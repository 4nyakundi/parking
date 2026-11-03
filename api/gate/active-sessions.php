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

    require_once __DIR__ . '/../../includes/guard_snapshot.php';
    echo json_encode(['ok' => true, 'data' => guard_roster_snapshot($db, $query)]);
} catch (Throwable $e) {
    error_log('ACTIVE SESSIONS ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to load active sessions.']);
}
