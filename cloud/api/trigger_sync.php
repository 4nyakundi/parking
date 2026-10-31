<?php
/**
 * Mombasa Mall Basement Parking - Remote Trigger Sync API
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['cloud_user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized cloud session.']);
    exit;
}

require_once __DIR__ . '/../../includes/cloud_sync_service.php';

try {
    $result = CloudSyncService::syncPendingBatch(100);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Sync failed: ' . $e->getMessage(),
    ]);
}
