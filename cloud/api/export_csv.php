<?php
/**
 * Mombasa Mall Basement Parking - Remote Cloud Mirror CSV Export
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['cloud_user_id'])) {
    http_response_code(403);
    die('Unauthorized cloud session.');
}

$cfg = require __DIR__ . '/../config.cloud.php';
$dbCfg = $cfg['db'];
$dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['dbname']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$filter = $_GET['status'] ?? 'all';
$query = "SELECT * FROM cloud_parking_sessions";
$params = [];

if ($filter === 'active') {
    $query .= " WHERE status = 'ACTIVE'";
} elseif ($filter === 'completed') {
    $query .= " WHERE status = 'COMPLETED'";
} elseif ($filter === 'today') {
    $query .= " WHERE DATE(entry_time) = CURDATE()";
}
$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'mombasa_mall_cloud_parking_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
// UTF-8 BOM for Microsoft Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'Ticket ID',
    'Plate Number',
    'Driver Name',
    'Driver Phone',
    'Destination',
    'Entry Time',
    'Exit Time',
    'Stay Duration (Mins)',
    'Status',
    'Entry Method',
    'Notes',
    'Synced At',
]);

foreach ($rows as $r) {
    fputcsv($output, [
        $r['ticket_id'],
        $r['plate_number'],
        $r['driver_name'],
        $r['driver_phone'],
        $r['destination'],
        $r['entry_time'],
        $r['exit_time'] ?? 'Inside',
        $r['duration_minutes'] ?? '',
        $r['status'],
        $r['entry_method'] ?? 'alpr',
        $r['notes'] ?? '',
        $r['synced_at'],
    ]);
}

fclose($output);
exit;
