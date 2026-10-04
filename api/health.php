<?php
/**
 * Mombasa Mall Basement Parking - System Health & Diagnostics Endpoint
 * Reports real-time status of Database, Thermal Printer, CCTV Cameras, ALPR Worker, Internet, and Cloud Sync.
 * Also receives ALPR worker heartbeat via POST.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

$config = require __DIR__ . '/../../config/config.php';

// Check if this is an ALPR worker heartbeat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true) ?? $_POST;

    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($postData['api_key'] ?? '');
    if (!hash_equals($config['alpr']['api_key'] ?? '', (string)$apiKey)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid ALPR API Key.']);
        exit;
    }

    try {
        $db = get_db();
        $camStatus   = $postData['cameras'] ?? [];
        $workerMsg   = $postData['message'] ?? 'ALPR Python worker heartbeat healthy';

        // Update worker health
        $db->prepare('
            INSERT INTO device_health (device, status, last_checked_at, message)
            VALUES ("alpr_worker", "OK", NOW(), :msg)
            ON DUPLICATE KEY UPDATE status = "OK", last_checked_at = NOW(), message = VALUES(message)
        ')->execute([':msg' => $workerMsg]);

        if (isset($camStatus['entrance'])) {
            $entStat = $camStatus['entrance']['status'] ?? 'OK';
            $entMsg  = $camStatus['entrance']['message'] ?? 'Entrance stream active';
            $db->prepare('
                INSERT INTO device_health (device, status, last_checked_at, message)
                VALUES ("entrance_cam", :status, NOW(), :msg)
                ON DUPLICATE KEY UPDATE status = VALUES(status), last_checked_at = NOW(), message = VALUES(message)
            ')->execute([':status' => $entStat, ':msg' => $entMsg]);
        }

        if (isset($camStatus['exit'])) {
            $extStat = $camStatus['exit']['status'] ?? 'OK';
            $extMsg  = $camStatus['exit']['message'] ?? 'Exit stream active';
            $db->prepare('
                INSERT INTO device_health (device, status, last_checked_at, message)
                VALUES ("exit_cam", :status, NOW(), :msg)
                ON DUPLICATE KEY UPDATE status = VALUES(status), last_checked_at = NOW(), message = VALUES(message)
            ')->execute([':status' => $extStat, ':msg' => $extMsg]);
        }

        echo json_encode(['ok' => true, 'message' => 'Heartbeat logged']);
        exit;
    } catch (Throwable $e) {
        error_log('HEALTH HEARTBEAT ERROR: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// GET Health Status
$health = [
    'database'    => ['status' => 'ERROR', 'message' => 'Not connected'],
    'printer'     => ['status' => 'WARNING', 'message' => 'Printer unverified'],
    'entrance_cam'=> ['status' => 'WARNING', 'message' => 'Camera unverified'],
    'exit_cam'    => ['status' => 'WARNING', 'message' => 'Camera unverified'],
    'alpr_worker' => ['status' => 'WARNING', 'message' => 'Worker unverified'],
    'internet'    => ['status' => 'OFFLINE', 'message' => 'No active internet connection'],
    'cloud_sync'  => ['status' => 'OK', 'message' => 'Mirror standby', 'unsynced_records' => 0],
];

// 1. Database Health
try {
    $db = get_db();
    $db->query('SELECT 1');
    $health['database'] = ['status' => 'OK', 'message' => 'MariaDB running normally'];

    // Read device_health table for background services
    $stmt = $db->query('SELECT device, status, last_checked_at, message, TIMESTAMPDIFF(SECOND, last_checked_at, NOW()) AS age_seconds FROM device_health');
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $dev = $r['device'];
        $age = (int)$r['age_seconds'];
        $stat = $r['status'];
        $msg = $r['message'];

        // Worker or camera timeout check: if no heartbeat in 90 seconds, flag WARNING/OFFLINE
        if (in_array($dev, ['alpr_worker', 'entrance_cam', 'exit_cam'], true) && $age > 90) {
            $stat = 'WARNING';
            $msg .= " (Heartbeat delayed: {$age}s ago)";
        }

        if (isset($health[$dev])) {
            $health[$dev] = [
                'status'          => $stat,
                'message'         => $msg,
                'last_checked_at' => $r['last_checked_at'],
                'age_seconds'     => $age,
            ];
        }
    }

    // Cloud sync pending count
    $syncStmt = $db->query('SELECT COUNT(*) FROM parking_sessions WHERE synced_to_cloud = 0');
    $unsynced = (int)$syncStmt->fetchColumn();
    $health['cloud_sync']['unsynced_records'] = $unsynced;
    if ($unsynced > 50) {
        $health['cloud_sync']['status'] = 'WARNING';
        $health['cloud_sync']['message'] = "{$unsynced} sessions pending cloud upload";
    }
} catch (Throwable $e) {
    $health['database'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// 2. Internet connectivity probe (Quick non-blocking socket test)
$internetOk = false;
$fp = @fsockopen('8.8.8.8', 53, $errno, $errstr, 1.0);
if ($fp) {
    fclose($fp);
    $internetOk = true;
} else {
    // Try HTTP fallback
    $fp2 = @fsockopen('www.google.com', 80, $errno2, $errstr2, 1.0);
    if ($fp2) {
        fclose($fp2);
        $internetOk = true;
    }
}

$health['internet'] = [
    'status'  => $internetOk ? 'OK' : 'OFFLINE',
    'message' => $internetOk ? 'Connected to Mall WAN / Internet' : 'Offline (Local LAN only - system operating normally in edge mode)',
];

echo json_encode([
    'ok'          => true,
    'timestamp'   => date('Y-m-d H:i:s'),
    'devices'     => $health,
]);
