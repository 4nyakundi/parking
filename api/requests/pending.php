<?php
/**
 * Mombasa Mall Basement Parking - Pending Queue Endpoint
 * Returns all unhandled visitor sign-ins sorted newest first.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

// Guards, supervisors, admins can view pending queue
require_auth(true);

try {
    $db = get_db();

    // Query pending requests and join with registered_vehicles to highlight VIP / staff / blacklisted
    $stmt = $db->query('
        SELECT 
            v.id,
            v.plate_number,
            v.driver_name,
            v.driver_phone,
            v.destination,
            v.source,
            v.status,
            v.alpr_verified,
            v.created_at,
            TIMESTAMPDIFF(SECOND, v.created_at, NOW()) AS wait_seconds,
            d.icon AS destination_icon,
            rv.category AS vehicle_category,
            rv.notes AS category_notes
        FROM visitors v
        LEFT JOIN destinations d ON d.name = v.destination
        LEFT JOIN registered_vehicles rv ON rv.plate_number = v.plate_number AND rv.is_active = 1
        WHERE v.status = "PENDING"
        ORDER BY v.id DESC
    ');

    $rows = $stmt->fetchAll();
    $data = [];

    foreach ($rows as $row) {
        $waitSeconds = (int)$row['wait_seconds'];
        $waitMinutes = floor($waitSeconds / 60);

        $waitText = $waitMinutes < 1 ? 'Just now' : ($waitMinutes == 1 ? '1 min ago' : "{$waitMinutes} mins ago");

        $data[] = [
            'id'               => (int)$row['id'],
            'plate_number'     => $row['plate_number'],
            'formatted_plate'  => PlateHelper::format($row['plate_number']),
            'driver_name'      => $row['driver_name'],
            'driver_phone'     => PhoneHelper::formatDisplay($row['driver_phone']),
            'raw_phone'        => $row['driver_phone'],
            'destination'      => $row['destination'],
            'destination_icon' => $row['destination_icon'] ?? 'store',
            'source'           => $row['source'],
            'alpr_verified'    => (bool)$row['alpr_verified'],
            'wait_seconds'     => $waitSeconds,
            'wait_text'        => $waitText,
            'category'         => $row['vehicle_category'] ?? 'regular',
            'category_notes'   => $row['category_notes'] ?? '',
            'created_at'       => $row['created_at'],
        ];
    }

    echo json_encode(['ok' => true, 'data' => $data]);
} catch (Throwable $e) {
    error_log('PENDING QUEUE ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to load pending queue.']);
}
