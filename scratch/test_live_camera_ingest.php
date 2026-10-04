<?php
/**
 * Test live camera fetch & ingestion into Mombasa Mall Parking System
 */

$camIp = '192.168.1.230';
$camUser = 'admin';
$camPass = 'Mall@2024';

echo "[1/4] Connecting to live camera at http://$camIp ...\n";

$snapUrl = "http://{$camIp}/cgi-bin/snapshot.cgi?channel=1";
$ch = curl_init($snapUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
    CURLOPT_USERPWD        => "{$camUser}:{$camPass}",
]);

$imgData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($imgData)) {
    echo "ERROR: Failed to fetch snapshot from camera. HTTP Code: {$httpCode}\n";
    exit(1);
}

$imgBytes = strlen($imgData);
echo "SUCCESS: Fetched fresh live frame from camera! Size: " . number_format($imgBytes / 1024, 2) . " KB\n";

echo "[2/4] Packaging frame for ALPR Ingestion API ...\n";
$b64 = base64_encode($imgData);

$payload = [
    'camera'       => 'entrance',
    'plate'        => 'KDA 456C', // Matches pending visitor Samuel Mwangi
    'confidence'   => 0.96,
    'image_base64' => $b64,
    'api_key'      => 'MOMBASA_PARKING_ALPR_SECRET_KEY_2026',
];

echo "[3/4] Dispatching to http://127.0.0.1/parking/api/gate/plate-detected.php ...\n";
$chApi = curl_init('http://127.0.0.1/parking/api/gate/plate-detected.php');
curl_setopt_array($chApi, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-API-Key: MOMBASA_PARKING_ALPR_SECRET_KEY_2026',
    ],
    CURLOPT_TIMEOUT        => 10,
]);

$apiResponse = curl_exec($chApi);
$apiCode     = curl_getinfo($chApi, CURLINFO_HTTP_CODE);
curl_close($chApi);

echo "API Response ({$apiCode}):\n";
echo $apiResponse . "\n\n";

echo "[4/4] Verifying poll feed from http://127.0.0.1/parking/api/poll.php ...\n";
$pollRes = file_get_contents('http://127.0.0.1/parking/api/poll.php?since=0');
echo "Poll Response:\n";
echo $pollRes . "\n";
