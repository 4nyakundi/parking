<?php
$camIp = '192.168.1.230';
$user  = 'admin';
$pass  = 'Mall@2024';

$url = "http://{$camIp}/cgi-bin/snapshot.cgi?channel=1";
$savePath = __DIR__ . '/../storage/snapshots/live_entrance_snap.jpg';

if (!is_dir(dirname($savePath))) {
    mkdir(dirname($savePath), 0777, true);
}

$ch = curl_init($url);
$fp = fopen($savePath, 'wb');
curl_setopt_array($ch, [
    CURLOPT_FILE           => $fp,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
    CURLOPT_USERPWD        => "{$user}:{$pass}",
]);

$success = curl_exec($ch);
$code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err     = curl_error($ch);
curl_close($ch);
fclose($fp);

if ($success && $code === 200 && file_exists($savePath)) {
    $size = filesize($savePath);
    $imgInfo = getimagesize($savePath);
    echo "SUCCESS: Saved live snapshot to {$savePath}\n";
    echo "File Size: " . number_format($size / 1024, 2) . " KB\n";
    echo "Dimensions: " . ($imgInfo[0] ?? '?') . " x " . ($imgInfo[1] ?? '?') . " (" . ($imgInfo['mime'] ?? '') . ")\n";
} else {
    echo "FAILED: HTTP Code {$code}, Error: {$err}\n";
}
