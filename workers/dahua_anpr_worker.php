<?php
/**
 * Mombasa Mall Basement Parking - Dahua Dual-Camera ANPR Real-Time Worker
 *
 * Connects simultaneously to BOTH Dahua ANPR Cameras:
 *   - Entrance ANPR Camera: 192.168.1.230
 *   - Exit ANPR Camera:     192.168.1.210
 * Master Credentials: Username: admin, Password: Mall@2024
 *
 * Listens to multipart /cgi-bin/eventManager.cgi event streams concurrently via curl_multi.
 * Extracts vehicle number plates, fetches high-res frame snapshots, and dispatches them
 * instantly to the parking system API (api/gate/plate-detected.php).
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Africa/Nairobi');

$configFile = __DIR__ . '/../config/config.php';
$config = require $configFile;

$apiKey    = $config['alpr']['api_key'] ?? 'MOMBASA_PARKING_ALPR_SECRET_KEY_2026';
$apiUrl    = 'http://127.0.0.1/parking/api/gate/plate-detected.php';
$healthUrl = 'http://127.0.0.1/parking/api/health.php';

// Target Cameras Configuration (Locked to Mall Network)
$cameras = [
    'entrance' => [
        'name'      => 'Entrance ANPR Camera',
        'ip'        => $config['alpr']['entrance']['ip'] ?? '192.168.1.230',
        'user'      => $config['alpr']['entrance']['username'] ?? 'admin',
        'pass'      => $config['alpr']['entrance']['password'] ?? 'Mall@2024',
        'stream'    => "http://" . ($config['alpr']['entrance']['ip'] ?? '192.168.1.230') . "/cgi-bin/eventManager.cgi?action=attach&codes=[Traffic,TrafficTollGate,PlateDetection,All]",
        'snap'      => "http://" . ($config['alpr']['entrance']['ip'] ?? '192.168.1.230') . "/cgi-bin/snapshot.cgi?channel=1",
        'last_seen' => 0,
        'status'    => 'CONNECTING',
    ],
    'exit' => [
        'name'      => 'Exit ANPR Camera',
        'ip'        => $config['alpr']['exit']['ip'] ?? '192.168.1.210',
        'user'      => $config['alpr']['exit']['username'] ?? 'admin',
        'pass'      => $config['alpr']['exit']['password'] ?? 'Mall@2024',
        'stream'    => "http://" . ($config['alpr']['exit']['ip'] ?? '192.168.1.210') . "/cgi-bin/eventManager.cgi?action=attach&codes=[Traffic,TrafficTollGate,PlateDetection,All]",
        'snap'      => "http://" . ($config['alpr']['exit']['ip'] ?? '192.168.1.210') . "/cgi-bin/snapshot.cgi?channel=1",
        'last_seen' => 0,
        'status'    => 'CONNECTING',
    ],
];

echo "====================================================================\n";
echo " Mombasa Mall ANPR Worker - Dahua Dual-Camera Real-Time Engine\n";
echo " Entrance Camera: http://{$cameras['entrance']['ip']} (User: {$cameras['entrance']['user']})\n";
echo " Exit Camera:     http://{$cameras['exit']['ip']} (User: {$cameras['exit']['user']})\n";
echo " API Receiver:    {$apiUrl}\n";
echo " Credentials:     Locked (admin / Mall@2024)\n";
echo "====================================================================\n\n";

$debounceCache = []; // [plate_camera => timestamp]
$lastHeartbeat = 0;

function log_msg(string $msg): void {
    $time = date('Y-m-d H:i:s');
    echo "[{$time}] {$msg}\n";
}

function grab_snapshot_base64(string $snapUrl, string $user, string $pass): ?string {
    $ch = curl_init($snapUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && !empty($data) && strlen($data) > 1000) {
        return base64_encode($data);
    }
    return null;
}

function dispatch_plate_to_api(
    string $apiUrl,
    string $apiKey,
    string $camera,
    string $plate,
    float $confidence,
    ?string $imageBase64
): bool {
    global $debounceCache;

    $clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $plate));
    $now = time();
    $cacheKey = "{$camera}_{$clean}";

    // 10 second debounce per plate per camera
    if (isset($debounceCache[$cacheKey]) && ($now - $debounceCache[$cacheKey]) < 10) {
        log_msg("[{$camera}] Debouncing repeat plate: {$clean}");
        return false;
    }
    $debounceCache[$cacheKey] = $now;

    log_msg(">>> [{$camera}] DISPATCHING DETECTED PLATE: {$clean} (Confidence: " . round($confidence * 100) . "%)");

    $payload = [
        'camera'       => $camera,
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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    log_msg("[{$camera}] API Response [HTTP {$code}]: " . substr((string)$resp, 0, 150));
    return ($code === 200);
}

function send_system_heartbeat(string $healthUrl, string $apiKey, array &$cameras): void {
    $camStatus = [];
    foreach ($cameras as $camKey => $cam) {
        $camStatus[$camKey] = [
            'ip'      => $cam['ip'],
            'status'  => $cam['status'],
            'message' => "Dahua {$cam['name']} ({$cam['ip']}): {$cam['status']}",
        ];
    }

    $payload = [
        'api_key'  => $apiKey,
        'message'  => 'Dahua Dual-Camera ANPR Worker active (Entrance: ' . $cameras['entrance']['status'] . ', Exit: ' . $cameras['exit']['status'] . ')',
        'cameras'  => $camStatus,
    ];

    $ch = curl_init($healthUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_exec($ch);
    curl_close($ch);

    log_msg("Heartbeat sent -> Entrance: {$cameras['entrance']['status']} | Exit: {$cameras['exit']['status']}");
}

// -----------------------------------------------------------------------
// Multi-cURL Concurrent Streaming Engine
// -----------------------------------------------------------------------
$mh = curl_multi_init();
$handles = []; // [camKey => [ch, buffer, retry_at]]

function init_camera_stream(string $camKey, array &$camConfig, string $apiUrl, string $apiKey): array {
    log_msg("Connecting stream for [{$camKey}] -> {$camConfig['stream']} ...");

    $buffer = '';
    $ch = curl_init($camConfig['stream']);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$camConfig['user']}:{$camConfig['pass']}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 0); // Continuous stream
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024 * 16);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (
        &$buffer, &$camConfig, $camKey, $apiUrl, $apiKey
    ) {
        $buffer .= $chunk;
        $camConfig['last_seen'] = time();
        $camConfig['status'] = 'OK';

        // Split on Dahua boundary markers
        while (($pos = strpos($buffer, "\r\n--myboundary\r\n")) !== false || ($pos = strpos($buffer, "--myboundary\r\n")) !== false) {
            $part = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + strlen("\r\n--myboundary\r\n"));

            if (empty(trim($part))) continue;

            if (stripos($part, 'PlateNumber') !== false || stripos($part, 'Traffic') !== false) {
                log_msg("[{$camKey}] ANPR Event Received (" . strlen($part) . " bytes)");

                $matchedPlate = null;
                $currentConf = 0.95;

                // 1. JSON parsing
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

                    if (strlen($cleanUpper) >= 4 && !in_array($cleanUpper, ['NOPLATE', 'UNKNOWN', 'NONE', '000000', 'NULL'], true)) {
                        log_msg("[{$camKey}] Extracted Plate: {$matchedPlate}");

                        // Grab live high-res snapshot
                        $snapB64 = grab_snapshot_base64($camConfig['snap'], $camConfig['user'], $camConfig['pass']);
                        dispatch_plate_to_api($apiUrl, $apiKey, $camKey, $matchedPlate, $currentConf, $snapB64);
                    }
                }
            }
        }

        // Buffer limit protection
        if (strlen($buffer) > 2 * 1024 * 1024) {
            $buffer = substr($buffer, -512 * 1024);
        }

        return strlen($chunk);
    });

    return [
        'ch'       => $ch,
        'buffer'   => &$buffer,
        'retry_at' => 0,
    ];
}

// Attach both cameras
foreach ($cameras as $camKey => &$camConfig) {
    $handles[$camKey] = init_camera_stream($camKey, $camConfig, $apiUrl, $apiKey);
    curl_multi_add_handle($mh, $handles[$camKey]['ch']);
}
unset($camConfig);

// Main asynchronous event loop
$running = null;

while (true) {
    // Check heartbeats every 30s
    $now = time();
    if (($now - $lastHeartbeat) >= 30) {
        send_system_heartbeat($healthUrl, $apiKey, $cameras);
        $lastHeartbeat = $now;
    }

    // Step multi-curl
    do {
        $mrc = curl_multi_exec($mh, $running);
    } while ($mrc === CURLM_CALL_MULTI_PERFORM);

    if ($mrc !== CURLM_OK) {
        log_msg("Multi-cURL error: {$mrc}");
    }

    // Check completed/failed handles
    while ($info = curl_multi_info_read($mh)) {
        $doneCh = $info['handle'];
        $result = $info['result'];

        foreach ($handles as $camKey => $data) {
            if ($data['ch'] === $doneCh) {
                $err = curl_error($doneCh);
                log_msg("[{$camKey}] Stream ended or disconnected (Err: {$err}). Will re-connect in 3s...");
                $cameras[$camKey]['status'] = 'OFFLINE';

                curl_multi_remove_handle($mh, $doneCh);
                curl_close($doneCh);

                // Schedule reconnect in 3s
                $handles[$camKey]['ch'] = null;
                $handles[$camKey]['retry_at'] = time() + 3;
                break;
            }
        }
    }

    // Handle reconnections
    foreach ($handles as $camKey => $data) {
        if ($data['ch'] === null && time() >= $data['retry_at']) {
            $handles[$camKey] = init_camera_stream($camKey, $cameras[$camKey], $apiUrl, $apiKey);
            curl_multi_add_handle($mh, $handles[$camKey]['ch']);
            $cameras[$camKey]['status'] = 'CONNECTING';
        }
    }

    // Non-busy wait with curl_multi_select
    if ($running > 0) {
        curl_multi_select($mh, 0.5);
    } else {
        usleep(300000); // 300ms sleep if waiting for reconnects
    }
}
