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
    // Supervisor Override if No Active Session Found
    // -------------------------------------------------------------
    if (!$session || $session['status'] !== 'ACTIVE') {
        if (empty($supervisorPin)) {
            echo json_encode([
                'ok'                        => false,
                'need_supervisor_override' => true,
                'error'                     => 'No active parking record found for this vehicle. Enter Supervisor PIN to authorize exit.',
            ]);
            exit;
        }

        // Verify supervisor PIN
        $supStmt = $db->prepare('
            SELECT id, full_name, pin_hash 
            FROM users 
            WHERE is_active = 1 AND role IN ("supervisor", "admin") AND pin_hash IS NOT NULL
        ');
        $supStmt->execute();
        $supervisors = $supStmt->fetchAll();

        $authorizedSupervisor = null;
        foreach ($supervisors as $s) {
            if (password_verify($supervisorPin, $s['pin_hash'])) {
                $authorizedSupervisor = $s;
                break;
            }
        }

        if (!$authorizedSupervisor) {
            echo json_encode([
                'ok'                        => false,
                'need_supervisor_override' => true,
                'error'                     => 'Invalid Supervisor PIN. Access denied.',
            ]);
            exit;
        }

        // Create an ad-hoc emergency departure session for audit integrity
        $cleanPlate = !empty($rawPlate) ? PlateHelper::clean($rawPlate) : 'UNKNOWN';
        $displayPlate = PlateHelper::format($cleanPlate);
        $datePart = date('Ymd');
        $ticketId = sprintf('MM-%s-EMERG-%04d', $datePart, rand(100, 999));

        $ins = $db->prepare('
            INSERT INTO parking_sessions (
                ticket_id, plate_number, driver_name, driver_phone, destination,
                entry_time, exit_time, status, duration_minutes,
                approved_by, cleared_by, entry_method, exit_method, notes, synced_to_cloud, created_at
            ) VALUES (
                :ticket_id, :plate, "Unregistered Driver", "254700000000", "Direct Exit",
                NOW(), NOW(), "COMPLETED", 0,
                :sup_id, :guard_id, "manual", "supervisor_override", :notes, 0, NOW()
            )
        ');
        $ins->execute([
            ':ticket_id' => $ticketId,
            ':plate'     => $cleanPlate,
            ':sup_id'    => $authorizedSupervisor['id'],
            ':guard_id'  => $user['id'],
            ':notes'     => 'SUPERVISOR OVERRIDE EXIT: ' . $overrideReason . ' (Authorized by ' . $authorizedSupervisor['full_name'] . ')',
        ]);
        $newSessId = (int)$db->lastInsertId();

        // Audit supervisor override
        audit_log($user['id'], 'SUPERVISOR_EXIT_OVERRIDE', 'parking_sessions', $ticketId, null, [
            'supervisor' => $authorizedSupervisor['full_name'],
            'plate'      => $cleanPlate,
            'reason'     => $overrideReason,
        ]);

        fire_event('session_completed', [
            'session_id'       => $newSessId,
            'ticket_id'        => $ticketId,
            'plate_number'     => $cleanPlate,
            'formatted_plate'  => $displayPlate,
            'duration_minutes' => 0,
            'duration_text'    => 'Override (0m)',
            'cleared_by'       => $user['full_name'] . " (Sup: {$authorizedSupervisor['full_name']})",
            'time'             => date('H:i:s'),
        ]);

        echo json_encode([
            'ok'   => true,
            'data' => [
                'session_id'       => $newSessId,
                'ticket_id'        => $ticketId,
                'plate_number'     => $cleanPlate,
                'formatted_plate'  => $displayPlate,
                'duration_minutes' => 0,
                'duration_text'    => 'Authorized via Supervisor Override',
                'cleared_by'       => $authorizedSupervisor['full_name'],
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
