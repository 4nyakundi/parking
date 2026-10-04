<?php
/**
 * Mombasa Mall Basement Parking - Cloud Synchronization Edge Worker
 * Runs every 1 minute via Windows Task Scheduler.
 * Securely mirrors local edge data to remote management cloud via HMAC-SHA256.
 * Completely offline resilient; never drops local data.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && !isset($_GET['run_now'])) {
    http_response_code(403);
    echo "CLI execution only.\n";
    exit;
}

require_once __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/config.php';
$sCfg = $config['cloud_sync'] ?? [];

echo "[" . date('Y-m-d H:i:s') . "] Starting Cloud Sync Edge Worker...\n";

if (empty($sCfg['enabled'])) {
    echo "Cloud synchronization is disabled in config/config.php. Skipping sync cycle.\n";
    exit;
}

$endpoint   = $sCfg['endpoint'] ?? '';
$apiKey     = $sCfg['api_key'] ?? '';
$hmacSecret = $sCfg['hmac_secret'] ?? '';
$batchSize  = (int)($sCfg['batch_size'] ?? 50);
$timeout    = (int)($sCfg['timeout_seconds'] ?? 10);

if (empty($endpoint) || empty($apiKey) || empty($hmacSecret)) {
    echo "Cloud sync endpoint or credentials missing in config/config.php.\n";
    exit;
}

try {
    $db = get_db();

    // 1. Fetch unsynced parking sessions
    $stmt = $db->prepare('
        SELECT * 
        FROM parking_sessions 
        WHERE synced_to_cloud = 0 
        ORDER BY id ASC 
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $sessions = $stmt->fetchAll();

    if (empty($sessions)) {
        echo "All sessions currently in sync with Cloud Mirror.\n";
        exit;
    }

    echo "Found " . count($sessions) . " unsynced session(s). Packaging payload...\n";

    $sessionIds = array_column($sessions, 'id');

    // 2. Package sync payload
    $timestamp = time();
    $payloadData = [
        'timestamp' => $timestamp,
        'edge_pc'   => 'Mombasa-Mall-Edge-01',
        'sessions'  => $sessions,
    ];
    $jsonPayload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

    // 3. Generate cryptographic HMAC signature
    $signature = hash_hmac('sha256', (string)$jsonPayload, $hmacSecret);

    // 4. Send via cURL
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8',
            'X-API-Key: ' . $apiKey,
            'X-Signature: ' . $signature,
            'X-Timestamp: ' . $timestamp,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 5. Evaluate response
    if ($curlError) {
        echo "Edge offline or network unreachable ({$curlError}). Local data safe, will retry on next cycle.\n";
        updateCloudHealth('WARNING', 'Edge offline: ' . $curlError);
        exit;
    }

    $resJson = json_decode((string)$response, true) ?? [];

    if ($httpCode >= 200 && $httpCode < 300 && !empty($resJson['ok'])) {
        $syncedIds = $resJson['synced_ids'] ?? $sessionIds;
        $count = count($syncedIds);

        if (!empty($syncedIds)) {
            $inClause = implode(',', array_map('intval', $syncedIds));
            $db->exec("UPDATE parking_sessions SET synced_to_cloud = 1, synced_at = NOW() WHERE id IN ({$inClause})");
        }

        echo "Successfully synced {$count} records to Cloud Mirror!\n";
        updateCloudHealth('OK', "Synced {$count} sessions at " . date('H:i:s'));
    } else {
        $err = $resJson['error'] ?? "HTTP {$httpCode}: {$response}";
        echo "Cloud mirror returned error: {$err}\n";
        updateCloudHealth('ERROR', 'Cloud response: ' . substr($err, 0, 100));
    }
} catch (Throwable $e) {
    echo "SYNC WORKER ERROR: " . $e->getMessage() . "\n";
    error_log("SYNC WORKER ERROR: " . $e->getMessage());
    updateCloudHealth('ERROR', 'Worker exception: ' . $e->getMessage());
}

function updateCloudHealth(string $status, string $msg): void
{
    try {
        $db = get_db();
        $db->prepare('
            INSERT INTO device_health (device, status, last_checked_at, message)
            VALUES ("cloud_sync", :stat, NOW(), :msg)
            ON DUPLICATE KEY UPDATE status = VALUES(status), last_checked_at = NOW(), message = VALUES(message)
        ')->execute([':stat' => $status, ':msg' => substr($msg, 0, 255)]);
    } catch (Throwable) {}
}
