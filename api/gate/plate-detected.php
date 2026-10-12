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
if (!PlateHelper::validate($cleanPlate)) {
    echo json_encode([
        'ok'    => false,
        'error' => "Plate '{$rawPlate}' does not match allowed Kenyan vehicle plate format (Cars and TukTuks starting with K, G, or County Gov only)."
    ]);
    exit;
}
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
                'request_id'         => $matchedRequestId,
                'plate_number'       => $cleanPlate,
                'formatted_plate'    => $displayPlate,
                'driver_name'        => $pendingReq['driver_name'],
                'destination'        => $pendingReq['destination'],
                'confidence'         => $confidence,
                'snapshot_url'       => $relSnapshotPath,
                'created_at'         => $pendingReq['created_at'],
                'entry_time'         => $pendingReq['created_at'],
                'entry_time_display' => date('h:i A', strtotime($pendingReq['created_at'])),
                'vehicle_type'       => PlateHelper::detectType($cleanPlate),
            ]);
        } else {
            // Check if vehicle is already actively inside
            $actStmt = $db->prepare('SELECT id, ticket_id, entry_time FROM parking_sessions WHERE plate_number = :plate AND status = "ACTIVE" LIMIT 1');
            $actStmt->execute([':plate' => $cleanPlate]);
            $activeInside = $actStmt->fetch();

            if ($activeInside) {
                // Vehicle is already parked inside
                fire_event('alpr_banner_entrance', [
                    'camera'             => 'entrance',
                    'plate_number'       => $cleanPlate,
                    'formatted_plate'    => $displayPlate,
                    'confidence'         => $confidence,
                    'snapshot_url'       => $relSnapshotPath,
                    'time'               => date('H:i:s'),
                    'entry_time_display' => date('h:i A', strtotime($activeInside['entry_time'])),
                    'is_duplicate'       => true,
                    'ticket_id'          => $activeInside['ticket_id'],
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

                $nowTime = date('Y-m-d H:i:s');
                $nowDisplay = date('h:i A');

                // Insert into visitors table as PENDING so it appears on Guard Dashboard immediately
                $insVis = $db->prepare('
                    INSERT INTO visitors (plate_number, driver_name, driver_phone, destination, source, status, alpr_verified, created_at)
                    VALUES (:plate, :driver, :phone, "Mombasa Mall", "alpr_camera", "PENDING", 1, :created_at)
                ');
                $insVis->execute([
                    ':plate'      => $cleanPlate,
                    ':driver'     => $driverName,
                    ':phone'      => $driverPhone,
                    ':created_at' => $nowTime,
                ]);
                $matchedRequestId = (int)$db->lastInsertId();

                // Fire new_request event so guard tablet chimes and displays the card with exact entry timestamp
                fire_event('new_request', [
                    'id'                 => $matchedRequestId,
                    'request_id'         => $matchedRequestId,
                    'plate_number'       => $cleanPlate,
                    'formatted_plate'    => $displayPlate,
                    'driver_name'        => $driverName,
                    'driver_phone'       => PhoneHelper::formatDisplay($driverPhone),
                    'destination'        => 'Mombasa Mall',
                    'source'             => 'alpr_camera',
                    'alpr_verified'      => true,
                    'snapshot_url'       => $relSnapshotPath,
                    'created_at'         => $nowTime,
                    'entry_time'         => $nowTime,
                    'entry_time_display' => $nowDisplay,
                    'wait_seconds'       => 0,
                    'wait_text'          => 'Just now',
                    'category'           => $category,
                    'category_notes'     => $categoryNotes,
                    'vehicle_type'       => PlateHelper::detectType($cleanPlate),
                ]);

                // Also fire camera banner
                fire_event('alpr_banner_entrance', [
                    'camera'             => 'entrance',
                    'plate_number'       => $cleanPlate,
                    'formatted_plate'    => $displayPlate,
                    'confidence'         => $confidence,
                    'snapshot_url'       => $relSnapshotPath,
                    'time'               => date('H:i:s'),
                    'entry_time_display' => $nowDisplay,
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
            ORDER BY id DESC LIMIT 1
        ');
        $sessStmt->execute([':plate' => $cleanPlate]);
        $activeSess = $sessStmt->fetch();

        if ($activeSess) {
            $matchedSessionId = (int)$activeSess['id'];
            $driverName = trim((string)$activeSess['driver_name']);
            $driverPhone = trim((string)$activeSess['driver_phone']);

            // Check if details were actually collected (not default 'Visitor' or empty phone)
            $hasDetails = (!empty($driverName) && $driverName !== 'Visitor' && !empty($driverPhone) && $driverPhone !== '254700000000');

            if ($hasDetails) {
                // Vehicle had complete data collected -> Standard exit candidate for guard
                if ($relSnapshotPath) {
                    $db->prepare('UPDATE parking_sessions SET exit_snapshot = ? WHERE id = ?')
                       ->execute([$relSnapshotPath, $matchedSessionId]);
                }

                $durMins = (int)$activeSess['duration_minutes'];
                $durHours = floor($durMins / 60);
                $remMins = $durMins % 60;
                $durationText = $durHours > 0 ? "{$durHours}h {$remMins}m" : "{$remMins} mins";

                fire_event('exit_candidate', [
                    'session_id'         => $matchedSessionId,
                    'ticket_id'          => $activeSess['ticket_id'],
                    'plate_number'       => $cleanPlate,
                    'formatted_plate'    => $displayPlate,
                    'driver_name'        => $activeSess['driver_name'],
                    'driver_phone'       => PhoneHelper::formatDisplay($activeSess['driver_phone']),
                    'destination'        => $activeSess['destination'],
                    'entry_time'         => $activeSess['entry_time'],
                    'entry_time_display' => date('h:i A', strtotime($activeSess['entry_time'])),
                    'duration_minutes'   => $durMins,
                    'duration_text'      => $durationText,
                    'snapshot_url'       => $relSnapshotPath,
                    'time'               => date('H:i:s'),
                ]);
            } else {
                // Vehicle had NO details collected -> Let it get out and inform the guard
                $durationMinutes = max(1, (int)$activeSess['duration_minutes']);
                $durHours = floor($durationMinutes / 60);
                $durMins  = $durationMinutes % 60;
                $durationText = $durHours > 0 ? "{$durHours}h {$durMins}m" : "{$durMins} mins";
                $entryTime = $activeSess['entry_time'];

                $db->prepare('
                    UPDATE parking_sessions 
                    SET status = "COMPLETED",
                        exit_time = NOW(),
                        duration_minutes = :dur,
                        exit_snapshot = :snap,
                        exit_method = "auto_exit_uncollected",
                        notes = "EXIT WITHOUT DETAILS: Vehicle left without collection of driver details."
                    WHERE id = :id
                ')->execute([
                    ':dur'  => $durationMinutes,
                    ':snap' => $relSnapshotPath,
                    ':id'   => $matchedSessionId,
                ]);

                // Expire any pending visitor request for this plate
                $db->prepare('UPDATE visitors SET status = "EXPIRED" WHERE plate_number = ? AND status = "PENDING"')
                   ->execute([$cleanPlate]);

                fire_event('exit_without_details', [
                    'session_id'         => $matchedSessionId,
                    'ticket_id'          => $activeSess['ticket_id'],
                    'plate_number'       => $cleanPlate,
                    'formatted_plate'    => $displayPlate,
                    'entry_time'         => $entryTime,
                    'entry_time_display' => date('h:i A', strtotime($entryTime)),
                    'exit_time'          => date('Y-m-d H:i:s'),
                    'exit_time_display'  => date('h:i A'),
                    'duration_minutes'   => $durationMinutes,
                    'duration_text'      => $durationText,
                    'snapshot_url'       => $relSnapshotPath,
                    'driver_name'        => $activeSess['driver_name'],
                    'message'            => "Vehicle {$displayPlate} exited without collection of details! Entered: " . date('h:i A', strtotime($entryTime)) . " | Exited: " . date('h:i A') . " ({$durationText}).",
                    'time'               => date('H:i:s'),
                ]);
            }
        } else {
            // Exit detected with NO active session -> Find entry timestamp from pending visitors or ALPR
            $visStmt = $db->prepare('
                SELECT id, destination, created_at 
                FROM visitors 
                WHERE plate_number = :plate AND status = "PENDING"
                ORDER BY id DESC LIMIT 1
            ');
            $visStmt->execute([':plate' => $cleanPlate]);
            $pendingVis = $visStmt->fetch();

            $entryTime = null;
            $destination = 'Mombasa Mall';

            if ($pendingVis) {
                $matchedRequestId = (int)$pendingVis['id'];
                $entryTime = $pendingVis['created_at'];
                $destination = $pendingVis['destination'] ?: 'Mombasa Mall';

                // Mark pending request as EXPIRED so it drops from the guard queue
                $db->prepare('UPDATE visitors SET status = "EXPIRED" WHERE id = ?')
                   ->execute([$matchedRequestId]);
            } else {
                // Check recent entrance detection
                $detStmt = $db->prepare('
                    SELECT created_at 
                    FROM alpr_detections 
                    WHERE camera = "entrance" AND plate_clean = :plate 
                    ORDER BY id DESC LIMIT 1
                ');
                $detStmt->execute([':plate' => $cleanPlate]);
                $entranceTime = $detStmt->fetchColumn();
                $entryTime = $entranceTime ?: date('Y-m-d H:i:s', time() - 300);
            }

            $entryTs = strtotime($entryTime);
            $durationMinutes = max(1, (int)round((time() - $entryTs) / 60));
            $durHours = floor($durationMinutes / 60);
            $durMins  = $durationMinutes % 60;
            $durationText = $durHours > 0 ? "{$durHours}h {$durMins}m" : "{$durMins} mins";
            $ticketId = sprintf('MM-%s-AUTO-%04d', date('Ymd'), rand(1000, 9999));

            // Create completed session
            $insSess = $db->prepare('
                INSERT INTO parking_sessions (
                    ticket_id, plate_number, driver_name, driver_phone, destination,
                    entry_time, exit_time, status, duration_minutes,
                    entry_snapshot, exit_snapshot, entry_method, exit_method, notes, synced_to_cloud, created_at
                ) VALUES (
                    :ticket_id, :plate, "Uncollected Visitor", "", :dest,
                    :entry_time, NOW(), "COMPLETED", :duration,
                    NULL, :exit_snapshot, "alpr", "auto_exit_uncollected", "AUTO-EXIT: Vehicle left without collection of details.", 0, NOW()
                )
            ');
            $insSess->execute([
                ':ticket_id'      => $ticketId,
                ':plate'          => $cleanPlate,
                ':dest'           => $destination,
                ':entry_time'     => $entryTime,
                ':duration'       => $durationMinutes,
                ':exit_snapshot'  => $relSnapshotPath,
            ]);
            $matchedSessionId = (int)$db->lastInsertId();

            // Broadcast realtime event: Inform the guard that car left without details
            fire_event('exit_without_details', [
                'session_id'         => $matchedSessionId,
                'ticket_id'          => $ticketId,
                'plate_number'       => $cleanPlate,
                'formatted_plate'    => $displayPlate,
                'entry_time'         => $entryTime,
                'entry_time_display' => date('h:i A', $entryTs),
                'exit_time'          => date('Y-m-d H:i:s'),
                'exit_time_display'  => date('h:i A'),
                'duration_minutes'   => $durationMinutes,
                'duration_text'      => $durationText,
                'snapshot_url'       => $relSnapshotPath,
                'message'            => "Vehicle {$displayPlate} exited without collection of details! Entered: " . date('h:i A', $entryTs) . " | Exited: " . date('h:i A') . " ({$durationText}).",
                'time'               => date('H:i:s'),
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
