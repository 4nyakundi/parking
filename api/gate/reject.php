<?php
/**
 * Mombasa Mall Basement Parking - Reject Pending Request Endpoint
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/plate_helper.php';

$user = require_auth(true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

$requestId = isset($input['request_id']) ? (int)$input['request_id'] : 0;
$reason    = trim((string)($input['reason'] ?? 'Declined by security guard'));

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
        SET status = "REJECTED", reject_reason = :reason, handled_by = :guard 
        WHERE id = :id
    ')->execute([
        ':reason' => $reason,
        ':guard'  => $user['id'],
        ':id'     => $requestId,
    ]);

    // Audit log
    audit_log($user['id'], 'REJECT_REQUEST', 'visitors', (string)$requestId, [
        'plate' => $req['plate_number'],
    ], [
        'reason'     => $reason,
        'handled_by' => $user['full_name'],
    ]);

    // Broadcast realtime event
    fire_event('request_rejected', [
        'request_id'   => $requestId,
        'plate_number' => $req['plate_number'],
        'reason'       => $reason,
        'handled_by'   => $user['full_name'],
    ]);

    echo json_encode([
        'ok'      => true,
        'message' => 'Driver request was rejected successfully.',
    ]);
} catch (Throwable $e) {
    error_log('REJECT REQUEST ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to reject request.']);
}
