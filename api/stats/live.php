<?php
/**
 * Mombasa Mall Basement Parking - Live Statistics Endpoint
 * Real-time calculation of capacity, entries, exits, dwell times, and longest parked.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/plate_helper.php';

try {
    $db = get_db();

    // Capacity & Overstay settings
    $setStmt = $db->query('SELECT `key`, value FROM system_settings WHERE `key` IN ("capacity", "overstay_hours")');
    $settings = $setStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $capacity = isset($settings['capacity']) ? (int)$settings['capacity'] : 60;
    $overstayHours = isset($settings['overstay_hours']) ? (int)$settings['overstay_hours'] : 8;

    // 1. Live Occupancy
    $occStmt = $db->query('SELECT COUNT(*) FROM parking_sessions WHERE status = "ACTIVE"');
    $occupied = (int)$occStmt->fetchColumn();
    $available = max(0, $capacity - $occupied);

    // 2. Today's Entries & Exits
    $entryStmt = $db->query('SELECT COUNT(*) FROM parking_sessions WHERE DATE(entry_time) = CURDATE()');
    $todayEntries = (int)$entryStmt->fetchColumn();

    $exitStmt = $db->query('SELECT COUNT(*) FROM parking_sessions WHERE status = "COMPLETED" AND DATE(exit_time) = CURDATE()');
    $todayExits = (int)$exitStmt->fetchColumn();

    // 3. Overstay count
    $overStmt = $db->prepare('
        SELECT COUNT(*) 
        FROM parking_sessions 
        WHERE status = "ACTIVE" 
          AND TIMESTAMPDIFF(HOUR, entry_time, NOW()) >= :hrs
    ');
    $overStmt->execute([':hrs' => $overstayHours]);
    $overstayCount = (int)$overStmt->fetchColumn();

    // 4. Longest parked active vehicle
    $longStmt = $db->query('
        SELECT 
            id, ticket_id, plate_number, driver_name, destination, entry_time,
            TIMESTAMPDIFF(MINUTE, entry_time, NOW()) AS dwell_minutes
        FROM parking_sessions
        WHERE status = "ACTIVE"
        ORDER BY entry_time ASC
        LIMIT 1
    ');
    $longest = $longStmt->fetch();
    $longestParked = null;

    if ($longest) {
        $dwellMins = (int)$longest['dwell_minutes'];
        $hrs = floor($dwellMins / 60);
        $mins = $dwellMins % 60;
        $longestParked = [
            'ticket_id'       => $longest['ticket_id'],
            'plate_number'    => $longest['plate_number'],
            'formatted_plate' => PlateHelper::format($longest['plate_number']),
            'driver_name'     => $longest['driver_name'],
            'destination'     => $longest['destination'],
            'entry_time'      => $longest['entry_time'],
            'dwell_minutes'   => $dwellMins,
            'dwell_string'    => "{$hrs}h {$mins}m",
        ];
    }

    // 5. Average duration today (in minutes)
    $avgStmt = $db->query('
        SELECT AVG(duration_minutes) AS avg_dur
        FROM parking_sessions
        WHERE status = "COMPLETED" AND DATE(exit_time) = CURDATE()
    ');
    $avgMins = round((float)($avgStmt->fetchColumn() ?: 0));

    echo json_encode([
        'ok'   => true,
        'data' => [
            'capacity'        => $capacity,
            'occupied'        => $occupied,
            'available'       => $available,
            'today_entries'   => $todayEntries,
            'today_exits'     => $todayExits,
            'overstay_count'  => $overstayCount,
            'overstay_hours'  => $overstayHours,
            'avg_stay_mins'   => $avgMins,
            'longest_parked'  => $longestParked,
            'server_time'     => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    error_log('LIVE STATS ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to calculate live statistics.']);
}
