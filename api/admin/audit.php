<?php
/**
 * Mombasa Mall Basement Parking - Immutable Audit Log Viewer API
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';

$user = require_role(['supervisor', 'admin'], true);

try {
    $db = get_db();

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    $action = trim((string)($_GET['action_filter'] ?? ''));
    $entity = trim((string)($_GET['entity_filter'] ?? ''));

    $where = ['1=1'];
    $params = [];

    if (!empty($action)) {
        $where[] = 'a.action LIKE :action';
        $params[':action'] = "%{$action}%";
    }

    if (!empty($entity)) {
        $where[] = 'a.entity = :entity';
        $params[':entity'] = $entity;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log a WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "
        SELECT 
            a.*,
            u.full_name AS user_name,
            u.role AS user_role
        FROM audit_log a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE {$whereSql}
        ORDER BY a.id DESC
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

    $logs = [];
    foreach ($rows as $r) {
        $logs[] = [
            'id'         => (int)$r['id'],
            'user_name'  => $r['user_name'] ?? 'System / Anonymous',
            'user_role'  => $r['user_role'] ?? 'system',
            'action'     => $r['action'],
            'entity'     => $r['entity'],
            'entity_id'  => $r['entity_id'],
            'old_value'  => json_decode($r['old_value'] ?? '', true),
            'new_value'  => json_decode($r['new_value'] ?? '', true),
            'ip'         => $r['ip'],
            'created_at' => $r['created_at'],
        ];
    }

    echo json_encode([
        'ok'   => true,
        'data' => [
            'total' => $total,
            'page'  => $page,
            'pages' => (int)ceil($total / $limit),
            'logs'  => $logs,
        ],
    ]);
} catch (Throwable $e) {
    error_log('AUDIT LOG API ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
