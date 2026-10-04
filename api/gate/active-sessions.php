<?php
/**
 * Mombasa Mall Basement Parking - Active Sessions Endpoint ("Cars Inside Now")
 * Returns currently parked vehicles sorted by longest parked, with overstay flags.
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

    // Get overstay threshold from settings
    $setStmt = $db->query('SELECT value FROM system_settings WHERE `key` = "overstay_hours" LIMIT 1');
    $overstayHours = (int)($setStmt->fetchColumn() ?: 8);

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
        $hrs  = floor($dwellMins / 60);
        $mins = $dwellMins % 60;
        $dwellStr = $hrs > 0 ? "{$hrs}h {$mins}m" : "{$mins}m";

        $warningThresholdMins = ($overstayHours - 2) * 60;
        $criticalThresholdMins = $overstayHours * 60;

        $overstayLevel = 'normal'; // 'normal', 'warning', 'critical'
        if ($dwellMins >= $criticalThresholdMins) {
            $overstayLevel = 'critical';
            $overstayCount++;
        } elseif ($dwellMins >= $warningThresholdMins) {
            $overstayLevel = 'warning';
        }

        $sessions[] = [
            'id'               => (int)$row['id'],
            'ticket_id'        => $row['ticket_id'],
            'plate_number'     => $row['plate_number'],
            'formatted_plate'  => PlateHelper::format($row['plate_number']),
            'driver_name'      => $row['driver_name'],
            'driver_phone'     => PhoneHelper::formatDisplay($row['driver_phone']),
            'raw_phone'        => $row['driver_phone'],
            'destination'      => $row['destination'],
            'destination_icon' => $row['destination_icon'] ?? 'store',
            'entry_time'       => $row['entry_time'],
            'dwell_minutes'    => $dwellMins,
            'dwell_string'     => $dwellStr,
            'overstay_level'   => $overstayLevel,
            'entry_snapshot'   => $row['entry_snapshot'],
            'entry_method'     => $row['entry_method'],
            'guard_name'       => $row['guard_name'] ?? 'Gate Guard',
            'category'         => $row['vehicle_category'] ?? 'regular',
        ];
    }

    echo json_encode([
        'ok'   => true,
        'data' => [
            'total_active'   => count($sessions),
            'overstay_count' => $overstayCount,
            'overstay_hours' => $overstayHours,
            'sessions'       => $sessions,
        ],
    ]);
} catch (Throwable $e) {
    error_log('ACTIVE SESSIONS ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to load active sessions.']);
}
