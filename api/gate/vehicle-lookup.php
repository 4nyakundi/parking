<?php
/**
 * Mombasa Mall Basement Parking - Vehicle Data Bank Lookup API
 * Returns client profile, visit history count, and frequent client status (>= 3 visits).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/databank.php';

// Requires logged in guard/supervisor/admin
$user = require_auth(true);

$rawPlate = trim((string)($_GET['plate'] ?? $_POST['plate'] ?? ''));
if (empty($rawPlate)) {
    echo json_encode(['ok' => false, 'error' => 'Plate number is required.']);
    exit;
}

$cleanPlate = PlateHelper::clean($rawPlate);
if (!PlateHelper::validate($cleanPlate)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid vehicle plate number format.']);
    exit;
}

$profile = DataBank::lookup($cleanPlate);

if (!$profile) {
    echo json_encode([
        'ok'   => true,
        'data' => [
            'plate_number'    => $cleanPlate,
            'formatted_plate' => PlateHelper::format($cleanPlate),
            'visit_count'     => 0,
            'is_frequent'     => false,
            'skip_details'    => false,
            'driver_name'     => '',
            'driver_phone'    => '',
            'default_destination' => 'G-01 Naivas Supermarket',
        ]
    ]);
    exit;
}

$profile['skip_details'] = (bool)$profile['is_frequent'];

echo json_encode([
    'ok'   => true,
    'data' => $profile
]);
