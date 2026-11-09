<?php
/** Shared read-only guard snapshots. Camera processing is deliberately separate. */
declare(strict_types=1);
require_once __DIR__ . '/plate_helper.php';
require_once __DIR__ . '/phone_helper.php';
function guard_pending_snapshot(PDO $db): array
{
    // Older installations do not have the optional Data Bank profile columns.
    $columns = array_column($db->query('SHOW COLUMNS FROM registered_vehicles')->fetchAll(), 'Field');
    $visitCount = in_array('visit_count', $columns, true) ? 'rv.visit_count' : '0';
    $frequent = in_array('is_frequent', $columns, true) ? 'rv.is_frequent' : '0';
    $defaultDestination = in_array('default_destination', $columns, true) ? 'rv.default_destination' : 'NULL';
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
            rv.notes AS category_notes,
            ' . $visitCount . ' AS visit_count,
            ' . $frequent . ' AS is_frequent,
            ' . $defaultDestination . ' AS default_destination,
            (SELECT snapshot_path FROM alpr_detections WHERE plate_clean = v.plate_number AND camera = "entrance" ORDER BY id DESC LIMIT 1) AS snapshot_url
        FROM visitors v
        LEFT JOIN destinations d ON d.name = v.destination
        LEFT JOIN registered_vehicles rv ON rv.plate_number = v.plate_number AND rv.is_active = 1
        WHERE v.status = "PENDING" AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY v.id DESC
        LIMIT 100
    ');

    $rows = $stmt->fetchAll();
    $data = [];

    foreach ($rows as $row) {
        $waitSeconds = (int)$row['wait_seconds'];
        $waitMinutes = floor($waitSeconds / 60);

        $waitText = $waitMinutes < 1 ? 'Just now' : ($waitMinutes == 1 ? '1 min ago' : "{$waitMinutes} mins ago");

        $createdTs = strtotime($row['created_at']);
        $isToday = (date('Y-m-d', $createdTs) === date('Y-m-d'));
        $timeDisplay = date('h:i A', $createdTs);
        $entryDisplay = $isToday ? $timeDisplay : date('d M, h:i A', $createdTs);

        $data[] = [
            'id'                 => (int)$row['id'],
            'plate_number'       => $row['plate_number'],
            'formatted_plate'    => PlateHelper::format($row['plate_number']),
            'driver_name'        => $row['driver_name'],
            'driver_phone'       => PhoneHelper::formatDisplay($row['driver_phone']),
            'raw_phone'          => $row['driver_phone'],
            'destination'        => $row['destination'],
            'destination_icon'   => $row['destination_icon'] ?? 'store',
            'source'             => $row['source'],
            'alpr_verified'      => (bool)$row['alpr_verified'],
            'snapshot_url'       => $row['snapshot_url'] ?? null,
            'wait_seconds'       => $waitSeconds,
            'wait_text'          => $waitText,
            'category'           => $row['vehicle_category'] ?? 'regular',
            'category_notes'     => $row['category_notes'] ?? '',
            'is_frequent'        => ((int)($row['visit_count'] ?? 0) >= 3 || (int)($row['is_frequent'] ?? 0) === 1),
            'visit_count'        => (int)($row['visit_count'] ?? 0),
            'skip_details'       => ((int)($row['visit_count'] ?? 0) >= 3 || (int)($row['is_frequent'] ?? 0) === 1),
            'default_destination'=> $row['default_destination'] ?? '',
            'created_at'         => $row['created_at'],
            'entry_time'         => $row['created_at'],
            'entry_time_display' => $entryDisplay,
            'vehicle_type'       => PlateHelper::detectType($row['plate_number']),
        ];
    }

    return visit_store_decorate($db,$data,'visitor');
}
require_once __DIR__.'/visit_destinations.php';
function guard_roster_snapshot(PDO $db, string $query = ''): array
{
    $sql = '
        SELECT 
            s.id,
            s.ticket_id,
            s.plate_number,
            s.driver_name,
            s.driver_phone,
            s.destination,
            s.entry_time,
            e.approved_until,
            s.entry_snapshot,
            s.entry_method,
            s.print_status,
            s.whatsapp_status,
            u.full_name AS guard_name,
            TIMESTAMPDIFF(MINUTE, s.entry_time, NOW()) AS dwell_minutes,
            TIMESTAMPDIFF(SECOND, s.entry_time, NOW()) AS dwell_seconds,
            d.icon AS destination_icon,
            rv.category AS vehicle_category,
            EXISTS(SELECT 1 FROM parking_actions clamp WHERE clamp.session_id=s.id AND clamp.action_type="clamped" AND clamp.resolved_at IS NULL) is_clamped,
            EXISTS(SELECT 1 FROM parking_actions incident WHERE incident.session_id=s.id AND incident.action_type="incident" AND incident.resolved_at IS NULL) has_incident
        FROM parking_sessions s
        LEFT JOIN parking_extensions e ON e.session_id=s.id
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

    $sql .= ' ORDER BY s.entry_time DESC, s.id DESC'; // Newest arrivals first, longest parked last

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = visit_store_decorate($db,$stmt->fetchAll());

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
        $expectedExitTs = max($entryTs + ($freeLimitMins * 60), !empty($row['approved_until']) ? strtotime($row['approved_until']) : 0);
        $freeLimitMins = (int)(($expectedExitTs-$entryTs)/60);
        $expectedExitTime = date('Y-m-d H:i:s', $expectedExitTs);
        $expectedExitDisplay = date('h:i A', $expectedExitTs);
        $isToday = (date('Y-m-d', $entryTs) === date('Y-m-d'));
        $entryTimeDisplay = $isToday ? date('h:i:s A', $entryTs) : date('d M, h:i A', $entryTs);

        $remainingMins = $freeLimitMins - $dwellMins;
        $isOverstay = ($dwellMins >= $freeLimitMins);
        $overdueMins = max(0, $dwellMins - $freeLimitMins);

        $warningThresholdMins = $freeLimitMins - 30; // 1 hour 30 mins

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
            'destinations'          => $row['destinations'] ?? [$row['destination']],
            'approved_until'        => $row['approved_until'] ?? null,
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
            'print_status'          => $row['print_status'],
            'is_clamped'            => (bool)$row['is_clamped'],
            'has_incident'          => (bool)$row['has_incident'],
            'is_blacklisted'        => ($row['vehicle_category']??'')==='blacklisted',
            'category'              => $row['vehicle_category'] ?? 'regular',
        ];
    }

    return ['total_active' => count($sessions), 'overstay_count' => $overstayCount, 'overstay_hours' => 2, 'sessions' => $sessions];
}
