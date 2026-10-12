<?php
/**
 * Mombasa Mall Basement Parking - Clear Exit Endpoint
 * Marks session as COMPLETED, calculates duration, queues exit WhatsApp,
 * records permanent audit log, and handles supervisor overrides for missing entries.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';
require_once __DIR__ . '/../../includes/whatsapp_service.php';

$user = require_auth(true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

$sessionId     = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$ticketId      = trim((string)($input['ticket_id'] ?? ''));
$rawPlate      = trim((string)($input['plate_number'] ?? ''));
$supervisorPin = trim((string)($input['supervisor_pin'] ?? ''));
$overrideReason= trim((string)($input['override_reason'] ?? 'Manual Gate Exit Override'));

try {
    $db = get_db();

    // 1. Locate the active session
    $session = null;

    if ($sessionId > 0) {
        $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
    } elseif (!empty($ticketId)) {
        $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE ticket_id = ?');
        $stmt->execute([$ticketId]);
        $session = $stmt->fetch();
    } elseif (!empty($rawPlate)) {
        $cleanPlate = PlateHelper::clean($rawPlate);
        $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE plate_number = ? AND status = "ACTIVE" ORDER BY id DESC LIMIT 1');
        $stmt->execute([$cleanPlate]);
        $session = $stmt->fetch();
    }

    // -------------------------------------------------------------
    // Departure Processing if No Active Session Found
    // "even if the car left without data being collected, let it get out and inform the guard that the car left without collection of detail"
    // -------------------------------------------------------------
    if (!$session || $session['status'] !== 'ACTIVE') {
        $cleanPlate = !empty($rawPlate) ? PlateHelper::clean($rawPlate) : 'UNKNOWN';
        $displayPlate = PlateHelper::format($cleanPlate);

        // Check if supervisor PIN was explicitly provided
        $authorizedSupervisor = null;
        if (!empty($supervisorPin)) {
            $supStmt = $db->prepare('
                SELECT id, full_name, pin_hash 
                FROM users 
                WHERE is_active = 1 AND role IN ("supervisor", "admin") AND pin_hash IS NOT NULL
            ');
            $supStmt->execute();
            foreach ($supStmt->fetchAll() as $s) {
                if (password_verify($supervisorPin, $s['pin_hash'])) {
                    $authorizedSupervisor = $s;
                    break;
                }
            }
        }

        // Look up vehicle entry time from visitors or alpr_detections
        $entryTime = null;
        $visStmt = $db->prepare('
            SELECT id, destination, created_at 
            FROM visitors 
            WHERE plate_number = :plate 
            ORDER BY id DESC LIMIT 1
        ');
        $visStmt->execute([':plate' => $cleanPlate]);
        $pendingVis = $visStmt->fetch();

        if ($pendingVis) {
            $entryTime = $pendingVis['created_at'];
            // Mark pending visitor request as EXPIRED so it clears from the entrance screen
            $db->prepare('UPDATE visitors SET status = "EXPIRED" WHERE id = ?')->execute([(int)$pendingVis['id']]);
        } else {
            $detStmt = $db->prepare('
                SELECT created_at 
                FROM alpr_detections 
                WHERE camera = "entrance" AND plate_clean = :plate 
                ORDER BY id DESC LIMIT 1
            ');
            $detStmt->execute([':plate' => $cleanPlate]);
            $entryTime = $detStmt->fetchColumn() ?: date('Y-m-d H:i:s', time() - 300);
        }

        $entryTs = strtotime($entryTime);
        $durationMinutes = max(1, (int)round((time() - $entryTs) / 60));
        $durHours = floor($durationMinutes / 60);
        $durMins  = $durationMinutes % 60;
        $durationText = $durHours > 0 ? "{$durHours}h {$durMins}m" : "{$durMins} mins";

        $datePart = date('Ymd');
        $ticketId = sprintf('MM-%s-UNCOL-%04d', $datePart, rand(1000, 9999));

        $exitMethod = $authorizedSupervisor ? 'supervisor_override' : 'guard_exit_uncollected';
        $notes = $authorizedSupervisor 
            ? "SUPERVISOR OVERRIDE EXIT: {$overrideReason} (Authorized by {$authorizedSupervisor['full_name']})"
            : "EXIT WITHOUT DETAILS: Vehicle left without collection of driver details. Cleared by {$user['full_name']}";

        $ins = $db->prepare('
            INSERT INTO parking_sessions (
                ticket_id, plate_number, driver_name, driver_phone, destination,
                entry_time, exit_time, status, duration_minutes,
                approved_by, cleared_by, entry_method, exit_method, notes, synced_to_cloud, created_at
            ) VALUES (
                :ticket_id, :plate, "Uncollected Driver", "", "Direct Exit",
                :entry_time, NOW(), "COMPLETED", :duration,
                :approved_by, :guard_id, "alpr", :exit_method, :notes, 0, NOW()
            )
        ');
        $ins->execute([
            ':ticket_id'    => $ticketId,
            ':plate'        => $cleanPlate,
            ':entry_time'   => $entryTime,
            ':duration'     => $durationMinutes,
            ':approved_by'  => $authorizedSupervisor ? $authorizedSupervisor['id'] : $user['id'],
            ':guard_id'     => $user['id'],
            ':exit_method'  => $exitMethod,
            ':notes'        => $notes,
        ]);
        $newSessId = (int)$db->lastInsertId();

        // Audit Log
        audit_log($user['id'], 'EXIT_WITHOUT_DETAILS', 'parking_sessions', $ticketId, null, [
            'plate'       => $cleanPlate,
            'entry_time'  => $entryTime,
            'duration'    => $durationMinutes,
            'cleared_by'  => $user['full_name'],
            'supervisor'  => $authorizedSupervisor ? $authorizedSupervisor['full_name'] : null,
        ]);

        // Fire realtime event to inform all guard stations
        fire_event('exit_without_details', [
            'session_id'         => $newSessId,
            'ticket_id'          => $ticketId,
            'plate_number'       => $cleanPlate,
            'formatted_plate'    => $displayPlate,
            'entry_time'         => $entryTime,
            'entry_time_display' => date('h:i A', $entryTs),
            'exit_time'          => date('Y-m-d H:i:s'),
            'exit_time_display'  => date('h:i A'),
            'duration_minutes'   => $durationMinutes,
            'duration_text'      => $durationText,
            'cleared_by'         => $user['full_name'],
            'message'            => "Vehicle {$displayPlate} exited without collection of details! Entered: " . date('h:i A', $entryTs) . " | Exited: " . date('h:i A') . " ({$durationText}).",
            'time'               => date('H:i:s'),
        ]);

        fire_event('session_completed', [
            'session_id'       => $newSessId,
            'ticket_id'        => $ticketId,
            'plate_number'     => $cleanPlate,
            'formatted_plate'  => $displayPlate,
            'duration_minutes' => $durationMinutes,
            'duration_text'    => $durationText,
            'cleared_by'       => $user['full_name'],
            'time'             => date('H:i:s'),
        ]);

        echo json_encode([
            'ok'   => true,
            'data' => [
                'session_id'       => $newSessId,
                'ticket_id'        => $ticketId,
                'plate_number'     => $cleanPlate,
                'formatted_plate'  => $displayPlate,
                'entry_time'       => $entryTime,
                'entry_time_display' => date('h:i A', $entryTs),
                'duration_minutes' => $durationMinutes,
                'duration_text'    => $durationText,
                'notice'           => "Vehicle cleared for exit without prior collection of driver details. Entry recorded at: {$entryTime}.",
                'cleared_by'       => $user['full_name'],
            ],
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // Standard Exit Clearance
    // -------------------------------------------------------------
    $sessId = (int)$session['id'];
    $cleanPlate = $session['plate_number'];
    $displayPlate = PlateHelper::format($cleanPlate);

    // Look for recent exit snapshot from ALPR
    $snapStmt = $db->prepare('
        SELECT snapshot_path 
        FROM alpr_detections 
        WHERE camera = "exit" AND plate_clean = ? 
        ORDER BY id DESC LIMIT 1
    ');
    $snapStmt->execute([$cleanPlate]);
    $exitSnapshot = $snapStmt->fetchColumn() ?: $session['exit_snapshot'];

    // Calculate duration in minutes
    $entryTime = new DateTime($session['entry_time']);
    $exitTime  = new DateTime();
    $interval  = $entryTime->diff($exitTime);
    $durationMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

    $durHours = floor($durationMinutes / 60);
    $durMins  = $durationMinutes % 60;
    $durationText = $durHours > 0 ? "{$durHours}h {$durMins}m" : "{$durMins} mins";

    // Update session to COMPLETED
    $upd = $db->prepare('
        UPDATE parking_sessions 
        SET status = "COMPLETED",
            exit_time = NOW(),
            duration_minutes = :duration,
            cleared_by = :guard_id,
            exit_snapshot = :snapshot,
            exit_method = "guard_clear",
            synced_to_cloud = 0
        WHERE id = :id
    ');
    $upd->execute([
        ':duration' => $durationMinutes,
        ':guard_id' => $user['id'],
        ':snapshot' => $exitSnapshot,
        ':id'       => $sessId,
    ]);

    // Send WhatsApp exit notification if configured
    if (!empty($session['driver_phone'])) {
        WhatsAppService::queueExit($sessId, $session['driver_phone'], [
            'driver_name'      => $session['driver_name'],
            'plate_number'     => $cleanPlate,
            'ticket_id'        => $session['ticket_id'],
            'exit_time'        => date('Y-m-d H:i:s'),
            'duration_minutes' => $durationMinutes,
        ]);
    }

    // Audit Log
    audit_log($user['id'], 'CLEAR_EXIT', 'parking_sessions', $session['ticket_id'], [
        'status' => 'ACTIVE',
    ], [
        'status'           => 'COMPLETED',
        'duration_minutes' => $durationMinutes,
        'cleared_by'       => $user['full_name'],
    ]);

    // Broadcast realtime event
    fire_event('session_completed', [
        'session_id'       => $sessId,
        'ticket_id'        => $session['ticket_id'],
        'plate_number'     => $cleanPlate,
        'formatted_plate'  => $displayPlate,
        'duration_minutes' => $durationMinutes,
        'duration_text'    => $durationText,
        'cleared_by'       => $user['full_name'],
        'time'             => date('H:i:s'),
    ]);

    echo json_encode([
        'ok'   => true,
        'data' => [
            'session_id'       => $sessId,
            'ticket_id'        => $session['ticket_id'],
            'plate_number'     => $cleanPlate,
            'formatted_plate'  => $displayPlate,
            'duration_minutes' => $durationMinutes,
            'duration_text'    => $durationText,
            'driver_name'      => $session['driver_name'],
            'cleared_by'       => $user['full_name'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('CLEAR EXIT ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to process exit: ' . $e->getMessage()]);
}
