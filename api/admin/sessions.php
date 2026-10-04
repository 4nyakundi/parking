<?php
/**
 * Mombasa Mall Basement Parking - Admin Sessions Management API
 * Supports pagination, multi-column search, date filtering, notes update, and session details.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

$user = require_role(['supervisor', 'admin'], true);

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

try {
    $db = get_db();

    // -------------------------------------------------------------
    // ACTION: Update Session Notes
    // -------------------------------------------------------------
    if ($action === 'update_notes') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?? $_POST;

        $sessionId = (int)($input['session_id'] ?? 0);
        $notes     = trim((string)($input['notes'] ?? ''));

        if ($sessionId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid session_id.']);
            exit;
        }

        $stmt = $db->prepare('SELECT id, ticket_id, notes FROM parking_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        $sess = $stmt->fetch();

        if (!$sess) {
            echo json_encode(['ok' => false, 'error' => 'Session not found.']);
            exit;
        }

        $db->prepare('UPDATE parking_sessions SET notes = ? WHERE id = ?')->execute([$notes, $sessionId]);

        audit_log($user['id'], 'UPDATE_SESSION_NOTES', 'parking_sessions', $sess['ticket_id'], [
            'old_notes' => $sess['notes'],
        ], [
            'new_notes' => $notes,
        ]);

        echo json_encode(['ok' => true, 'message' => 'Notes updated successfully.']);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: View Single Session Details (Snapshots, audit log, WhatsApp)
    // -------------------------------------------------------------
    if ($action === 'detail') {
        $sessionId = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare('
            SELECT 
                s.*,
                u1.full_name AS approved_by_name,
                u2.full_name AS cleared_by_name
            FROM parking_sessions s
            LEFT JOIN users u1 ON u1.id = s.approved_by
            LEFT JOIN users u2 ON u2.id = s.cleared_by
            WHERE s.id = ?
        ');
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            echo json_encode(['ok' => false, 'error' => 'Session not found.']);
            exit;
        }

        // Fetch audit records for this session ticket
        $auditStmt = $db->prepare('
            SELECT a.*, u.full_name AS user_name
            FROM audit_log a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.entity = "parking_sessions" AND a.entity_id = :ticket
            ORDER BY a.id ASC
        ');
        $auditStmt->execute([':ticket' => $session['ticket_id']]);
        $audits = $auditStmt->fetchAll();

        // Fetch WhatsApp queue history
        $waStmt = $db->prepare('SELECT * FROM whatsapp_queue WHERE session_id = ? ORDER BY id DESC');
        $waStmt->execute([$sessionId]);
        $waMessages = $waStmt->fetchAll();

        echo json_encode([
            'ok'   => true,
            'data' => [
                'session'  => $session,
                'audits'   => $audits,
                'whatsapp' => $waMessages,
            ],
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: List Sessions with Filters and Pagination
    // -------------------------------------------------------------
    $page        = max(1, (int)($_GET['page'] ?? 1));
    $limit       = max(10, min(100, (int)($_GET['limit'] ?? 25)));
    $offset      = ($page - 1) * $limit;

    $plate       = trim((string)($_GET['plate'] ?? ''));
    $ticket      = trim((string)($_GET['ticket'] ?? ''));
    $destination = trim((string)($_GET['destination'] ?? ''));
    $status      = trim((string)($_GET['status'] ?? ''));
    $dateFrom    = trim((string)($_GET['date_from'] ?? ''));
    $dateTo      = trim((string)($_GET['date_to'] ?? ''));
    $guardId     = (int)($_GET['guard_id'] ?? 0);

    $where = ['1=1'];
    $params = [];

    if (!empty($plate)) {
        $cleanPlate = PlateHelper::clean($plate);
        $where[] = 's.plate_number LIKE :plate';
        $params[':plate'] = "%{$cleanPlate}%";
    }

    if (!empty($ticket)) {
        $where[] = 's.ticket_id LIKE :ticket';
        $params[':ticket'] = "%{$ticket}%";
    }

    if (!empty($destination)) {
        $where[] = 's.destination = :dest';
        $params[':dest'] = $destination;
    }

    if (!empty($status)) {
        $where[] = 's.status = :status';
        $params[':status'] = $status;
    }

    if (!empty($dateFrom)) {
        $where[] = 's.entry_time >= :date_from';
        $params[':date_from'] = "{$dateFrom} 00:00:00";
    }

    if (!empty($dateTo)) {
        $where[] = 's.entry_time <= :date_to';
        $params[':date_to'] = "{$dateTo} 23:59:59";
    }

    if ($guardId > 0) {
        $where[] = '(s.approved_by = :guard_id OR s.cleared_by = :guard_id)';
        $params[':guard_id'] = $guardId;
    }

    $whereSql = implode(' AND ', $where);

    // Count total records matching filter
    $countStmt = $db->prepare("SELECT COUNT(*) FROM parking_sessions s WHERE {$whereSql}");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    // Fetch page records
    $sql = "
        SELECT 
            s.*,
            u1.full_name AS approved_by_name,
            u2.full_name AS cleared_by_name,
            TIMESTAMPDIFF(MINUTE, s.entry_time, IFNULL(s.exit_time, NOW())) AS calculated_duration
        FROM parking_sessions s
        LEFT JOIN users u1 ON u1.id = s.approved_by
        LEFT JOIN users u2 ON u2.id = s.cleared_by
        WHERE {$whereSql}
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $sessions = [];
    foreach ($rows as $r) {
        $durMins = (int)$r['calculated_duration'];
        $hrs = floor($durMins / 60);
        $mins = $durMins % 60;
        $durStr = $hrs > 0 ? "{$hrs}h {$mins}m" : "{$mins}m";

        $sessions[] = [
            'id'               => (int)$r['id'],
            'ticket_id'        => $r['ticket_id'],
            'plate_number'     => $r['plate_number'],
            'formatted_plate'  => PlateHelper::format($r['plate_number']),
            'driver_name'      => $r['driver_name'],
            'driver_phone'     => PhoneHelper::formatDisplay($r['driver_phone']),
            'destination'      => $r['destination'],
            'entry_time'       => $r['entry_time'],
            'exit_time'        => $r['exit_time'],
            'status'           => $r['status'],
            'duration_minutes' => $durMins,
            'duration_string'  => $durStr,
            'entry_method'     => $r['entry_method'],
            'exit_method'      => $r['exit_method'],
            'approved_by_name' => $r['approved_by_name'] ?? 'System',
            'cleared_by_name'  => $r['cleared_by_name'] ?? null,
            'print_status'     => $r['print_status'],
            'whatsapp_status'  => $r['whatsapp_status'],
            'entry_snapshot'   => $r['entry_snapshot'],
            'exit_snapshot'    => $r['exit_snapshot'],
            'notes'            => $r['notes'],
            'synced_to_cloud'  => (bool)$r['synced_to_cloud'],
        ];
    }

    echo json_encode([
        'ok'   => true,
        'data' => [
            'total'     => $totalRecords,
            'page'      => $page,
            'limit'     => $limit,
            'pages'     => (int)ceil($totalRecords / $limit),
            'sessions'  => $sessions,
        ],
    ]);
} catch (Throwable $e) {
    error_log('ADMIN SESSIONS API ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database query error: ' . $e->getMessage()]);
}
