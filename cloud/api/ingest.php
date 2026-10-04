<?php
/**
 * Mombasa Mall Basement Parking - Cloud Mirror Ingestion API
 * Receives encrypted & signed sync batches from Edge PC.
 * Authenticated via X-API-Key and HMAC-SHA256 signature.
 * Idempotent upsert logic protects against duplicate transmissions.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../config.cloud.php';

// 1. Verify Secret API Key
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = $config['security']['api_key'] ?? '';

if (empty($providedKey) || !hash_equals($expectedKey, (string)$providedKey)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: Invalid Cloud API Key.']);
    exit;
}

// 2. Read raw payload
$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty request body.']);
    exit;
}

// 3. Verify HMAC-SHA256 signature
$providedSig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$hmacSecret  = $config['security']['hmac_secret'] ?? '';
$expectedSig = hash_hmac('sha256', $rawBody, $hmacSecret);

if (empty($providedSig) || !hash_equals($expectedSig, (string)$providedSig)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'HMAC signature verification failed. Tampered or corrupted transmission.']);
    exit;
}

// 4. Parse JSON payload
$data = json_decode($rawBody, true);
if (!$data || !isset($data['sessions'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Malformed JSON payload structure.']);
    exit;
}

$edgePc   = $data['edge_pc'] ?? 'Mombasa-Mall-Edge';
$sessions = $data['sessions'] ?? [];

try {
    $dbCfg = $config['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbCfg['host'], $dbCfg['port'], $dbCfg['dbname'], $dbCfg['charset']
    );
    $pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $syncedIds = [];

    // 5. Idempotent Upsert of Sessions
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
            exit_time        = VALUES(exit_time),
            status           = VALUES(status),
            duration_minutes = VALUES(duration_minutes),
            exit_method      = VALUES(exit_method),
            notes            = VALUES(notes),
            synced_at        = NOW()
    ');

    $pdo->beginTransaction();

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
        $syncedIds[] = $s['id'];
    }

    // 6. Record Edge Heartbeat
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $pdo->prepare('
        INSERT INTO cloud_edge_heartbeats (edge_pc, records_count, ip_address, last_synced_at)
        VALUES (:pc, :cnt, :ip, NOW())
    ')->execute([
        ':pc'  => $edgePc,
        ':cnt' => count($sessions),
        ':ip'  => $ip,
    ]);

    $pdo->commit();

    echo json_encode([
        'ok'           => true,
        'message'      => 'Synced ' . count($syncedIds) . ' records successfully.',
        'synced_ids'   => $syncedIds,
        'cloud_time'   => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('CLOUD INGEST ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Cloud database error: ' . $e->getMessage()]);
}
