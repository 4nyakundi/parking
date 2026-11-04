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
require_once __DIR__ . '/../../includes/admin_actions.php';
admin_require_mutation($action, ['force_close', 'batch_reconcile', 'shift_signoff']);

try {
    $db = get_db();

    // -------------------------------------------------------------
    // 1. LIST SESSIONS (Configurable Threshold: All Active, >4h, >8h, >12h)
    // -------------------------------------------------------------
    if ($action === 'list') {
        $reviewHours=(int)($db->query("SELECT value FROM system_settings WHERE `key`='overstay_hours'")->fetchColumn() ?: 8);
        $hoursThreshold = isset($_GET['hours']) && $_GET['hours']!=='' ? max(0,(int)$_GET['hours']) : $reviewHours;
        $capacity=(int)($db->query("SELECT value FROM system_settings WHERE `key`='capacity'")->fetchColumn() ?: 60);

        $sql = '
            SELECT 
                s.id, s.ticket_id, s.plate_number, s.driver_name, s.destination, s.entry_time, s.notes,
                TIMESTAMPDIFF(HOUR, s.entry_time, NOW()) AS hours_active,
                TIMESTAMPDIFF(MINUTE, s.entry_time, NOW()) AS mins_active
            FROM parking_sessions s
            WHERE s.status = "ACTIVE"
        ';
        $params = [];

        if ($hoursThreshold > 0) {
            $sql .= ' AND TIMESTAMPDIFF(HOUR, s.entry_time, NOW()) >= :hours';
            $params[':hours'] = $hoursThreshold;
        }

        $sql .= ' ORDER BY s.entry_time ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $stuck = $stmt->fetchAll();

        $data = [];
        foreach ($stuck as $r) {
            $mins = (int)$r['mins_active'];
            $hrs = floor($mins / 60);
            $remMins = $mins % 60;
            $dwellText = $hrs > 0 ? "{$hrs}h {$remMins}m" : "{$remMins}m";

            $data[] = [
                'id'              => (int)$r['id'],
                'ticket_id'       => $r['ticket_id'],
                'plate_number'    => $r['plate_number'],
                'formatted_plate' => PlateHelper::format($r['plate_number']),
                'driver_name'     => $r['driver_name'] ?: 'Visitor',
                'destination'     => $r['destination'] ?: 'Mall Store',
                'entry_time'      => $r['entry_time'],
                'hours_active'    => (int)$r['hours_active'],
                'mins_active'     => $mins,
                'dwell_text'      => $dwellText,
                'notes'           => $r['notes'] ?: '',
                'urgency'         => ($r['hours_active'] >= 12) ? 'ghost' : (($r['hours_active'] >= 8) ? 'overstay' : 'normal'),
            ];
        }

        // Live Deck Telemetry for Zero-Drift Calculator
        $totalActive = (int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE status = "ACTIVE"')->fetchColumn();
        $overstay8h  = (int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE status = "ACTIVE" AND TIMESTAMPDIFF(HOUR, entry_time, NOW()) >= 8')->fetchColumn();
        $extended4h  = (int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE status = "ACTIVE" AND TIMESTAMPDIFF(HOUR, entry_time, NOW()) >= 4')->fetchColumn();
        $todayEntries = (int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE DATE(entry_time) = CURDATE()')->fetchColumn();
        $todayExits   = (int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE status = "COMPLETED" AND DATE(exit_time) = CURDATE()')->fetchColumn();
        $overridesToday = (int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE DATE(exit_time) = CURDATE() AND exit_method IN ("supervisor_override", "supervisor_reconcile")')->fetchColumn();

        echo json_encode([
            'ok'      => true,
            'data'    => $data,
            'count'   => count($data),
            'metrics' => [
                'total_active'      => $totalActive,
                'capacity' => $capacity,
                'available' => max(0, $capacity - $totalActive),
                'overstay_8h'       => $overstay8h,
                'extended_4h'       => $extended4h,
                'today_entries'     => $todayEntries,
                'today_exits'       => $todayExits,
                'overrides_today'   => $overridesToday,
                'threshold_applied' => $hoursThreshold,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------
    // 2. FORCE CLOSE / RECONCILE SINGLE SESSION
    // -------------------------------------------------------------
    if ($action === 'force_close') {
        $sessionId = (int)($input['session_id'] ?? 0);
        $reason    = trim((string)($input['reason'] ?? 'Supervisor physical count verified vehicle departed.'));

        $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE id = ? AND status = "ACTIVE"');
        $stmt->execute([$sessionId]);
        $sess = $stmt->fetch();

        if (!$sess) {
            echo json_encode(['ok' => false, 'error' => 'Active session not found or already closed.']);
            exit;
        }

        $entryTime = new DateTime($sess['entry_time']);
        $exitTime  = new DateTime();
        $interval  = $entryTime->diff($exitTime);
        $durationMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        $update = $db->prepare('
            UPDATE parking_sessions 
            SET status = "COMPLETED", exit_time = NOW(), duration_minutes = :dur,
                cleared_by = :user_id, exit_method = "supervisor_reconcile",
                notes = CONCAT(IFNULL(notes, ""), " [RECONCILED: ", :reason, "]"),
                synced_to_cloud = 0
            WHERE id = :id AND status = "ACTIVE"
        ');
        $update->execute([
            ':dur'     => $durationMinutes,
            ':user_id' => $user['id'],
            ':reason'  => $reason,
            ':id'      => $sessionId,
        ]);
        if (!$update->rowCount()) { http_response_code(409); echo json_encode(['ok'=>false,'error'=>'This session was already closed. Refresh the list.']); exit; }

        // Audit log
        audit_log($user['id'], 'RECONCILE_FORCE_CLOSE', 'parking_sessions', $sess['ticket_id'], [
            'status' => 'ACTIVE',
        ], [
            'status'           => 'COMPLETED',
            'reason'           => $reason,
            'duration_minutes' => $durationMinutes,
            'supervisor'       => $user['full_name'],
        ]);

        // Broadcast realtime event
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

        echo json_encode([
            'ok'      => true,
            'message' => "Session {$sess['ticket_id']} successfully reconciled and cleared from basement occupancy.",
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 3. BATCH RECONCILE (Multi-session clearance)
    // -------------------------------------------------------------
    if ($action === 'batch_reconcile') {
        $ids = $input['session_ids'] ?? [];
        $reason = trim((string)($input['reason'] ?? 'Supervisor physical count verified ghost sessions departed.'));

        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['ok' => false, 'error' => 'Please select at least one session to reconcile.']);
            exit;
        }

        $cleared = 0;
        foreach ($ids as $sId) {
            $sessId = (int)$sId;
            $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE id = ? AND status = "ACTIVE"');
            $stmt->execute([$sessId]);
            $sess = $stmt->fetch();
            if (!$sess) continue;

            $entryTime = new DateTime($sess['entry_time']);
            $exitTime  = new DateTime();
            $interval  = $entryTime->diff($exitTime);
            $durMins   = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

            $update = $db->prepare('
                UPDATE parking_sessions 
                SET status = "COMPLETED", exit_time = NOW(), duration_minutes = :dur,
                    cleared_by = :user_id, exit_method = "supervisor_reconcile",
                    notes = CONCAT(IFNULL(notes, ""), " [BATCH RECONCILED: ", :reason, "]"),
                    synced_to_cloud = 0
                WHERE id = :id AND status = "ACTIVE"
            ');
        $update->execute([
                ':dur'     => $durMins,
                ':user_id' => $user['id'],
                ':reason'  => $reason,
                ':id'      => $sessId,
            ]);
        if (!$update->rowCount()) { continue; }

            audit_log($user['id'], 'RECONCILE_BATCH_CLOSE', 'parking_sessions', $sess['ticket_id'], null, [
                'reason'     => $reason,
                'supervisor' => $user['full_name'],
            ]);

            $cleared++;
        }

        if ($cleared) fire_event('session_completed', ['reconciled_count'=>$cleared]);
        echo json_encode([
            'ok'      => true,
            'message' => "Successfully reconciled {$cleared} session(s). Basement occupancy updated.",
            'cleared' => $cleared,
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 4. SUPERVISOR SHIFT SIGN-OFF & DECK LOG
    // -------------------------------------------------------------
    if ($action === 'shift_signoff') {
        require_once __DIR__ . '/../../includes/admin_signoff.php';
        $details=admin_record_signoff($db,$user,$input['physical_count']??null,trim((string)($input['notes']??'')));
        $delta=$details['drift_delta'];

        echo json_encode([
            'ok'      => true,
            'message' => 'Shift reconciliation signed off and recorded in permanent audit log.',
            'delta'   => $delta,
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Invalid reconcile action.']);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    error_log('RECONCILE ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
