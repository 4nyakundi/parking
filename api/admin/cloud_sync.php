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
require_once __DIR__ . '/../../includes/admin_actions.php';
admin_require_mutation($action, ['trigger']);

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
        $totalVehicles = (int)$db->query('SELECT COUNT(*) FROM registered_vehicles')->fetchColumn();

        $healthStmt = $db->query('SELECT status, last_checked_at, message, TIMESTAMPDIFF(SECOND,last_checked_at,NOW()) age_seconds FROM device_health WHERE device = "cloud_sync" LIMIT 1');
        $health = $healthStmt->fetch() ?: [
            'status' => 'UNKNOWN', 'last_checked_at' => null, 'message' => 'No synchronization report received',
        ];

        $intervalMinutes = max(1, (int)($sCfg['interval_minutes'] ?? 20));
        if (isset($health['age_seconds']) && (int)$health['age_seconds'] > ($intervalMinutes * 60 + 120)) { $health['status']='WARNING'; $health['message']='Synchronization report is stale'; }
        $lastSyncedSession = $db->query('SELECT synced_at FROM parking_sessions WHERE synced_to_cloud = 1 AND synced_at IS NOT NULL ORDER BY synced_at DESC LIMIT 1')->fetchColumn();
        $lastSyncedAt = CloudSyncService::lastSuccessfulSyncAt() ?: ($lastSyncedSession ?: null);

        echo json_encode([
            'ok'   => true,
            'data' => [
                'enabled'             => (bool)($sCfg['enabled'] ?? true),
                'endpoint'            => $sCfg['endpoint'] ?? 'http://127.0.0.1/parking/cloud/api/ingest.php',
                'batch_size'          => (int)($sCfg['batch_size'] ?? 50),
                'interval_minutes'    => $intervalMinutes,
                'unsynced_count'      => $unsynced,
                'synced_count'        => $syncedSessions,
                'total_sessions'      => $totalSessions,
                'total_vehicles'      => $totalVehicles,
                'health_status'       => $health['status'],
                'health_message'      => $health['message'],
                'last_health_check'   => $health['last_checked_at'],
                'last_synced_at' => $lastSyncedAt,
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
                'session_count' => (int)($res['session_count'] ?? 0),
                'vehicle_count' => (int)($res['vehicle_count'] ?? 0),
                'supervisor'   => $user['full_name'],
            ]);

            $sessionCount = (int)($res['session_count'] ?? 0);
            $vehicleCount = (int)($res['vehicle_count'] ?? 0);
            echo json_encode([
                'ok'      => true,
                'synced'  => $syncedCount,
                'session_count' => $sessionCount,
                'vehicle_count' => $vehicleCount,
                'message' => $res['message'],
                'log'     => "[" . date('H:i:s') . "] Processed segment: {$sessionCount} session(s), {$vehicleCount} vehicle profile(s).\n[" . date('H:i:s') . "] Batch request completed.",
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
