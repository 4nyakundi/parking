<?php
/**
 * Mombasa Mall Basement Parking - Request Action Endpoint (Accept/Reject/Dismiss/Clear Stale)
 * Handles vehicle queue management: approval, rejection, single release/dismissal, and batch clearing of stale intake records.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = strtolower(trim((string)($input['action'] ?? 'accept')));

if ($action === 'reject') {
    require __DIR__ . '/../gate/reject.php';
    exit;
}

if ($action === 'dismiss' || $action === 'release') {
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/audit.php';
    require_once __DIR__ . '/../../includes/events.php';
    require_once __DIR__ . '/../../includes/plate_helper.php';

    $user = require_auth(true);
    $requestId = isset($input['request_id']) ? (int)$input['request_id'] : 0;
    $reason = trim((string)($input['reason'] ?? 'Released by guard from intake queue'));

    if ($requestId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Valid request_id is required.']);
        exit;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT * FROM visitors WHERE id = ?');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            echo json_encode(['ok' => false, 'error' => 'Pending request not found.']);
            exit;
        }

        $db->prepare('
            UPDATE visitors 
            SET status = "DISMISSED", reject_reason = :reason, handled_by = :guard 
            WHERE id = :id
        ')->execute([
            ':reason' => $reason,
            ':guard'  => $user['id'] ?? 1,
            ':id'     => $requestId,
        ]);

        audit_log($user['id'] ?? 1, 'DISMISS_REQUEST', 'visitors', (string)$requestId, [
            'plate' => $req['plate_number'],
        ], [
            'reason'     => $reason,
            'handled_by' => $user['full_name'] ?? 'Guard',
        ]);

        fire_event('request_dismissed', [
            'request_id'   => $requestId,
            'plate_number' => $req['plate_number'],
            'reason'       => $reason,
            'handled_by'   => $user['full_name'] ?? 'Guard',
        ]);

        echo json_encode([
            'ok'      => true,
            'message' => 'Vehicle was released from the intake queue.',
            'request_id' => $requestId,
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('DISMISS REQUEST ERROR: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Failed to release request.']);
        exit;
    }
}

if ($action === 'clear_stale' || $action === 'release_stale') {
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/audit.php';
    require_once __DIR__ . '/../../includes/events.php';

    $user = require_auth(true);
    $olderThanMins = isset($input['minutes']) ? max(5, (int)$input['minutes']) : 15;

    try {
        $db = get_db();
        $stmt = $db->prepare('
            UPDATE visitors 
            SET status = "EXPIRED", handled_by = :guard, reject_reason = "Expired by queue release"
            WHERE status = "PENDING" AND created_at < DATE_SUB(NOW(), INTERVAL :mins MINUTE)
        ');
        $stmt->execute([
            ':guard' => $user['id'] ?? 1,
            ':mins'  => $olderThanMins,
        ]);
        $cleared = $stmt->rowCount();

        audit_log($user['id'] ?? 1, 'RELEASE_STALE_QUEUE', 'visitors', '0', [], [
            'cleared_count' => $cleared,
            'older_than_minutes' => $olderThanMins,
            'handled_by' => $user['full_name'] ?? 'Guard',
        ]);

        fire_event('queue_cleared', [
            'cleared_count' => $cleared,
            'handled_by'    => $user['full_name'] ?? 'Guard',
        ]);

        echo json_encode([
            'ok'            => true,
            'cleared_count' => $cleared,
            'message'       => "Released {$cleared} stale vehicle(s) older than {$olderThanMins} minutes.",
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('CLEAR STALE ERROR: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Failed to release stale records.']);
        exit;
    }
}

// Default to accept / approve
require __DIR__ . '/../gate/approve-session.php';
