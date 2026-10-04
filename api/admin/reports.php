<?php
/**
 * Mombasa Mall Basement Parking - Reports & Analytics Engine
 * Generates shift handovers, destination breakdowns, peak hours, and CSV exports.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

$user = require_role(['supervisor', 'admin'], true);

$type     = $_GET['type'] ?? 'summary'; // 'summary', 'shift', 'destinations', 'overstay', 'export_csv'
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');

try {
    $db = get_db();

    // -------------------------------------------------------------
    // EXPORT CSV
    // -------------------------------------------------------------
    if ($type === 'export_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=parking_report_' . $dateFrom . '_to_' . $dateTo . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Ticket ID', 'Plate Number', 'Driver Name', 'Phone', 'Destination', 'Entry Time', 'Exit Time', 'Status', 'Duration (Mins)', 'Approved By', 'Cleared By', 'Notes']);

        $stmt = $db->prepare('
            SELECT s.*, u1.full_name AS approver, u2.full_name AS clearer
            FROM parking_sessions s
            LEFT JOIN users u1 ON u1.id = s.approved_by
            LEFT JOIN users u2 ON u2.id = s.cleared_by
            WHERE s.entry_time >= :dfrom AND s.entry_time <= :dto
            ORDER BY s.id DESC
        ');
        $stmt->execute([
            ':dfrom' => "{$dateFrom} 00:00:00",
            ':dto'   => "{$dateTo} 23:59:59",
        ]);

        while ($r = $stmt->fetch()) {
            fputcsv($output, [
                $r['ticket_id'],
                PlateHelper::format($r['plate_number']),
                $r['driver_name'],
                PhoneHelper::formatDisplay($r['driver_phone']),
                $r['destination'],
                $r['entry_time'],
                $r['exit_time'] ?? '',
                $r['status'],
                $r['duration_minutes'] ?? '',
                $r['approver'] ?? '',
                $r['clearer'] ?? '',
                $r['notes'] ?? '',
            ]);
        }
        fclose($output);
        exit;
    }

    // Default JSON headers
    header('Content-Type: application/json; charset=utf-8');

    // -------------------------------------------------------------
    // 1. SUMMARY REPORT
    // -------------------------------------------------------------
    if ($type === 'summary') {
        $stmt = $db->prepare('
            SELECT 
                COUNT(*) AS total_entries,
                SUM(CASE WHEN status = "COMPLETED" THEN 1 ELSE 0 END) AS total_exits,
                SUM(CASE WHEN status = "ACTIVE" THEN 1 ELSE 0 END) AS active_inside,
                AVG(CASE WHEN status = "COMPLETED" THEN duration_minutes ELSE NULL END) AS avg_duration_mins
            FROM parking_sessions
            WHERE entry_time >= :dfrom AND entry_time <= :dto
        ');
        $stmt->execute([':dfrom' => "{$dateFrom} 00:00:00", ':dto' => "{$dateTo} 23:59:59"]);
        $summary = $stmt->fetch();

        // Peak Hour
        $peakStmt = $db->prepare('
            SELECT HOUR(entry_time) AS hr, COUNT(*) AS count
            FROM parking_sessions
            WHERE entry_time >= :dfrom AND entry_time <= :dto
            GROUP BY HOUR(entry_time)
            ORDER BY count DESC
            LIMIT 1
        ');
        $peakStmt->execute([':dfrom' => "{$dateFrom} 00:00:00", ':dto' => "{$dateTo} 23:59:59"]);
        $peak = $peakStmt->fetch();
        $peakHour = $peak ? sprintf('%02d:00 - %02d:00 (%d cars)', $peak['hr'], $peak['hr'] + 1, $peak['count']) : 'N/A';

        // Destination Breakdown
        $destStmt = $db->prepare('
            SELECT destination, COUNT(*) AS count
            FROM parking_sessions
            WHERE entry_time >= :dfrom AND entry_time <= :dto
            GROUP BY destination
            ORDER BY count DESC
        ');
        $destStmt->execute([':dfrom' => "{$dateFrom} 00:00:00", ':dto' => "{$dateTo} 23:59:59"]);
        $destinations = $destStmt->fetchAll();

        // Hourly Trend
        $hourStmt = $db->prepare('
            SELECT HOUR(entry_time) AS hr, COUNT(*) AS count
            FROM parking_sessions
            WHERE entry_time >= :dfrom AND entry_time <= :dto
            GROUP BY HOUR(entry_time)
            ORDER BY hr ASC
        ');
        $hourStmt->execute([':dfrom' => "{$dateFrom} 00:00:00", ':dto' => "{$dateTo} 23:59:59"]);
        $hourly = $hourStmt->fetchAll();

        echo json_encode([
            'ok'   => true,
            'data' => [
                'summary' => [
                    'total_entries'     => (int)$summary['total_entries'],
                    'total_exits'       => (int)$summary['total_exits'],
                    'active_inside'     => (int)$summary['active_inside'],
                    'avg_duration_mins' => round((float)($summary['avg_duration_mins'] ?? 0)),
                    'peak_hour'         => $peakHour,
                ],
                'destinations' => $destinations,
                'hourly'       => $hourly,
            ],
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 2. GUARD SHIFT HANDOVER REPORT
    // -------------------------------------------------------------
    if ($type === 'shift') {
        $stmt = $db->prepare('
            SELECT 
                u.id AS guard_id,
                u.full_name AS guard_name,
                (SELECT COUNT(*) FROM parking_sessions WHERE approved_by = u.id AND entry_time >= :dfrom1 AND entry_time <= :dto1) AS entries_approved,
                (SELECT COUNT(*) FROM parking_sessions WHERE cleared_by = u.id AND exit_time >= :dfrom2 AND exit_time <= :dto2) AS exits_cleared,
                (SELECT COUNT(*) FROM visitors WHERE handled_by = u.id AND status = "REJECTED" AND created_at >= :dfrom3 AND created_at <= :dto3) AS rejections
            FROM users u
            WHERE u.role IN ("guard", "supervisor")
            ORDER BY entries_approved DESC
        ');
        $stmt->execute([
            ':dfrom1' => "{$dateFrom} 00:00:00", ':dto1' => "{$dateTo} 23:59:59",
            ':dfrom2' => "{$dateFrom} 00:00:00", ':dto2' => "{$dateTo} 23:59:59",
            ':dfrom3' => "{$dateFrom} 00:00:00", ':dto3' => "{$dateTo} 23:59:59",
        ]);

        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // -------------------------------------------------------------
    // 3. OVERSTAY REPORT (> 8 Hours)
    // -------------------------------------------------------------
    if ($type === 'overstay') {
        $setStmt = $db->query('SELECT value FROM system_settings WHERE `key` = "overstay_hours" LIMIT 1');
        $overstayHours = (int)($setStmt->fetchColumn() ?: 8);

        $stmt = $db->prepare('
            SELECT 
                s.ticket_id, s.plate_number, s.driver_name, s.driver_phone, s.destination, s.entry_time,
                TIMESTAMPDIFF(HOUR, s.entry_time, NOW()) AS dwell_hours,
                TIMESTAMPDIFF(MINUTE, s.entry_time, NOW()) AS dwell_minutes
            FROM parking_sessions s
            WHERE s.status = "ACTIVE" AND TIMESTAMPDIFF(HOUR, s.entry_time, NOW()) >= :hrs
            ORDER BY s.entry_time ASC
        ');
        $stmt->execute([':hrs' => $overstayHours]);
        $rows = $stmt->fetchAll();

        $overstays = [];
        foreach ($rows as $r) {
            $overstays[] = [
                'ticket_id'       => $r['ticket_id'],
                'plate_number'    => $r['plate_number'],
                'formatted_plate' => PlateHelper::format($r['plate_number']),
                'driver_name'     => $r['driver_name'],
                'driver_phone'    => PhoneHelper::formatDisplay($r['driver_phone']),
                'destination'     => $r['destination'],
                'entry_time'      => $r['entry_time'],
                'dwell_hours'     => (int)$r['dwell_hours'],
                'dwell_minutes'   => (int)$r['dwell_minutes'],
            ];
        }

        echo json_encode(['ok' => true, 'data' => ['overstay_hours' => $overstayHours, 'overstays' => $overstays]]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown report type.']);
} catch (Throwable $e) {
    error_log('REPORTS ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
