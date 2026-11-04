<?php
/**
 * Mombasa Mall Basement Parking - Hourly Traffic Distribution Endpoint
 * Returns today's entries and exits grouped by hour for Chart.js.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_auth(true); session_write_close();
header('Cache-Control: no-store');

try {
    $db = get_db();

    // Query entries by hour today
    $entryStmt = $db->query('
        SELECT HOUR(entry_time) AS hr, COUNT(*) AS count
        FROM parking_sessions
        WHERE status IN ("ACTIVE","COMPLETED") AND entry_time >= CURDATE() AND entry_time < DATE_ADD(CURDATE(),INTERVAL 1 DAY)
        GROUP BY HOUR(entry_time)
    ');
    $entriesMap = $entryStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Query exits by hour today
    $exitStmt = $db->query('
        SELECT HOUR(exit_time) AS hr, COUNT(*) AS count
        FROM parking_sessions
        WHERE status = "COMPLETED" AND DATE(exit_time) = CURDATE()
        GROUP BY HOUR(exit_time)
    ');
    $exitsMap = $exitStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $hours = [];
    $entries = [];
    $exits = [];

    // Mall operating hours 06:00 to 23:00
    for ($h = 0; $h <= 23; $h++) {
        $label = sprintf('%02d:00', $h);
        $hours[] = $label;
        $entries[] = (int)($entriesMap[$h] ?? 0);
        $exits[]   = (int)($exitsMap[$h] ?? 0);
    }

    echo json_encode([
        'ok'   => true,
        'data' => [
            'labels'  => $hours, 'hours' => $hours,
            'entries' => $entries,
            'exits'   => $exits,
        ],
    ]);
} catch (Throwable $e) {
    error_log('HOURLY STATS ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to calculate hourly statistics.']);
}
