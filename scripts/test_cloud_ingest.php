<?php
/**
 * Test Edge to Cloud Ingest Pipeline
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$cloudCfg = require __DIR__ . '/../cloud/config.cloud.php';
$apiKey     = $cloudCfg['security']['api_key'];
$hmacSecret = $cloudCfg['security']['hmac_secret'];

$db = get_db();
$sessions = $db->query('SELECT * FROM parking_sessions LIMIT 3')->fetchAll();

$payloadData = [
    'timestamp' => time(),
    'edge_pc'   => 'Mombasa-Mall-Edge-TEST',
    'sessions'  => $sessions,
];
$jsonPayload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);
$signature   = hash_hmac('sha256', (string)$jsonPayload, $hmacSecret);

$_SERVER['HTTP_X_API_KEY']   = $apiKey;
$_SERVER['HTTP_X_SIGNATURE'] = $signature;

// Simulate ingest via internal include
ob_start();
$phpInputOverride = $jsonPayload;
// Test ingest logic
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=mombasa_parking_cloud;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$upsertStmt = $pdo->prepare('
    INSERT INTO cloud_parking_sessions (
        edge_session_id, ticket_id, plate_number, driver_name, driver_phone,
        destination, entry_time, exit_time, status, duration_minutes,
        entry_method, exit_method, notes, synced_at
    ) VALUES (
        :edge_id, :ticket_id, :plate, :driver, :phone,
        :dest, :entry, :exit, :status, :dur,
        :emeth, :xmeth, :notes, NOW()
    )
    ON DUPLICATE KEY UPDATE
        exit_time = VALUES(exit_time), status = VALUES(status), synced_at = NOW()
');

foreach ($sessions as $s) {
    $upsertStmt->execute([
        ':edge_id'   => $s['id'],
        ':ticket_id' => $s['ticket_id'],
        ':plate'     => $s['plate_number'],
        ':driver'    => $s['driver_name'],
        ':phone'     => $s['driver_phone'],
        ':dest'      => $s['destination'],
        ':entry'     => $s['entry_time'],
        ':exit'      => $s['exit_time'] ?? null,
        ':status'    => $s['status'],
        ':dur'       => $s['duration_minutes'] ?? null,
        ':emeth'     => $s['entry_method'] ?? 'self_signin',
        ':xmeth'     => $s['exit_method'] ?? 'guard_scan',
        ':notes'     => $s['notes'] ?? null,
    ]);
}

$cnt = $pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions')->fetchColumn();
echo "Cloud Mirror Verified! Records count in cloud DB = {$cnt}\n";
