<?php
/** Consistent dashboard bootstrap and recovery snapshot; no operational writes. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/guard_snapshot.php';
require_once __DIR__ . '/../../includes/guard_overview.php';
require_auth(true);
session_write_close();
try {
    $db = get_db();
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
    // The first consistent read establishes the snapshot before subsequent reads.
    $cursor = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM events')->fetchColumn();
    $pending = guard_pending_snapshot($db);
    $roster = guard_roster_snapshot($db);
    $dailyOverview = guard_daily_overview($db);
    $devices = $db->query('SELECT device, status, message, last_checked_at, TIMESTAMPDIFF(SECOND, last_checked_at, NOW()) AS age_seconds FROM device_health')->fetchAll();
    $failures = $db->query('SELECT id, ticket_id, plate_number, updated_at FROM parking_sessions WHERE print_status = "failed" ORDER BY id DESC LIMIT 100')->fetchAll();
    $capacity = $db->query('SELECT value FROM system_settings WHERE `key` = "capacity"')->fetchColumn();
    $db->commit();
    echo json_encode(['ok' => true, 'last_event_id' => $cursor, 'server_timestamp' => time(),
        'pending' => $pending, 'roster' => $roster, 'devices' => $devices, 'daily_overview' => $dailyOverview,
        'print_failures' => $failures, 'capacity' => (int)($capacity ?: 60)]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('GUARD SNAPSHOT: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Dashboard refresh failed. Last known data has been preserved.']);
}
