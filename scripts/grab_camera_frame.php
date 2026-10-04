<?php
/**
 * Mombasa Mall Basement Parking - Camera Frame Grabber Utility
 * Fetches snapshot from Dahua camera (192.168.1.230) and ingests it into system.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';

$camCfg = $config['alpr']['entrance'] ?? [];
$camIp   = $camCfg['ip'] ?? '192.168.1.230';
$camUser = $camCfg['username'] ?? 'admin';
$camPass = $camCfg['password'] ?? 'Mall@2024';
$snapUrl = $camCfg['snapshot_url'] ?? "http://{$camIp}/cgi-bin/snapshot.cgi?channel=1";

echo "[" . date('Y-m-d H:i:s') . "] Grabbing frame from {$snapUrl} ...\n";

$ch = curl_init($snapUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
    CURLOPT_USERPWD        => "{$camUser}:{$camPass}",
]);

$imgData = curl_exec($ch);
$code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err     = curl_error($ch);
curl_close($ch);

if ($code !== 200 || empty($imgData)) {
    echo "ERROR: Snapshot failed (HTTP {$code}): {$err}\n";
    exit(1);
}

$dateFolder = date('Y/m/d');
$saveDir = __DIR__ . '/../storage/snapshots/' . $dateFolder;
if (!is_dir($saveDir)) {
    mkdir($saveDir, 0777, true);
}

$fileName = 'cam_entrance_manual_' . date('His') . '.jpg';
$filePath = $saveDir . '/' . $fileName;
file_put_contents($filePath, $imgData);

$relPath = 'storage/snapshots/' . $dateFolder . '/' . $fileName;
$sizeKb  = round(strlen($imgData) / 1024, 2);

echo "SUCCESS: Saved {$sizeKb} KB snapshot to {$filePath}\n";

// Update entrance camera health
try {
    $db = get_db();
    $db->prepare('
        INSERT INTO device_health (device, status, last_checked_at, message)
        VALUES ("entrance_cam", "OK", NOW(), :msg)
        ON DUPLICATE KEY UPDATE status = "OK", last_checked_at = NOW(), message = VALUES(message)
    ')->execute([':msg' => "Dahua 192.168.1.230 snapshot OK ({$sizeKb} KB)"]);
} catch (Throwable $e) {}

echo "Relative snapshot path: {$relPath}\n";
