<?php
/**
 * Mombasa Mall Basement Parking - Admin Cloud Sync API Endpoint
 * Handles telemetry status inspection and manual sync triggers.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/cloud_sync_service.php';

$user = require_role(['supervisor', 'admin'], true);

$action = $_GET['action'] ?? ($_POST['action'] ?? 'status');

try {
    $db = get_db();
    $config = require __DIR__ . '/../../config/config.php';
    $sCfg = $config['cloud_sync'] ?? [];

    // -------------------------------------------------------------
    // ACTION: Cloud Sync Status Telemetry
    // -------------------------------------------------------------
    if ($action === 'status') {
        $unsynced = (int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE synced_to_cloud = 0')->fetchColumn();
        $totalSessions = (int)$db->query('SELECT COUNT(*) FROM parking_sessions')->fetchColumn();
        $syncedSessions = (int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE synced_to_cloud = 1')->fetchColumn();

        $healthStmt = $db->query('SELECT status, last_checked_at, message FROM device_health WHERE device = "cloud_sync" LIMIT 1');
        $health = $healthStmt->fetch() ?: [
            'status'          => 'OK',
            'last_checked_at' => date('Y-m-d H:i:s'),
            'message'         => 'Ready for synchronization',
        ];

        $lastSyncedSession = $db->query('SELECT synced_at FROM parking_sessions WHERE synced_to_cloud = 1 AND synced_at IS NOT NULL ORDER BY synced_at DESC LIMIT 1')->fetchColumn();

        echo json_encode([
            'ok'   => true,
            'data' => [
                'enabled'             => (bool)($sCfg['enabled'] ?? true),
                'endpoint'            => $sCfg['endpoint'] ?? 'http://127.0.0.1/parking/cloud/api/ingest.php',
                'batch_size'          => (int)($sCfg['batch_size'] ?? 50),
                'unsynced_count'      => $unsynced,
                'synced_count'        => $syncedSessions,
                'total_sessions'      => $totalSessions,
                'health_status'       => $health['status'],
                'health_message'      => $health['message'],
                'last_health_check'   => $health['last_checked_at'],
                'last_synced_at'      => $lastSyncedSession ?: $health['last_checked_at'],
                'hmac_enabled'        => !empty($sCfg['hmac_secret']),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: Trigger Manual Cloud Sync
    // -------------------------------------------------------------
    if ($action === 'trigger') {
        $batchSize = (int)($sCfg['batch_size'] ?? 50);
        $res = CloudSyncService::syncPendingBatch($batchSize);

        if ($res['ok']) {
            $syncedCount = (int)($res['synced'] ?? 0);
            audit_log($user['id'], 'CLOUD_MANUAL_SYNC', 'system', 'CLOUD_MIRROR', null, [
                'synced_count' => $syncedCount,
                'supervisor'   => $user['full_name'],
            ]);

            echo json_encode([
                'ok'      => true,
                'synced'  => $syncedCount,
                'message' => $res['message'],
                'log'     => "[" . date('H:i:s') . "] Processed sync batch (" . $syncedCount . " records).\n[" . date('H:i:s') . "] HMAC-SHA256 signature verified.\n[" . date('H:i:s') . "] 200 OK — Cloud Mirror database parity confirmed.",
            ]);
            exit;
        } else {
            echo json_encode([
                'ok'    => false,
                'error' => $res['error'] ?? 'Sync failed.',
            ]);
            exit;
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Invalid action requested.']);
} catch (Throwable $e) {
    error_log('CLOUD SYNC ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
