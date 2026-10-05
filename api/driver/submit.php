<?php
/**
 * Mombasa Mall Basement Parking - Public Driver Self Sign-In Endpoint
 * Rate-limited, honeypot protected, Kenyan plate & phone validated.
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
        'error' => 'Please enter a valid Kenyan number plate (e.g. KDA 123A, KMDA 123A, GK 123A).',
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

    // 5. Rate Limiting: Max 5 submissions per IP or Phone within 10 minutes
    $rateStmt = $db->prepare('
        SELECT COUNT(*) AS cnt 
        FROM visitors 
        WHERE (ip_address = :ip OR driver_phone = :phone)
          AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ');
    $rateStmt->execute([':ip' => $ip, ':phone' => $cleanPhone]);
    $recentCount = (int)$rateStmt->fetchColumn();

    if ($recentCount >= 5) {
        echo json_encode([
            'ok'    => false,
            'error' => 'Too many sign-in attempts. Please drive directly to the security guard for assistance.',
        ]);
        exit;
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

    // 7. Check if this vehicle already has a PENDING request waiting
    $pendingStmt = $db->prepare('
        SELECT id, created_at 
        FROM visitors 
        WHERE plate_number = :plate AND status = "PENDING"
          AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        LIMIT 1
    ');
    $pendingStmt->execute([':plate' => $cleanPlate]);
    $existingPending = $pendingStmt->fetch();

    if ($existingPending) {
        $createdTimestamp = !empty($existingPending['created_at']) ? strtotime($existingPending['created_at']) : time();
        $timeInFormatted = date('H:i:s • d/m/Y', $createdTimestamp);
        $expectedTimeOut = date('H:i:s • d/m/Y', $createdTimestamp + (120 * 60));

        echo json_encode([
            'ok'   => true,
            'data' => [
                'request_id'        => (int)$existingPending['id'],
                'plate_number'      => $cleanPlate,
                'formatted_plate'   => $displayPlate,
                'driver_name'       => $driverName,
                'driver_phone'      => PhoneHelper::formatDisplay($cleanPhone),
                'destination'       => $destination,
                'time_in'           => $timeInFormatted,
                'expected_time_out' => $expectedTimeOut,
                'parking_limit'     => '2 Hours (120 Mins) — Free',
                'already_queued'    => true,
                'message'           => 'Your sign-in is already waiting for the guard. Please drive to the gate.',
            ],
        ]);
        exit;
    }

    // 8. Check for recent ALPR entrance detection (within last 3 minutes)
    $alprStmt = $db->prepare('
        SELECT id 
        FROM alpr_detections 
        WHERE camera = "entrance" 
          AND plate_clean = :plate 
          AND created_at > DATE_SUB(NOW(), INTERVAL 3 MINUTE)
        ORDER BY id DESC 
        LIMIT 1
    ');
    $alprStmt->execute([':plate' => $cleanPlate]);
    $alprDetection = $alprStmt->fetch();
    $alprVerified = $alprDetection ? 1 : 0;

    // 9. Check registered vehicle category (VIP, Staff, Tenant, Blacklisted)
    $regStmt = $db->prepare('
        SELECT category, notes 
        FROM registered_vehicles 
        WHERE plate_number = :plate AND is_active = 1
        LIMIT 1
    ');
    $regStmt->execute([':plate' => $cleanPlate]);
    $regVehicle = $regStmt->fetch();
    $category = $regVehicle['category'] ?? 'regular';

    // 10. Insert new visitor request
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

    // Link ALPR detection if available
    if ($alprDetection) {
        $db->prepare('UPDATE alpr_detections SET matched_request_id = ? WHERE id = ?')
           ->execute([$requestId, $alprDetection['id']]);
    }

    // 11. Broadcast Realtime Event to Guard Tablet
    fire_event('new_request', [
        'request_id'      => $requestId,
        'plate_number'    => $cleanPlate,
        'formatted_plate' => $displayPlate,
        'driver_name'     => $driverName,
        'driver_phone'    => PhoneHelper::formatDisplay($cleanPhone),
        'destination'     => $destination,
        'source'          => 'self_signin',
        'alpr_verified'   => $alprVerified === 1,
        'category'        => $category,
        'category_note'   => $regVehicle['notes'] ?? '',
        'created_at'      => date('H:i:s'),
    ]);

    $timeInTimestamp = time();
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
            'message'           => 'Sign-in successful! Please drive forward to the guard booth to collect your parking ticket.',
        ],
    ]);
} catch (Throwable $e) {
    error_log('DRIVER SIGNIN SUBMIT ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while saving your sign-in. Please see the guard.']);
}
