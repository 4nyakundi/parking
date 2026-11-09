<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/guard_history.php';
$user=require_auth(true);
session_write_close();
try {
    $options = guard_history_options(array_merge($_GET,['guard_id'=>$user['id']]));
    $db = get_db();
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
    $data = guard_history_report($db, $options);
    $db->commit();
    echo json_encode(['ok' => true, 'data' => $data]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('GUARD HISTORY: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Traffic history is unavailable. Please retry.']);
}
