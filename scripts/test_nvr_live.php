<?php
/**
 * Test Dahua NVR connectivity and snapshot acquisition
 */
declare(strict_types=1);

$nvrIp = '192.168.0.3';
$channels = [1, 2];
$credentials = [
    'admin:Mall@2024',
    'admin:admin123',
    'admin:admin',
];

echo "Testing Dahua NVR connectivity at {$nvrIp}...\n";

foreach ($channels as $ch) {
    echo "\n--- Channel {$ch} (" . ($ch === 1 ? 'Entrance' : 'Exit') . ") ---\n";
    $success = false;
    foreach ($credentials as $cred) {
        $url = "https://{$nvrIp}/cgi-bin/snapshot.cgi?channel={$ch}";
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
        curl_setopt($c, CURLOPT_USERPWD, $cred);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($c, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $err = curl_error($c);
        curl_close($c);

        if ($code === 200 && !empty($data) && strlen($data) > 1000) {
            echo "SUCCESS: Channel {$ch} with [{$cred}]: Got " . strlen($data) . " bytes JPEG!\n";
            $savePath = __DIR__ . "/../storage/snapshots/live_test_ch{$ch}.jpg";
            @mkdir(dirname($savePath), 0777, true);
            file_put_contents($savePath, $data);
            echo "Saved snapshot to: {$savePath}\n";
            $success = true;
            break;
        } else {
            echo "Attempt [{$cred}]: HTTP {$code}, Error: {$err}, Bytes: " . strlen((string)$data) . "\n";
        }
    }
    if (!$success) {
        echo "FAILED: Could not retrieve snapshot for Channel {$ch}.\n";
    }
}
