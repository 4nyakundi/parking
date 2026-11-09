<?php
/**
 * Mombasa Mall Basement Parking - Cloud Synchronization Service
 * Handles instant and batch transmission of parking sessions to Cloud Mirror
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class CloudSyncService
{
    /**
     * Synchronize a specific session immediately upon creation or exit
     */
    public static function syncSession(int $sessionId): bool
    {
        try {
            $db = get_db();
            $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE id = ?');
            $stmt->execute([$sessionId]);
            $sess = $stmt->fetch();
            if (!$sess) return false;

            return self::transmitSessions([$sess]);
        } catch (Throwable $e) {
            error_log('CloudSyncService::syncSession Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Batch synchronize all pending unsynced sessions
     */
    public static function syncPendingBatch(int $limit = 50): array
    {
        try {
            $db = get_db();
            $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE synced_to_cloud = 0 ORDER BY id ASC LIMIT :limit');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $sessions = $stmt->fetchAll();

            if (empty($sessions)) {
                return ['ok' => true, 'synced' => 0, 'message' => 'Zero unsynced records found. Local and Cloud databases are in parity.'];
            }

            $success = self::transmitSessions($sessions);
            return [
                'ok'      => $success,
                'synced'  => $success ? count($sessions) : 0,
                'message' => $success ? ('Successfully synchronized ' . count($sessions) . ' session(s) to Cloud Mirror.') : 'Failed to synchronize batch to Cloud Mirror.',
            ];
        } catch (Throwable $e) {
            error_log('CloudSyncService::syncPendingBatch Error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Transmit sessions payload to Cloud Ingest endpoint or direct database upsert
     */
    private static function transmitSessions(array $sessions): bool
    {
        if (empty($sessions)) return true;

        $db = get_db();
        $config = require __DIR__ . '/../config/config.php';
        $cloudCfg = $config['cloud_sync'] ?? [];

        $endpoint   = $cloudCfg['endpoint'] ?? 'http://127.0.0.1/parking/cloud/api/ingest.php';
        $apiKey     = $cloudCfg['api_key'] ?? 'MOMBASA_CLOUD_SYNC_KEY_987654321';
        $hmacSecret = $cloudCfg['hmac_secret'] ?? 'SEC_HMAC_MOMBASA_MALL_CLOUD_SIGNING_SECRET_2026';

        $payload = [
            'timestamp' => time(),
            'edge_pc'   => 'Mombasa-Mall-Edge-01',
            'sessions'  => $sessions,
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', (string)$jsonPayload, $hmacSecret);

        // 1. Attempt HTTP POST to ingest endpoint
        $httpSuccess = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                "X-API-KEY: {$apiKey}",
                "X-SIGNATURE: {$signature}",
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $respJson = json_decode($response, true);
                if (!empty($respJson['ok'])) {
                    $httpSuccess = true;
                }
            }
        }

        // 2. Fallback direct local DB upsert if loopback HTTP was blocked
        if (!$httpSuccess) {
            try {
                $cloudDbCfg = require __DIR__ . '/../cloud/config.cloud.php';
                $cdb = $cloudDbCfg['db'];
                $dsn = "mysql:host={$cdb['host']};port={$cdb['port']};dbname={$cdb['dbname']};charset=utf8mb4";
                $cloudPdo = new PDO($dsn, $cdb['username'], $cdb['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                
                require_once __DIR__ . '/../cloud/schema_init.php';
                ensure_cloud_schema($cloudPdo);

                $upsertStmt = $cloudPdo->prepare('
                    INSERT INTO cloud_parking_sessions (
                        edge_session_id, ticket_id, plate_number, driver_name, driver_phone,
                        destination, entry_time, exit_time, status, duration_minutes,
                        entry_method, exit_method, notes, synced_at
                    ) VALUES (
                        :edge_id, :ticket_id, :plate, :driver, :phone,
                        :dest, :entry, :exit, :status, :dur,
                        :emeth, :xmeth, :notes, NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        destination      = VALUES(destination),
                        exit_time        = VALUES(exit_time),
                        status           = VALUES(status),
                        duration_minutes = VALUES(duration_minutes),
                        exit_method      = VALUES(exit_method),
                        notes            = VALUES(notes),
                        synced_at        = NOW()
                ');

                $cloudPdo->beginTransaction();
                foreach ($sessions as $s) {
                    $dur = (isset($s['duration_minutes']) && (int)$s['duration_minutes'] > 0)
                        ? (int)$s['duration_minutes']
                        : (!empty($s['exit_time']) ? (int)round((strtotime($s['exit_time']) - strtotime($s['entry_time'])) / 60) : null);
                    $upsertStmt->execute([
                        ':edge_id'   => $s['id'],
                        ':ticket_id' => $s['ticket_id'],
                        ':plate'     => $s['plate_number'],
                        ':driver'    => $s['driver_name'],
                        ':phone'     => $s['driver_phone'] ?? '',
                        ':dest'      => $s['destination'] ?? '',
                        ':entry'     => $s['entry_time'],
                        ':exit'      => $s['exit_time'] ?? null,
                        ':status'    => $s['status'],
                        ':dur'       => $dur,
                        ':emeth'     => $s['entry_method'] ?? 'alpr',
                        ':xmeth'     => $s['exit_method'] ?? null,
                        ':notes'     => $s['notes'] ?? null,
                    ]);
                }

                // Record Heartbeat
                $hbStmt = $cloudPdo->prepare('
                    INSERT INTO cloud_edge_heartbeats (edge_pc, records_count, ip_address, last_synced_at)
                    VALUES (:pc, :cnt, :ip, NOW())
                ');
                $hbStmt->execute([
                    ':pc'  => 'Mombasa-Mall-Edge-01',
                    ':cnt' => count($sessions),
                    ':ip'  => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
                ]);

                $cloudPdo->commit();
                $httpSuccess = true;
            } catch (Throwable $dbErr) {
                error_log('Local cloud fallback error: ' . $dbErr->getMessage());
            }
        }

        if ($httpSuccess) {
            $ids = array_column($sessions, 'id');
            if (!empty($ids)) {
                $inList = implode(',', array_map('intval', $ids));
                $db->exec("UPDATE parking_sessions SET synced_to_cloud = 1, synced_at = NOW() WHERE id IN ({$inList})");
            }
            // Update device health
            $db->prepare('
                INSERT INTO device_health (device, status, last_checked_at, message)
                VALUES ("cloud_sync", "OK", NOW(), :msg)
                ON DUPLICATE KEY UPDATE status = "OK", last_checked_at = NOW(), message = VALUES(message)
            ')->execute([':msg' => 'Synchronized ' . count($sessions) . ' session(s) successfully']);
            return true;
        }

        return false;
    }
}
