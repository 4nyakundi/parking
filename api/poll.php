<?php
/**
 * Mombasa Mall Basement Parking - High-Performance Polling Endpoint
 * Called every 2 seconds by guard tablets and dashboards.
 * Returns events strictly newer than `since` ID and fresh live occupancy counters.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../../config/database.php';

$sinceId = isset($_GET['since']) ? (int)$_GET['since'] : 0;

try {
    $db = get_db();

    // 1. Fetch new events since the requested ID
    $evtStmt = $db->prepare('
        SELECT id, type, payload, created_at
        FROM events
        WHERE id > :since
        ORDER BY id ASC
        LIMIT 50
    ');
    $evtStmt->execute([':since' => $sinceId]);
    $rawEvents = $evtStmt->fetchAll();

    $events = [];
    $maxId = $sinceId;

    foreach ($rawEvents as $row) {
        $maxId = max($maxId, (int)$row['id']);
        $payload = json_decode($row['payload'], true) ?? [];
        $events[] = [
            'id'         => (int)$row['id'],
            'type'       => $row['type'],
            'payload'    => $payload,
            'created_at' => $row['created_at'],
        ];
    }

    // 2. Compute LIVE Occupancy directly from database to prevent counter drift
    // Occupied = COUNT(parking_sessions WHERE status = 'ACTIVE')
    $cfg = require __DIR__ . '/../../config/config.php';
    $capacity = (int)($cfg['app']['capacity'] ?? 60);

    // Check system_settings if customized
    $setStmt = $db->query('SELECT value FROM system_settings WHERE `key` = "capacity" LIMIT 1');
    $dbCap = $setStmt->fetchColumn();
    if ($dbCap !== false && is_numeric($dbCap)) {
        $capacity = (int)$dbCap;
    }

    $occStmt = $db->query('SELECT COUNT(*) FROM parking_sessions WHERE status = "ACTIVE"');
    $occupied = (int)$occStmt->fetchColumn();
    $available = max(0, $capacity - $occupied);

    // 3. Count currently pending visitor requests
    $pendStmt = $db->query('SELECT COUNT(*) FROM visitors WHERE status = "PENDING"');
    $pendingCount = (int)$pendStmt->fetchColumn();

    echo json_encode([
        'ok'            => true,
        'last_event_id' => $maxId,
        'events'        => $events,
        'stats'         => [
            'capacity'      => $capacity,
            'occupied'      => $occupied,
            'available'     => $available,
            'pending_count' => $pendingCount,
            'server_time'   => date('H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    error_log('POLL ERROR: ' . $e->getMessage());
    echo json_encode([
        'ok'    => false,
        'error' => 'Polling temporary failure: ' . $e->getMessage(),
    ]);
}
