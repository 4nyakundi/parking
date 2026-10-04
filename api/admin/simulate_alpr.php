<?php
/**
 * Mombasa Mall Basement Parking - ALPR Simulation Endpoint
 * Allows administrators & supervisors to trigger simulated camera detections for testing.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';

$user = require_role(['supervisor', 'admin'], true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

$camera     = trim((string)($input['camera'] ?? 'entrance'));
$plate      = trim((string)($input['plate_number'] ?? 'KDA 123A'));
$confidence = (float)($input['confidence'] ?? 0.95);

$config = require __DIR__ . '/../../config/config.php';
$apiKey = $config['alpr']['api_key'] ?? '';

// Internal sub-request to gate/plate-detected.php
$url = 'http://127.0.0.1/parking/api/gate/plate-detected.php';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'camera'     => $camera,
        'plate'      => $plate,
        'confidence' => $confidence,
    ]),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    // If loopback cURL fails, include directly
    $_SERVER['HTTP_X_API_KEY'] = $apiKey;
    $_POST['camera'] = $camera;
    $_POST['plate'] = $plate;
    $_POST['confidence'] = $confidence;
    ob_start();
    require __DIR__ . '/../gate/plate-detected.php';
    $resStr = ob_get_clean();
    echo $resStr;
    exit;
}

http_response_code($httpCode);
echo $response;
