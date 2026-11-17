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
if (!is_array($data) || (!isset($data['sessions']) && !isset($data['vehicles']))) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Malformed JSON payload structure.']);
    exit;
}

$edgePc   = $data['edge_pc'] ?? 'Mombasa-Mall-Edge';
$sessions = $data['sessions'] ?? [];
$vehicles = $data['vehicles'] ?? [];
if (!is_array($sessions) || !is_array($vehicles) || count($sessions) + count($vehicles) > 1000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid or oversized sync segment.']);
    exit;
}

foreach ($sessions as $session) {
    if (!is_array($session) || !isset($session['id'], $session['ticket_id'], $session['plate_number'], $session['driver_name'], $session['entry_time'], $session['status'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'A parking session record is incomplete.']);
        exit;
    }
}
foreach ($vehicles as $vehicle) {
    if (!is_array($vehicle) || !isset($vehicle['id'], $vehicle['plate_number'], $vehicle['created_at'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'A registered vehicle record is incomplete.']);
        exit;
    }
}

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

    require_once __DIR__ . '/../schema_init.php';
    ensure_cloud_schema($pdo);

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
                        destination      = VALUES(destination),
            exit_time        = VALUES(exit_time),
            status           = VALUES(status),
            duration_minutes = VALUES(duration_minutes),
            exit_method      = VALUES(exit_method),
            notes            = VALUES(notes),
            synced_at        = NOW()
    ');
    $vehicleUpsertStmt = $pdo->prepare('
        INSERT INTO registered_vehicles (
            id, plate_number, owner_name, phone, vehicle_type, category, notes, is_active,
            visit_count, is_frequent, default_destination, last_visit_at, driver_name,
            driver_phone, profile_archived, profile_reset_at, created_at
        ) VALUES (
            :id, :plate, :owner, :phone, :type, :category, :notes, :active, :visits,
            :frequent, :destination, :last_visit, :driver, :driver_phone, :archived,
            :reset_at, :created_at
        )
        ON DUPLICATE KEY UPDATE
            plate_number = VALUES(plate_number), owner_name = VALUES(owner_name),
            phone = VALUES(phone), vehicle_type = VALUES(vehicle_type),
            category = VALUES(category), notes = VALUES(notes), is_active = VALUES(is_active),
            visit_count = VALUES(visit_count), is_frequent = VALUES(is_frequent),
            default_destination = VALUES(default_destination), last_visit_at = VALUES(last_visit_at),
            driver_name = VALUES(driver_name), driver_phone = VALUES(driver_phone),
            profile_archived = VALUES(profile_archived), profile_reset_at = VALUES(profile_reset_at),
            created_at = VALUES(created_at)
    ');

    $pdo->beginTransaction();
    $syncedVehicleIds = [];

    foreach ($sessions as $s) {
        $upsertStmt->execute([
            ':edge_id'   => $s['id'],
            ':ticket_id' => $s['ticket_id'],
            ':plate'     => $s['plate_number'],
            ':driver'    => $s['driver_name'],
            ':phone'     => $s['driver_phone'] ?? '',
            ':dest'      => $s['destination'] ?? '',
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

    foreach ($vehicles as $vehicle) {
        $vehicleUpsertStmt->execute([
            ':id' => $vehicle['id'],
            ':plate' => $vehicle['plate_number'],
            ':owner' => $vehicle['owner_name'] ?? null,
            ':phone' => $vehicle['phone'] ?? null,
            ':type' => $vehicle['vehicle_type'] ?? 'car',
            ':category' => $vehicle['category'] ?? 'regular',
            ':notes' => $vehicle['notes'] ?? null,
            ':active' => $vehicle['is_active'] ?? 1,
            ':visits' => $vehicle['visit_count'] ?? 0,
            ':frequent' => $vehicle['is_frequent'] ?? 0,
            ':destination' => $vehicle['default_destination'] ?? null,
            ':last_visit' => $vehicle['last_visit_at'] ?? null,
            ':driver' => $vehicle['driver_name'] ?? null,
            ':driver_phone' => $vehicle['driver_phone'] ?? null,
            ':archived' => $vehicle['profile_archived'] ?? 0,
            ':reset_at' => $vehicle['profile_reset_at'] ?? null,
            ':created_at' => $vehicle['created_at'],
        ]);
        $syncedVehicleIds[] = $vehicle['id'];
    }

    // 6. Record Edge Heartbeat
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $pdo->prepare('
        INSERT INTO cloud_edge_heartbeats (edge_pc, records_count, ip_address, last_synced_at)
        VALUES (:pc, :cnt, :ip, NOW())
    ')->execute([
        ':pc'  => $edgePc,
        ':cnt'  => count($sessions) + count($vehicles),
        ':ip'  => $ip,
    ]);

    $pdo->commit();

    echo json_encode([
        'ok'           => true,
        'message'      => 'Synced ' . count($syncedIds) . ' session(s) and ' . count($syncedVehicleIds) . ' vehicle profile(s) successfully.',
        'synced_ids'   => $syncedIds,
        'synced_vehicle_ids' => $syncedVehicleIds,
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
