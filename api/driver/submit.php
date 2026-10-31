<?php
/**
 * Mombasa Mall Basement Parking - Public Driver Self Sign-In Endpoint
 * Rate-limited, honeypot protected, Kenyan plate & phone validated.
 * Fully synchronized with Edge MySQL database and Realtime Guard Dispatch.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';
require_once __DIR__ . '/../../includes/events.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

// 1. Bot Honeypot Check (field must be blank)
if (!empty($input['hp_website']) || !empty($input['website'])) {
    // Silently return success to bot without saving anything
    echo json_encode(['ok' => true, 'data' => ['message' => 'Sign-in registered.']]);
    exit;
}

$rawPlate       = trim((string)($input['plate_number'] ?? ''));
$rawName        = trim((string)($input['driver_name'] ?? ''));
$rawPhone       = trim((string)($input['driver_phone'] ?? ''));
$rawDestination = trim((string)($input['destination'] ?? ''));
$consent        = !empty($input['consent']);

// 2. Validate mandatory inputs
if (empty($rawPlate) || empty($rawName) || empty($rawPhone) || empty($rawDestination)) {
    echo json_encode(['ok' => false, 'error' => 'Please fill in all required fields (Plate, Name, Phone, and Destination).']);
    exit;
}

if (!$consent) {
    echo json_encode(['ok' => false, 'error' => 'Please accept the Data Protection Act consent to proceed.']);
    exit;
}

// 3. Plate Normalization & Validation
$cleanPlate = PlateHelper::clean($rawPlate);
if (!PlateHelper::validate($cleanPlate)) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Please enter a valid Kenyan number plate (e.g. KDA 123A, GK 123A, 47 CG 123A).',
    ]);
    exit;
}
$displayPlate = PlateHelper::format($cleanPlate);

// 4. Phone Normalization & Validation
$cleanPhone = PhoneHelper::normalize($rawPhone);
if (!PhoneHelper::validate($cleanPhone)) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Please enter a valid Kenyan mobile number (e.g. 0712 345 678 or 0110 123 456).',
    ]);
    exit;
}

$driverName = mb_substr(strip_tags($rawName), 0, 100);
$destination = mb_substr(strip_tags($rawDestination), 0, 100);

// Get Client IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
}

try {
    $db = get_db();

    // 5. Rate Limiting: Max 10 submissions per IP or Phone within 10 minutes
    $isLocalIp = ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || str_starts_with($ip, '172.'));
    if (!$isLocalIp) {
        $rateStmt = $db->prepare('
            SELECT COUNT(*) AS cnt 
            FROM visitors 
            WHERE (ip_address = :ip OR driver_phone = :phone)
              AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ');
        $rateStmt->execute([':ip' => $ip, ':phone' => $cleanPhone]);
        $recentCount = (int)$rateStmt->fetchColumn();

        if ($recentCount >= 10) {
            echo json_encode([
                'ok'    => false,
                'error' => 'Too many sign-in attempts. Please drive directly to the security guard for assistance.',
            ]);
            exit;
        }
    } else {
        $rateStmt = $db->prepare('
            SELECT COUNT(*) AS cnt 
            FROM visitors 
            WHERE driver_phone = :phone
              AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ');
        $rateStmt->execute([':phone' => $cleanPhone]);
        $recentCount = (int)$rateStmt->fetchColumn();

        if ($recentCount >= 8) {
            echo json_encode([
                'ok'    => false,
                'error' => 'Too many sign-in attempts for this phone number. Please see the security guard.',
            ]);
            exit;
        }
    }

    // 6. Check if vehicle currently has an ACTIVE session inside
    $activeStmt = $db->prepare('
        SELECT id, ticket_id, entry_time 
        FROM parking_sessions 
        WHERE plate_number = :plate AND status = "ACTIVE"
        LIMIT 1
    ');
    $activeStmt->execute([':plate' => $cleanPlate]);
    $existingSession = $activeStmt->fetch();

    if ($existingSession) {
        echo json_encode([
            'ok'    => false,
            'error' => "Vehicle {$displayPlate} is already recorded inside the parking lot (Ticket #{$existingSession['ticket_id']}). Please present your ticket to the exit guard.",
        ]);
        exit;
    }

    // 7. Check for recent ALPR entrance detection (within last 5 minutes)
    $alprStmt = $db->prepare('
        SELECT id 
        FROM alpr_detections 
        WHERE camera = "entrance" 
          AND plate_clean = :plate 
          AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY id DESC 
        LIMIT 1
    ');
    $alprStmt->execute([':plate' => $cleanPlate]);
    $alprDetection = $alprStmt->fetch();
    $alprVerified = $alprDetection ? 1 : 0;

    // 8. Check registered vehicle category (VIP, Staff, Tenant, Blacklisted)
    $regStmt = $db->prepare('
        SELECT category, notes 
        FROM registered_vehicles 
        WHERE plate_number = :plate AND is_active = 1
        LIMIT 1
    ');
    $regStmt->execute([':plate' => $cleanPlate]);
    $regVehicle = $regStmt->fetch();
    $category = $regVehicle['category'] ?? 'regular';

    // 9. Check if this vehicle already has a PENDING request in the queue (e.g. from camera detection)
    $pendingStmt = $db->prepare('
        SELECT id, created_at 
        FROM visitors 
        WHERE plate_number = :plate AND status = "PENDING"
          AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY id DESC
        LIMIT 1
    ');
    $pendingStmt->execute([':plate' => $cleanPlate]);
    $existingPending = $pendingStmt->fetch();

    $requestId = null;
    $timeInTimestamp = time();

    if ($existingPending) {
        // Vehicle was already queued by camera: UPDATE it with the driver details!
        $requestId = (int)$existingPending['id'];
        $upd = $db->prepare('
            UPDATE visitors 
            SET driver_name   = :name,
                driver_phone  = :phone,
                destination   = :dest,
                source        = "self_signin",
                alpr_verified = 1,
                ip_address    = :ip
            WHERE id = :id
        ');
        $upd->execute([
            ':name'  => $driverName,
            ':phone' => $cleanPhone,
            ':dest'  => $destination,
            ':ip'    => $ip,
            ':id'    => $requestId,
        ]);

        if (!empty($existingPending['created_at'])) {
            $timeInTimestamp = strtotime($existingPending['created_at']);
        }
    } else {
        // 10. Brand-new visitor request: INSERT into visitors table
        $ins = $db->prepare('
            INSERT INTO visitors (plate_number, driver_name, driver_phone, destination, source, status, alpr_verified, ip_address, created_at)
            VALUES (:plate, :name, :phone, :dest, "self_signin", "PENDING", :alpr, :ip, NOW())
        ');
        $ins->execute([
            ':plate' => $cleanPlate,
            ':name'  => $driverName,
            ':phone' => $cleanPhone,
            ':dest'  => $destination,
            ':alpr'  => $alprVerified,
            ':ip'    => $ip,
        ]);
        $requestId = (int)$db->lastInsertId();
    }

    // Link ALPR detection if available
    if ($alprDetection && $requestId) {
        $db->prepare('UPDATE alpr_detections SET matched_request_id = ? WHERE id = ?')
           ->execute([$requestId, $alprDetection['id']]);
    }

    // 11. Broadcast Realtime Events to Guard Tablet & Screen
    $eventPayload = [
        'request_id'      => $requestId,
        'plate_number'    => $cleanPlate,
        'formatted_plate' => $displayPlate,
        'driver_name'     => $driverName,
        'driver_phone'    => PhoneHelper::formatDisplay($cleanPhone),
        'raw_phone'       => $cleanPhone,
        'destination'     => $destination,
        'source'          => 'self_signin',
        'alpr_verified'   => true,
        'category'        => $category,
        'category_note'   => $regVehicle['notes'] ?? '',
        'created_at'      => date('H:i:s', $timeInTimestamp),
        'is_update'       => !empty($existingPending),
    ];

    fire_event('new_request', $eventPayload);
    fire_event('request_updated', $eventPayload);

    $timeInFormatted = date('H:i:s • d/m/Y', $timeInTimestamp);
    $expectedTimeOut = date('H:i:s • d/m/Y', $timeInTimestamp + (120 * 60)); // 2 hours = 120 mins

    echo json_encode([
        'ok'   => true,
        'data' => [
            'request_id'        => $requestId,
            'plate_number'      => $cleanPlate,
            'formatted_plate'   => $displayPlate,
            'driver_name'       => $driverName,
            'driver_phone'      => PhoneHelper::formatDisplay($cleanPhone),
            'destination'       => $destination,
            'time_in'           => $timeInFormatted,
            'expected_time_out' => $expectedTimeOut,
            'parking_limit'     => '2 Hours (120 Mins) — Free',
            'already_queued'    => !empty($existingPending),
            'message'           => 'Sign-in registered! Your details have been sent to the security guard for instant pass printing.',
        ],
    ]);
} catch (Throwable $e) {
    error_log('DRIVER SIGNIN SUBMIT ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while saving your sign-in. Please see the guard.']);
}
