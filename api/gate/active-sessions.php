<?php
/**
 * Mombasa Mall Basement Parking - Active Sessions Endpoint ("Cars Inside Now")
 * Returns currently parked vehicles sorted by longest parked, with overstay flags,
 * expected exit time, and elapsed stay calculations.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

require_auth(true);

$query = trim((string)($_GET['q'] ?? ''));

try {
    $db = get_db();

    // Get overstay threshold from settings (default 2 hours = 120 mins free mall parking)
    $setStmt = $db->query('SELECT value FROM system_settings WHERE `key` = "overstay_hours" LIMIT 1');
    $overstayHours = (int)($setStmt->fetchColumn() ?: 2);
    if ($overstayHours <= 0) $overstayHours = 2;

    $sql = '
        SELECT 
            s.id,
            s.ticket_id,
            s.plate_number,
            s.driver_name,
            s.driver_phone,
            s.destination,
            s.entry_time,
            s.entry_snapshot,
            s.entry_method,
            s.print_status,
            s.whatsapp_status,
            u.full_name AS guard_name,
            TIMESTAMPDIFF(MINUTE, s.entry_time, NOW()) AS dwell_minutes,
            TIMESTAMPDIFF(SECOND, s.entry_time, NOW()) AS dwell_seconds,
            d.icon AS destination_icon,
            rv.category AS vehicle_category
        FROM parking_sessions s
        LEFT JOIN users u ON u.id = s.approved_by
        LEFT JOIN destinations d ON d.name = s.destination
        LEFT JOIN registered_vehicles rv ON rv.plate_number = s.plate_number AND rv.is_active = 1
        WHERE s.status = "ACTIVE"
    ';

    $params = [];
    if (!empty($query)) {
        $clean = PlateHelper::clean($query);
        $sql .= ' AND (s.plate_number LIKE :exact OR s.plate_number LIKE :part OR s.ticket_id LIKE :ticket OR s.driver_name LIKE :name OR s.driver_phone LIKE :phone)';
        $params[':exact']  = $clean;
        $params[':part']   = "%{$clean}%";
        $params[':ticket'] = "%{$query}%";
        $params[':name']   = "%{$query}%";
        $params[':phone']  = "%{$query}%";
    }

    $sql .= ' ORDER BY s.entry_time ASC'; // Longest parked first

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $sessions = [];
    $overstayCount = 0;

    foreach ($rows as $row) {
        $dwellMins = (int)$row['dwell_minutes'];
        $dwellSecs = (int)($row['dwell_seconds'] ?? ($dwellMins * 60));
        $hrs  = floor($dwellMins / 60);
        $mins = $dwellMins % 60;
        $dwellStr = $hrs > 0 ? "{$hrs}h {$mins}m" : "{$mins}m";

        $entryTs = !empty($row['entry_time']) ? strtotime($row['entry_time']) : time();
        $freeLimitMins = 120; // 2 hours
        $expectedExitTs = $entryTs + ($freeLimitMins * 60);
        $expectedExitTime = date('Y-m-d H:i:s', $expectedExitTs);
        $expectedExitDisplay = date('h:i A', $expectedExitTs);
        $isToday = (date('Y-m-d', $entryTs) === date('Y-m-d'));
        $entryTimeDisplay = $isToday ? date('h:i:s A', $entryTs) : date('d M, h:i A', $entryTs);

        $remainingMins = $freeLimitMins - $dwellMins;
        $isOverstay = ($dwellMins >= $freeLimitMins);
        $overdueMins = max(0, $dwellMins - $freeLimitMins);

        $warningThresholdMins = 90; // 1 hour 30 mins

        $overstayLevel = 'normal'; // 'normal', 'warning', 'critical'
        if ($isOverstay) {
            $overstayLevel = 'critical';
            $overstayCount++;
        } elseif ($dwellMins >= $warningThresholdMins) {
            $overstayLevel = 'warning';
        }

        $sessions[] = [
            'id'                    => (int)$row['id'],
            'ticket_id'             => $row['ticket_id'],
            'plate_number'          => $row['plate_number'],
            'formatted_plate'       => PlateHelper::format($row['plate_number']),
            'driver_name'           => $row['driver_name'] ?: 'Visitor Driver',
            'driver_phone'          => PhoneHelper::formatDisplay($row['driver_phone']),
            'raw_phone'             => $row['driver_phone'],
            'destination'           => $row['destination'] ?: 'Mombasa Mall',
            'destination_icon'      => $row['destination_icon'] ?? 'store',
            'entry_time'            => $row['entry_time'],
            'entry_time_display'    => $entryTimeDisplay,
            'entry_timestamp'       => $entryTs,
            'expected_exit_time'    => $expectedExitTime,
            'expected_exit_display' => $expectedExitDisplay,
            'expected_exit_ts'      => $expectedExitTs,
            'dwell_minutes'         => $dwellMins,
            'dwell_seconds'         => $dwellSecs,
            'dwell_string'          => $dwellStr,
            'remaining_minutes'     => $remainingMins,
            'is_overstay'           => $isOverstay,
            'overdue_minutes'       => $overdueMins,
            'overstay_level'        => $overstayLevel,
            'entry_snapshot'        => $row['entry_snapshot'],
            'entry_method'          => $row['entry_method'],
            'guard_name'            => $row['guard_name'] ?? 'Gate Guard',
            'category'              => $row['vehicle_category'] ?? 'regular',
        ];
    }

    echo json_encode([
        'ok'   => true,
        'data' => [
            'total_active'   => count($sessions),
            'overstay_count' => $overstayCount,
            'overstay_hours' => 2,
            'sessions'       => $sessions,
        ],
    ]);
} catch (Throwable $e) {
    error_log('ACTIVE SESSIONS ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to load active sessions.']);
}
