<?php
/**
 * Mombasa Mall Basement Parking - Supervisor Reconciliation Tool
 * Audited reconciliation of stuck, orphaned, or abandoned sessions to maintain zero-drift occupancy.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/plate_helper.php';

$user = require_role(['supervisor', 'admin'], true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'list');

try {
    $db = get_db();

    // 1. LIST STUCK SESSIONS (> 8 hours active)
    if ($action === 'list') {
        $stmt = $db->query('
            SELECT 
                s.id, s.ticket_id, s.plate_number, s.driver_name, s.destination, s.entry_time, s.notes,
                TIMESTAMPDIFF(HOUR, s.entry_time, NOW()) AS hours_active,
                TIMESTAMPDIFF(MINUTE, s.entry_time, NOW()) AS mins_active
            FROM parking_sessions s
            WHERE s.status = "ACTIVE" AND TIMESTAMPDIFF(HOUR, s.entry_time, NOW()) >= 8
            ORDER BY s.entry_time ASC
        ');
        $stuck = $stmt->fetchAll();

        $data = [];
        foreach ($stuck as $r) {
            $data[] = [
                'id'              => (int)$r['id'],
                'ticket_id'       => $r['ticket_id'],
                'plate_number'    => $r['plate_number'],
                'formatted_plate' => PlateHelper::format($r['plate_number']),
                'driver_name'     => $r['driver_name'],
                'destination'     => $r['destination'],
                'entry_time'      => $r['entry_time'],
                'hours_active'    => (int)$r['hours_active'],
                'notes'           => $r['notes'],
            ];
        }

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // 2. FORCE CLOSE / RECONCILE SESSION
    if ($action === 'force_close') {
        $sessionId = (int)($input['session_id'] ?? 0);
        $reason    = trim((string)($input['reason'] ?? 'Supervisor physical count verified vehicle departed.'));

        $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE id = ? AND status = "ACTIVE"');
        $stmt->execute([$sessionId]);
        $sess = $stmt->fetch();

        if (!$sess) {
            echo json_encode(['ok' => false, 'error' => 'Active session not found.']);
            exit;
        }

        $entryTime = new DateTime($sess['entry_time']);
        $exitTime  = new DateTime();
        $interval  = $entryTime->diff($exitTime);
        $durationMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        $db->prepare('
            UPDATE parking_sessions 
            SET status = "COMPLETED", exit_time = NOW(), duration_minutes = :dur,
                cleared_by = :user_id, exit_method = "supervisor_reconcile",
                notes = CONCAT(IFNULL(notes, ""), " [RECONCILED: ", :reason, "]"),
                synced_to_cloud = 0
            WHERE id = :id
        ')->execute([
            ':dur'     => $durationMinutes,
            ':user_id' => $user['id'],
            ':reason'  => $reason,
            ':id'      => $sessionId,
        ]);

        // Audit log
        audit_log($user['id'], 'RECONCILE_FORCE_CLOSE', 'parking_sessions', $sess['ticket_id'], [
            'status' => 'ACTIVE',
        ], [
            'status'           => 'COMPLETED',
            'reason'           => $reason,
            'duration_minutes' => $durationMinutes,
        ]);

        // Broadcast realtime event to update occupancy counts on all screens
        fire_event('session_completed', [
            'session_id'       => $sessionId,
            'ticket_id'        => $sess['ticket_id'],
            'plate_number'     => $sess['plate_number'],
            'formatted_plate'  => PlateHelper::format($sess['plate_number']),
            'duration_minutes' => $durationMinutes,
            'duration_text'    => 'Reconciled',
            'cleared_by'       => $user['full_name'],
            'time'             => date('H:i:s'),
        ]);

        echo json_encode(['ok' => true, 'message' => "Session {$sess['ticket_id']} successfully reconciled."]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Invalid reconcile action.']);
} catch (Throwable $e) {
    error_log('RECONCILE ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
