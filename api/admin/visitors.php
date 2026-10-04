<?php
/**
 * Mombasa Mall Basement Parking - Admin Visitors History API
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

require_role(['supervisor', 'admin'], true);

try {
    $db = get_db();

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;
    $status = trim((string)($_GET['status'] ?? ''));

    $where = '1=1';
    $params = [];

    if (!empty($status)) {
        $where .= ' AND v.status = :status';
        $params[':status'] = $status;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM visitors v WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "
        SELECT 
            v.*,
            u.full_name AS handled_by_name
        FROM visitors v
        LEFT JOIN users u ON u.id = v.handled_by
        WHERE {$where}
        ORDER BY v.id DESC
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

    $visitors = [];
    foreach ($rows as $r) {
        $visitors[] = [
            'id'              => (int)$r['id'],
            'plate_number'    => $r['plate_number'],
            'formatted_plate' => PlateHelper::format($r['plate_number']),
            'driver_name'     => $r['driver_name'],
            'driver_phone'    => PhoneHelper::formatDisplay($r['driver_phone']),
            'destination'     => $r['destination'],
            'source'          => $r['source'],
            'status'          => $r['status'],
            'reject_reason'   => $r['reject_reason'],
            'alpr_verified'   => (bool)$r['alpr_verified'],
            'handled_by_name' => $r['handled_by_name'] ?? 'System',
            'created_at'      => $r['created_at'],
        ];
    }

    echo json_encode([
        'ok'   => true,
        'data' => [
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int)ceil($total / $limit),
            'visitors' => $visitors,
        ],
    ]);
} catch (Throwable $e) {
    error_log('ADMIN VISITORS API ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
