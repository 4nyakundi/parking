<?php
/**
 * Mombasa Mall Basement Parking - Find Session Endpoint
 * Searches active sessions by license plate, phone number, or ticket ID (Lost Ticket support).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

require_auth(true);

$query = trim((string)($_GET['q'] ?? $_GET['query'] ?? ''));

if (empty($query)) {
    echo json_encode(['ok' => false, 'error' => 'Please provide a search term (Plate, Phone, or Ticket ID).']);
    exit;
}

$cleanPlate = PlateHelper::clean($query);
$normPhone  = PhoneHelper::normalize($query);

try {
    $db = get_db();

    // Fetch matching ACTIVE sessions first
    $stmt = $db->prepare('
        SELECT 
            s.*,
            u.full_name AS guard_name,
            TIMESTAMPDIFF(MINUTE, s.entry_time, NOW()) AS dwell_minutes
        FROM parking_sessions s
        LEFT JOIN users u ON u.id = s.approved_by
        WHERE (
            s.plate_number LIKE :exactPlate
            OR s.plate_number LIKE :partPlate
            OR s.ticket_id LIKE :ticket
            OR s.driver_phone LIKE :phone
            OR s.driver_name LIKE :name
        )
        ORDER BY (s.status = "ACTIVE") DESC, s.id DESC
        LIMIT 10
    ');

    $stmt->execute([
        ':exactPlate' => $cleanPlate,
        ':partPlate'  => "%{$cleanPlate}%",
        ':ticket'     => "%{$query}%",
        ':phone'      => "%{$normPhone}%",
        ':name'       => "%{$query}%",
    ]);

    $rows = $stmt->fetchAll();
    $results = [];

    $config = require __DIR__ . '/../../config/config.php';
    $overstayHours = (int)($config['app']['overstay_hours'] ?? 8);

    foreach ($rows as $row) {
        $dwellMins = (int)$row['dwell_minutes'];
        $hours = floor($dwellMins / 60);
        $mins  = $dwellMins % 60;
        $dwellStr = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins} mins";

        $isOverstay = ($dwellMins >= ($overstayHours * 60)) && ($row['status'] === 'ACTIVE');

        $results[] = [
            'id'               => (int)$row['id'],
            'ticket_id'        => $row['ticket_id'],
            'plate_number'     => $row['plate_number'],
            'formatted_plate'  => PlateHelper::format($row['plate_number']),
            'driver_name'      => $row['driver_name'],
            'driver_phone'     => PhoneHelper::formatDisplay($row['driver_phone']),
            'raw_phone'        => $row['driver_phone'],
            'destination'      => $row['destination'],
            'entry_time'       => $row['entry_time'],
            'exit_time'        => $row['exit_time'],
            'status'           => $row['status'],
            'dwell_minutes'    => $dwellMins,
            'dwell_string'     => $dwellStr,
            'is_overstay'      => $isOverstay,
            'entry_snapshot'   => $row['entry_snapshot'],
            'exit_snapshot'    => $row['exit_snapshot'],
            'guard_name'       => $row['guard_name'] ?? 'Gate Guard',
            'print_status'     => $row['print_status'],
            'whatsapp_status'  => $row['whatsapp_status'],
        ];
    }

    echo json_encode(['ok' => true, 'data' => $results]);
} catch (Throwable $e) {
    error_log('FIND SESSION ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database search error.']);
}
