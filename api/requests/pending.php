<?php
/**
 * Mombasa Mall Basement Parking - Pending Queue Endpoint
 * Returns all unhandled visitor sign-ins sorted newest first.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

// Guards, supervisors, admins can view pending queue
require_auth(true);

try {
    $db = get_db();

    // Auto-expire abandoned visitor requests older than 30 minutes to keep the guard intake queue clean & fast
    try {
        $db->query("UPDATE visitors SET status = 'EXPIRED' WHERE status = 'PENDING' AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    } catch (Throwable) {}

    require_once __DIR__ . '/../../includes/guard_snapshot.php';
    $data = guard_pending_snapshot($db);
    echo json_encode(['ok' => true, 'data' => $data]);
} catch (Throwable $e) {
    error_log('PENDING QUEUE ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to load pending queue.']);
}
