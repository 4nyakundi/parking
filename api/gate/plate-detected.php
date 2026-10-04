<?php
/**
 * Mombasa Mall Basement Parking - ALPR Ingestion Endpoint
 * Authenticated via X-API-Key from Python ALPR Worker.
 * Matches entrance requests or exit candidates in real-time.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';
require_once __DIR__ . '/../../includes/events.php';

$config = require __DIR__ . '/../../config/config.php';
$expectedApiKey = $config['alpr']['api_key'] ?? '';

// Check API Key
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? null;
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];

if ($providedKey === null && isset($jsonInput['api_key'])) {
    $providedKey = $jsonInput['api_key'];
}

if (empty($providedKey) || !hash_equals($expectedApiKey, (string)$providedKey)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized machine access: Invalid ALPR API Key.']);
    exit;
}

$camera     = strtolower(trim((string)($jsonInput['camera'] ?? $_POST['camera'] ?? 'entrance')));
$rawPlate   = trim((string)($jsonInput['plate'] ?? $_POST['plate'] ?? ''));
$confidence = (float)($jsonInput['confidence'] ?? $_POST['confidence'] ?? 0.0);
$imageBase64= $jsonInput['image_base64'] ?? $_POST['image_base64'] ?? null;

if (!in_array($camera, ['entrance', 'exit'], true) || empty($rawPlate)) {
    echo json_encode(['ok' => false, 'error' => 'Missing camera ("entrance" or "exit") or plate value.']);
    exit;
}

$cleanPlate   = PlateHelper::clean($rawPlate);
$displayPlate = PlateHelper::format($cleanPlate);

// Snapshot storage path: storage/snapshots/YYYY/MM/DD/
$relSnapshotPath = null;
if (!empty($imageBase64)) {
    $dateFolder = date('Y/m/d');
    $fullDir = __DIR__ . '/../../storage/snapshots/' . $dateFolder;
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0777, true);
    }

    $fileName = sprintf('cam_%s_%s_%s.jpg', $camera, $cleanPlate, date('His'));
    $filePath = $fullDir . '/' . $fileName;

    $binaryData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64));
    if ($binaryData !== false) {
        file_put_contents($filePath, $binaryData);
        $relSnapshotPath = 'storage/snapshots/' . $dateFolder . '/' . $fileName;
    }
}

try {
    $db = get_db();

    $matchedRequestId = null;
    $matchedSessionId = null;

    if ($camera === 'entrance') {
        // Find newest PENDING request matching this plate
        $reqStmt = $db->prepare('
            SELECT id, driver_name, driver_phone, destination, created_at 
            FROM visitors 
            WHERE plate_number = :plate AND status = "PENDING"
            ORDER BY id DESC 
            LIMIT 1
        ');
        $reqStmt->execute([':plate' => $cleanPlate]);
        $pendingReq = $reqStmt->fetch();

        if ($pendingReq) {
            $matchedRequestId = (int)$pendingReq['id'];
            $db->prepare('UPDATE visitors SET alpr_verified = 1 WHERE id = ?')->execute([$matchedRequestId]);

            // Fire event that pending request was verified by camera
            fire_event('plate_verified_entrance', [
                'request_id'      => $matchedRequestId,
                'plate_number'    => $cleanPlate,
                'formatted_plate' => $displayPlate,
                'driver_name'     => $pendingReq['driver_name'],
                'destination'     => $pendingReq['destination'],
                'confidence'      => $confidence,
                'snapshot_url'    => $relSnapshotPath,
            ]);
        } else {
            // Check if vehicle is already actively inside
            $actStmt = $db->prepare('SELECT id, ticket_id, entry_time FROM parking_sessions WHERE plate_number = :plate AND status = "ACTIVE" LIMIT 1');
            $actStmt->execute([':plate' => $cleanPlate]);
            $activeInside = $actStmt->fetch();

            if ($activeInside) {
                // Vehicle is already parked inside
                fire_event('alpr_banner_entrance', [
                    'camera'          => 'entrance',
                    'plate_number'    => $cleanPlate,
                    'formatted_plate' => $displayPlate,
                    'confidence'      => $confidence,
                    'snapshot_url'    => $relSnapshotPath,
                    'time'            => date('H:i:s'),
                    'is_duplicate'    => true,
                    'ticket_id'       => $activeInside['ticket_id'],
                ]);
            } else {
                // Check if vehicle is registered (VIP / Staff / Blacklisted)
                $regStmt = $db->prepare('SELECT owner_name, phone, category, notes FROM registered_vehicles WHERE plate_number = :plate AND is_active = 1 LIMIT 1');
                $regStmt->execute([':plate' => $cleanPlate]);
                $regVehicle = $regStmt->fetch();

                $driverName = !empty($regVehicle['owner_name']) ? $regVehicle['owner_name'] : 'Visitor';
                $driverPhone = !empty($regVehicle['phone']) ? $regVehicle['phone'] : '';
                $category = $regVehicle['category'] ?? 'regular';
                $categoryNotes = $regVehicle['notes'] ?? '';

                // Insert into visitors table as PENDING so it appears on Guard Dashboard immediately
                $insVis = $db->prepare('
                    INSERT INTO visitors (plate_number, driver_name, driver_phone, destination, source, status, alpr_verified, created_at)
                    VALUES (:plate, :driver, :phone, "Mombasa Mall", "alpr_camera", "PENDING", 1, NOW())
                ');
                $insVis->execute([
                    ':plate'  => $cleanPlate,
                    ':driver' => $driverName,
                    ':phone'  => $driverPhone,
                ]);
                $matchedRequestId = (int)$db->lastInsertId();

                // Fire new_request event so guard tablet chimes and displays the card immediately
                fire_event('new_request', [
                    'id'              => $matchedRequestId,
                    'request_id'      => $matchedRequestId,
                    'plate_number'    => $cleanPlate,
                    'formatted_plate' => $displayPlate,
                    'driver_name'     => $driverName,
                    'driver_phone'    => PhoneHelper::formatDisplay($driverPhone),
                    'destination'     => 'Mombasa Mall',
                    'source'          => 'alpr_camera',
                    'alpr_verified'   => true,
                    'snapshot_url'    => $relSnapshotPath,
                    'wait_seconds'    => 0,
                    'wait_text'       => 'Just now',
                    'category'        => $category,
                    'category_notes'  => $categoryNotes,
                ]);

                // Also fire camera banner
                fire_event('alpr_banner_entrance', [
                    'camera'          => 'entrance',
                    'plate_number'    => $cleanPlate,
                    'formatted_plate' => $displayPlate,
                    'confidence'      => $confidence,
                    'snapshot_url'    => $relSnapshotPath,
                    'time'            => date('H:i:s'),
                ]);
            }
        }
    } else {
        // Exit camera: Find ACTIVE session for this vehicle
        $sessStmt = $db->prepare('
            SELECT id, ticket_id, driver_name, driver_phone, destination, entry_time,
                   TIMESTAMPDIFF(MINUTE, entry_time, NOW()) AS duration_minutes
            FROM parking_sessions 
            WHERE plate_number = :plate AND status = "ACTIVE"
            LIMIT 1
        ');
        $sessStmt->execute([':plate' => $cleanPlate]);
        $activeSess = $sessStmt->fetch();

        if ($activeSess) {
            $matchedSessionId = (int)$activeSess['id'];

            // Update exit snapshot on the session
            if ($relSnapshotPath) {
                $db->prepare('UPDATE parking_sessions SET exit_snapshot = ? WHERE id = ?')
                   ->execute([$relSnapshotPath, $matchedSessionId]);
            }

            fire_event('exit_candidate', [
                'session_id'       => $matchedSessionId,
                'ticket_id'        => $activeSess['ticket_id'],
                'plate_number'     => $cleanPlate,
                'formatted_plate'  => $displayPlate,
                'driver_name'      => $activeSess['driver_name'],
                'driver_phone'     => PhoneHelper::formatDisplay($activeSess['driver_phone']),
                'destination'      => $activeSess['destination'],
                'entry_time'       => $activeSess['entry_time'],
                'duration_minutes' => (int)$activeSess['duration_minutes'],
                'snapshot_url'     => $relSnapshotPath,
                'time'             => date('H:i:s'),
            ]);
        } else {
            // Exit detected with no active session
            fire_event('exit_unmatched', [
                'plate_number'    => $cleanPlate,
                'formatted_plate' => $displayPlate,
                'confidence'      => $confidence,
                'snapshot_url'    => $relSnapshotPath,
                'time'            => date('H:i:s'),
            ]);
        }
    }

    // Save detection record
    $ins = $db->prepare('
        INSERT INTO alpr_detections (camera, plate_raw, plate_clean, confidence, snapshot_path, matched_request_id, matched_session_id, created_at)
        VALUES (:camera, :raw, :clean, :conf, :path, :req_id, :sess_id, NOW())
    ');
    $ins->execute([
        ':camera'  => $camera,
        ':raw'     => $rawPlate,
        ':clean'   => $cleanPlate,
        ':conf'    => $confidence,
        ':path'    => $relSnapshotPath,
        ':req_id'  => $matchedRequestId,
        ':sess_id' => $matchedSessionId,
    ]);
    $detId = (int)$db->lastInsertId();

    echo json_encode([
        'ok'   => true,
        'data' => [
            'detection_id'       => $detId,
            'camera'             => $camera,
            'plate_number'       => $cleanPlate,
            'formatted_plate'    => $displayPlate,
            'matched_request_id' => $matchedRequestId,
            'matched_session_id' => $matchedSessionId,
        ],
    ]);
} catch (Throwable $e) {
    error_log('ALPR DETECTION ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error while saving ALPR detection: ' . $e->getMessage()]);
}
