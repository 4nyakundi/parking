<?php
/**
 * Mombasa Mall Basement Parking - Fetch Camera Plate & Snapshot Endpoint
 * Fetches real-time snapshot and plate data from Dahua ANPR camera (192.168.1.230)
 * and creates a pending intake card for the guard station.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';
require_once __DIR__ . '/../../includes/events.php';

$user = require_auth(true);
$config = require __DIR__ . '/../../config/config.php';

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true) ?? [];
$manualPlate = trim((string)($json['plate'] ?? $_POST['plate'] ?? $_GET['plate'] ?? ''));
$targetCamera = in_array(strtolower((string)($json['camera'] ?? $_POST['camera'] ?? $_GET['camera'] ?? '')), ['exit', 'cam2'], true) ? 'exit' : 'entrance';

$camConfig = $config['alpr'][$targetCamera] ?? [];
$camIp   = $camConfig['ip'] ?? ($targetCamera === 'exit' ? '192.168.1.210' : '192.168.1.230');
$camUser = $camConfig['username'] ?? 'admin';
$camPass = $camConfig['password'] ?? 'Mall@2024';
$snapUrl = $camConfig['snapshot_url'] ?? "http://{$camIp}/cgi-bin/snapshot.cgi?channel=1";

$startTime = microtime(true);
$snapshotRelPath = null;
$cameraOnline = false;

// 1. Fetch live snapshot from Dahua camera
$ch = curl_init($snapUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
curl_setopt($ch, CURLOPT_USERPWD, "{$camUser}:{$camPass}");
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$imageBinary = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

$latencyMs = (int)round((microtime(true) - $startTime) * 1000);

if ($httpCode === 200 && !empty($imageBinary) && strlen($imageBinary) > 1000) {
    $cameraOnline = true;
    $dateFolder = date('Y/m/d');
    $fullDir = __DIR__ . '/../../storage/snapshots/' . $dateFolder;
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0777, true);
    }
    $fileName = sprintf('cam_entrance_live_%s_%s.jpg', date('His'), substr(md5(uniqid()), 0, 6));
    $filePath = $fullDir . '/' . $fileName;
    file_put_contents($filePath, $imageBinary);
    $snapshotRelPath = 'storage/snapshots/' . $dateFolder . '/' . $fileName;
}

try {
    $db = get_db();

    // Update device_health for Target Camera
    $camDeviceKey = ($targetCamera === 'exit') ? 'exit_cam' : 'entrance_cam';
    $camStatus = $cameraOnline ? 'OK' : 'OFFLINE';
    $msg = $cameraOnline ? "Dahua {$targetCamera} ANPR Camera ({$camIp}) active (Latency: {$latencyMs}ms)" : "Camera unreachable: {$curlErr}";
    try {
        $db->prepare('
            INSERT INTO device_health (device, status, last_checked_at, message)
            VALUES (:device, :status, NOW(), :msg)
            ON DUPLICATE KEY UPDATE status = VALUES(status), last_checked_at = NOW(), message = VALUES(message)
        ')->execute([
            ':device' => $camDeviceKey,
            ':status' => $camStatus,
            ':msg'    => $msg,
        ]);
    } catch (Throwable) {}

    // 2. Determine Plate Number
    $detectedPlate = '';
    if (!empty($manualPlate)) {
        $detectedPlate = PlateHelper::clean($manualPlate);
    } else {
        // Look for the most recent plate seen by target camera within last 2 minutes
        $detStmt = $db->prepare('
            SELECT plate_clean 
            FROM alpr_detections 
            WHERE camera = :cam AND created_at >= (NOW() - INTERVAL 2 MINUTE)
            ORDER BY id DESC LIMIT 1
        ');
        $detStmt->execute([':cam' => $targetCamera]);
        $recentDet = $detStmt->fetchColumn();
        if ($recentDet) {
            $detectedPlate = $recentDet;
        } else {
            // Check newest detection in table if within last 1 hour
            $detStmt2 = $db->prepare('
                SELECT plate_clean 
                FROM alpr_detections 
                WHERE camera = :cam AND created_at >= (NOW() - INTERVAL 1 HOUR)
                ORDER BY id DESC LIMIT 1
            ');
            $detStmt2->execute([':cam' => $targetCamera]);
            $detectedPlate = $detStmt2->fetchColumn() ?: '';
        }
    }

    if (empty($detectedPlate)) {
        echo json_encode([
            'ok'            => true,
            'camera_online' => $cameraOnline,
            'snapshot_url'  => $snapshotRelPath,
            'plate_found'   => false,
            'message'       => 'Camera is live. No vehicle plate currently positioned in detection zone.',
        ]);
        exit;
    }

    $cleanPlate   = PlateHelper::clean($detectedPlate);
    $displayPlate = PlateHelper::format($cleanPlate);

    // Check if vehicle is already parked inside
    $actStmt = $db->prepare('SELECT id, ticket_id, entry_time FROM parking_sessions WHERE plate_number = :plate AND status = "ACTIVE" LIMIT 1');
    $actStmt->execute([':plate' => $cleanPlate]);
    $activeInside = $actStmt->fetch();

    if ($activeInside) {
        echo json_encode([
            'ok'             => true,
            'camera_online'  => $cameraOnline,
            'is_duplicate'   => true,
            'plate_number'   => $cleanPlate,
            'formatted_plate'=> $displayPlate,
            'snapshot_url'   => $snapshotRelPath,
            'message'        => "Vehicle {$displayPlate} is already parked inside with Ticket #{$activeInside['ticket_id']}.",
        ]);
        exit;
    }

    // Check if already in pending visitors
    $visStmt = $db->prepare('SELECT id, driver_name, driver_phone, destination FROM visitors WHERE plate_number = :plate AND status = "PENDING" ORDER BY id DESC LIMIT 1');
    $visStmt->execute([':plate' => $cleanPlate]);
    $existing = $visStmt->fetch();

    $requestId = null;
    $driverName = 'Visitor';
    $driverPhone = '';
    $destination = 'Mombasa Mall';

    // Check registered vehicles
    $regStmt = $db->prepare('SELECT owner_name, phone, category, notes FROM registered_vehicles WHERE plate_number = :plate AND is_active = 1 LIMIT 1');
    $regStmt->execute([':plate' => $cleanPlate]);
    $regVehicle = $regStmt->fetch();

    if ($regVehicle) {
        $driverName  = !empty($regVehicle['owner_name']) ? $regVehicle['owner_name'] : $driverName;
        $driverPhone = !empty($regVehicle['phone']) ? $regVehicle['phone'] : $driverPhone;
    }

    if ($existing) {
        $requestId = (int)$existing['id'];
        $db->prepare('UPDATE visitors SET alpr_verified = 1 WHERE id = ?')->execute([$requestId]);
    } else {
        $ins = $db->prepare('
            INSERT INTO visitors (plate_number, driver_name, driver_phone, destination, source, status, alpr_verified, created_at)
            VALUES (:plate, :name, :phone, :dest, "alpr_camera", "PENDING", 1, NOW())
        ');
        $ins->execute([
            ':plate' => $cleanPlate,
            ':name'  => $driverName,
            ':phone' => $driverPhone,
            ':dest'  => $destination,
        ]);
        $requestId = (int)$db->lastInsertId();

        // Save detection record
        $insDet = $db->prepare('
            INSERT INTO alpr_detections (camera, plate_raw, plate_clean, confidence, snapshot_path, matched_request_id, created_at)
            VALUES ("entrance", :plate, :clean, 0.95, :snap, :req_id, NOW())
        ');
        $insDet->execute([
            ':plate'  => $detectedPlate,
            ':clean'  => $cleanPlate,
            ':snap'   => $snapshotRelPath,
            ':req_id' => $requestId,
        ]);

        // Dispatch new_request event
        fire_event('new_request', [
            'id'              => $requestId,
            'request_id'      => $requestId,
            'plate_number'    => $cleanPlate,
            'formatted_plate' => $displayPlate,
            'driver_name'     => $driverName,
            'driver_phone'    => PhoneHelper::formatDisplay($driverPhone),
            'destination'     => $destination,
            'source'          => 'alpr_camera',
            'alpr_verified'   => true,
            'snapshot_url'    => $snapshotRelPath,
            'wait_seconds'    => 0,
            'wait_text'       => 'Just now',
            'category'        => $regVehicle['category'] ?? 'regular',
            'category_notes'  => $regVehicle['notes'] ?? '',
        ]);
    }

    echo json_encode([
        'ok'            => true,
        'camera_online' => $cameraOnline,
        'plate_found'   => true,
        'data'          => [
            'request_id'      => $requestId,
            'plate_number'    => $cleanPlate,
            'formatted_plate' => $displayPlate,
            'driver_name'     => $driverName,
            'driver_phone'    => $driverPhone,
            'destination'     => $destination,
            'snapshot_url'    => $snapshotRelPath,
        ],
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Database error: ' . $e->getMessage(),
    ]);
}
