<?php
/**
 * Mombasa Mall Basement Parking - Dahua ANPR Camera Real-Time Worker
 *
 * Connects directly to Dahua ITC413-PW4D-IZ1 Access ANPR camera (192.168.1.230)
 * listens to the live multipart eventManager.cgi stream, captures vehicle plate
 * recognitions and JPEG snapshots, and pushes them instantly into the parking system.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Africa/Nairobi');

$configFile = __DIR__ . '/../config/config.php';
$config = require $configFile;

$camConfig = $config['alpr']['entrance'] ?? [];
$camIp     = $camConfig['ip'] ?? '192.168.1.230';
$camUser   = $camConfig['username'] ?? 'admin';
$camPass   = $camConfig['password'] ?? 'Mall@2024';
$apiKey    = $config['alpr']['api_key'] ?? 'MOMBASA_PARKING_ALPR_SECRET_KEY_2026';
$apiUrl    = 'http://127.0.0.1/parking/api/gate/plate-detected.php';
$healthUrl = 'http://127.0.0.1/parking/api/health.php';

$streamUrl = "http://{$camIp}/cgi-bin/eventManager.cgi?action=attach&codes=[Traffic,TrafficTollGate,All]";
$snapUrl   = "http://{$camIp}/cgi-bin/snapshot.cgi?channel=1";

echo "========================================================\n";
echo " Mombasa Mall ANPR Worker - Dahua Access Camera Engine\n";
echo " Target Camera: http://{$camIp} (Dahua ITC413 ANPR)\n";
echo " Event Stream:  {$streamUrl}\n";
echo " API Receiver:  {$apiUrl}\n";
echo "========================================================\n\n";

$lastHeartbeat = 0;
$debounceCache = []; // [plate => timestamp]

function log_msg(string $msg): void {
    $time = date('Y-m-d H:i:s');
    echo "[{$time}] {$msg}\n";
}

function send_heartbeat(string $healthUrl, string $camIp, string $camUser, string $camPass): void {
    // Quick test snapshot
    $ch = curl_init("http://{$camIp}/cgi-bin/snapshot.cgi?channel=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$camUser}:{$camPass}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isOnline = ($httpCode === 200);
    log_msg("Camera heartbeat check: " . ($isOnline ? "ONLINE (HTTP 200)" : "OFFLINE (HTTP {$httpCode})"));
}

function dispatch_plate_to_api(string $apiUrl, string $apiKey, string $plate, float $confidence, ?string $imageBase64): bool {
    global $debounceCache;

    $clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $plate));
    $now = time();

    // 10 second debounce per plate
    if (isset($debounceCache[$clean]) && ($now - $debounceCache[$clean]) < 10) {
        log_msg("Debouncing repeat plate: {$clean}");
        return false;
    }
    $debounceCache[$clean] = $now;

    log_msg(">>> DISPATCHING PLATE TO API: {$clean} (Confidence: " . round($confidence * 100) . "%)");

    $payload = [
        'camera'       => 'entrance',
        'plate'        => $clean,
        'confidence'   => $confidence,
        'image_base64' => $imageBase64,
        'api_key'      => $apiKey,
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    log_msg("API Response [HTTP {$code}]: " . substr((string)$resp, 0, 150));
    return ($code === 200);
}

function grab_snapshot_base64(string $snapUrl, string $user, string $pass): ?string {
    $ch = curl_init($snapUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && !empty($data) && strlen($data) > 1000) {
        return base64_encode($data);
    }
    return null;
}

// Main streaming loop with auto-reconnection
$retryDelay = 2;

while (true) {
    log_msg("Connecting to Dahua ANPR stream: {$streamUrl} ...");

    $buffer = '';
    $readingImage = false;
    $imageLength = 0;
    $imageBuffer = '';
    $currentPlate = null;
    $currentConf  = 0.95;

    $ch = curl_init($streamUrl);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$camUser}:{$camPass}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 0); // Infinite stream
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024 * 16);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (
        &$buffer, &$readingImage, &$imageLength, &$imageBuffer,
        &$currentPlate, &$currentConf, $apiUrl, $apiKey, $snapUrl, $camUser, $camPass
    ) {
        $buffer .= $chunk;

        // Check for Dahua boundary markers
        while (($pos = strpos($buffer, "\r\n--myboundary\r\n")) !== false || ($pos = strpos($buffer, "--myboundary\r\n")) !== false) {
            $part = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + strlen("\r\n--myboundary\r\n"));

            if (empty(trim($part))) continue;

            // Search for Traffic/TrafficCar/PlateNumber in the part
            if (stripos($part, 'PlateNumber') !== false || stripos($part, 'Traffic') !== false) {
                log_msg("ANPR Event Received (" . strlen($part) . " bytes)");

                $matchedPlate = null;

                // 1. Try JSON parsing
                if (preg_match('/data\s*=\s*(\{.*\})/is', $part, $m)) {
                    $jsonObj = json_decode($m[1], true);
                    if ($jsonObj) {
                        $matchedPlate = $jsonObj['TrafficCar']['PlateNumber'] 
                            ?? $jsonObj['PlateNumber'] 
                            ?? $jsonObj['Plate']['PlateNumber'] 
                            ?? null;
                        if (isset($jsonObj['TrafficCar']['Confidence'])) {
                            $currentConf = ((float)$jsonObj['TrafficCar']['Confidence']) / 100.0;
                        }
                    }
                }

                // 2. Regex fallback for PlateNumber
                if (empty($matchedPlate) && preg_match('/["\']?PlateNumber["\']?\s*[:=]\s*["\']?([^"\'\r\n,;]+)/i', $part, $m)) {
                    $matchedPlate = trim($m[1]);
                }

                if (!empty($matchedPlate)) {
                    $matchedPlate = trim($matchedPlate);
                    $cleanUpper = strtoupper(preg_replace('/[^A-Z0-9]/', '', $matchedPlate));
                    // Filter out empty, null, or invalid strings
                    if (strlen($cleanUpper) >= 4 && !in_array($cleanUpper, ['NOPLATE', 'UNKNOWN', 'NONE', '000000', 'NULL'], true)) {
                        log_msg("Extracted Plate: {$matchedPlate}");
                        $currentPlate = $matchedPlate;

                        // Grab snapshot
                        $snapB64 = grab_snapshot_base64($snapUrl, $camUser, $camPass);
                        dispatch_plate_to_api($apiUrl, $apiKey, $currentPlate, $currentConf, $snapB64);
                        $currentPlate = null;
                    }
                }
            }
        }

        // Prevent memory overflow if buffer gets too big without a boundary
        if (strlen($buffer) > 2 * 1024 * 1024) {
            $buffer = substr($buffer, -512 * 1024);
        }

        return strlen($chunk);
    });

    $success = curl_exec($ch);
    $curlErr = curl_error($ch);
    $curlCode = curl_errno($ch);
    curl_close($ch);

    log_msg("Dahua stream disconnected [Code: {$curlCode}]: {$curlErr}. Reconnecting in {$retryDelay}s...");
    sleep($retryDelay);
    $retryDelay = min($retryDelay * 2, 20);
}
