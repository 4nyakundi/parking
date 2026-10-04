<?php
$camIp = '192.168.1.230';
$user = 'admin';
$pass = 'Mall@2024';

function cam_get($path) {
    global $camIp, $user, $pass;
    $url = "http://{$camIp}{$path}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_HTTPAUTH => CURLAUTH_ANY,
        CURLOPT_USERPWD => "{$user}:{$pass}",
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

echo "=== SYSTEM INFO ===\n";
echo cam_get('/cgi-bin/magicBox.cgi?action=getSystemInfo') . "\n";

echo "=== DEVICE TYPE ===\n";
echo cam_get('/cgi-bin/magicBox.cgi?action=getDeviceType') . "\n";

echo "=== SERIAL NUMBER ===\n";
echo cam_get('/cgi-bin/magicBox.cgi?action=getSerialNo') . "\n";

echo "=== SOFTWARE VERSION ===\n";
echo cam_get('/cgi-bin/magicBox.cgi?action=getSoftwareVersion') . "\n";

echo "=== VIDEO CHANNELS / ENCODE CONFIG ===\n";
echo cam_get('/cgi-bin/configManager.cgi?action=getConfig&name=Encode') . "\n";
