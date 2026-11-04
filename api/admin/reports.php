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
require_once __DIR__ . '/../../includes/admin_reports.php';
require_once __DIR__ . '/../../includes/admin_session_filters.php';
header('Cache-Control: no-store');

try {
    $db = get_db();

    // -------------------------------------------------------------
    // EXPORT CSV
    // -------------------------------------------------------------
    if ($type === 'export_csv') {
        [$whereSql, $params] = admin_session_filters($_GET);
        $dateFrom = $_GET['date_from'] ?? 'all'; $dateTo = $_GET['date_to'] ?? 'all';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=parking_report_' . $dateFrom . '_to_' . $dateTo . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Ticket ID', 'Plate Number', 'Driver Name', 'Phone', 'Destination', 'Entry Time', 'Exit Time', 'Status', 'Duration (Mins)', 'Approved By', 'Cleared By', 'Notes']);

        $stmt = $db->prepare("
            SELECT s.*, u1.full_name AS approver, u2.full_name AS clearer
            FROM parking_sessions s
            LEFT JOIN users u1 ON u1.id = s.approved_by
            LEFT JOIN users u2 ON u2.id = s.cleared_by
            WHERE {$whereSql}
            ORDER BY s.id DESC
        ");
        $stmt->execute($params);

        while ($r = $stmt->fetch()) {
            $values = [
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
            ];
            fputcsv($output, array_map(fn($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $values));
        }
        fclose($output);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    [$dateFrom,$dateTo,$start,$end] = admin_report_range($_GET);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
    $report = admin_report($db,$start,$end);
    $db->commit();
    if ($type === 'summary') echo json_encode(['ok'=>true,'data'=>$report]);
    elseif ($type === 'shift') echo json_encode(['ok'=>true,'data'=>$report['shifts']]);
    elseif ($type === 'overstay') {
        $rows = $db->query("SELECT id,ticket_id,plate_number,driver_name,destination,entry_time,TIMESTAMPDIFF(MINUTE,entry_time,NOW()) dwell_minutes FROM parking_sessions WHERE status='ACTIVE' AND entry_time <= DATE_SUB(NOW(),INTERVAL 2 HOUR) ORDER BY entry_time")->fetchAll();
        echo json_encode(['ok'=>true,'data'=>['overstay_hours'=>2,'overstays'=>$rows]]);
    } else { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Unknown report type.']); }
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('ADMIN REPORT: '.$e->getMessage());
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>$e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to generate the report. Please retry.']);
}
