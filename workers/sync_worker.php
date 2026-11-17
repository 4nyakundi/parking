<?php
/**
 * Mombasa Mall Basement Parking - Cloud Synchronization Edge Worker
 * Runs on the existing Windows Task Scheduler cadence and sends bounded segments.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && !isset($_GET['run_now'])) {
    http_response_code(403);
    echo "CLI execution only.\n";
    exit;
}

require_once __DIR__ . '/../includes/cloud_sync_service.php';

$config = require __DIR__ . '/../config/config.php';
$syncConfig = $config['cloud_sync'] ?? [];
$interval = max(1, (int)($syncConfig['interval_minutes'] ?? 20));
$segmentSize = max(1, min(500, (int)($syncConfig['batch_size'] ?? 50)));

echo '[' . date('Y-m-d H:i:s') . "] Starting Cloud Sync Edge Worker...\n";

if (empty($syncConfig['enabled'])) {
    echo "Cloud synchronization is disabled in local configuration. Skipping sync cycle.\n";
    exit;
}

if (empty($syncConfig['endpoint']) || empty($syncConfig['api_key']) || empty($syncConfig['hmac_secret'])) {
    echo "Cloud sync endpoint or credentials are missing in local configuration.\n";
    exit;
}

try {
    $schedule = CloudSyncService::claimScheduledRun($interval);
    if (!$schedule['due']) {
        echo 'Next scheduled segment is due in ' . (int)$schedule['seconds_remaining'] . " second(s).\n";
        exit;
    }

    $result = CloudSyncService::syncPendingBatch($segmentSize);
    if (!$result['ok']) {
        updateCloudHealth('ERROR', (string)($result['error'] ?? 'Cloud synchronization failed.'));
        fwrite(STDERR, 'Cloud mirror returned error: ' . ($result['error'] ?? 'Sync failed.') . "\n");
        exit(1);
    }

    $sessionCount = (int)($result['session_count'] ?? 0);
    $vehicleCount = (int)($result['vehicle_count'] ?? 0);
    echo "Synchronized {$sessionCount} session(s) and {$vehicleCount} vehicle profile(s) in this segment.\n";
    updateCloudHealth('OK', "Synced {$sessionCount} session(s) and {$vehicleCount} vehicle profile(s)");
} catch (Throwable $e) {
    error_log('SYNC WORKER ERROR: ' . $e->getMessage());
    updateCloudHealth('ERROR', 'Worker exception: ' . $e->getMessage());
    fwrite(STDERR, "SYNC WORKER ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

function updateCloudHealth(string $status, string $message): void
{
    $db = get_db();
    $db->prepare('
        INSERT INTO device_health (device, status, last_checked_at, message)
        VALUES ("cloud_sync", :status, NOW(), :message)
        ON DUPLICATE KEY UPDATE status = VALUES(status), last_checked_at = NOW(), message = VALUES(message)
    ')->execute([
        ':status' => $status,
        ':message' => substr($message, 0, 255),
    ]);
}
