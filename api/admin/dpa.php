<?php
/**
 * Mombasa Mall Basement Parking - Kenya Data Protection Act 2019 Compliance API
 * Provides data subject lookup and export rights.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

$user = require_role(['supervisor', 'admin'], true);

$query = trim((string)($_GET['q'] ?? ''));

if (empty($query)) {
    echo json_encode(['ok' => false, 'error' => 'Please provide a plate number or mobile phone number to search.']);
    exit;
}

$cleanPlate = PlateHelper::clean($query);
$normPhone  = PhoneHelper::normalize($query);

try {
    $db = get_db();

    // 1. Visitor sign-in requests
    $visStmt = $db->prepare('
        SELECT id, plate_number, driver_name, driver_phone, destination, source, status, created_at, ip_address
        FROM visitors 
        WHERE plate_number = :plate OR driver_phone = :phone
        ORDER BY id DESC
    ');
    $visStmt->execute([':plate' => $cleanPlate, ':phone' => $normPhone]);
    $visitors = $visStmt->fetchAll();

    // 2. Parking sessions
    $sessStmt = $db->prepare('
        SELECT ticket_id, plate_number, driver_name, driver_phone, destination, entry_time, exit_time, status, duration_minutes, entry_method
        FROM parking_sessions
        WHERE plate_number = :plate OR driver_phone = :phone
        ORDER BY id DESC
    ');
    $sessStmt->execute([':plate' => $cleanPlate, ':phone' => $normPhone]);
    $sessions = $sessStmt->fetchAll();

    // 3. Registered vehicle records
    $regStmt = $db->prepare('
        SELECT * FROM registered_vehicles 
        WHERE plate_number = :plate OR phone = :phone
    ');
    $regStmt->execute([':plate' => $cleanPlate, ':phone' => $normPhone]);
    $regVehicles = $regStmt->fetchAll();

    audit_log($user['id'], 'DPA_DATA_SUBJECT_LOOKUP', 'dpa', $query, null, [
        'results_found' => count($sessions) + count($visitors),
    ]);

    echo json_encode([
        'ok'   => true,
        'data' => [
            'query'               => $query,
            'dpa_statement'       => 'Exported pursuant to Section 26 of the Kenya Data Protection Act 2019 (Right of Access).',
            'visitor_requests'    => $visitors,
            'parking_sessions'    => $sessions,
            'registered_vehicles' => $regVehicles,
        ],
    ]);
} catch (Throwable $e) {
    error_log('DPA LOOKUP ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
