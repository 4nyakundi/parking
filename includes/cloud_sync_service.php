<?php
/**
 * Mombasa Mall Basement Parking - Cloud Synchronization Service
 * Mirrors parking sessions and registered vehicle profiles in bounded batches.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class CloudSyncService
{
    private const STATE_TABLE = 'cloud_sync_state';

    public static function syncSession(int $sessionId): bool
    {
        try {
            $db = get_db();
            $stmt = $db->prepare('
                SELECT id, ticket_id, plate_number, driver_name, driver_phone, destination,
                       entry_time, exit_time, status, duration_minutes, entry_method, exit_method, notes
                FROM parking_sessions WHERE id = ?
            ');
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
            if (!$session) {
                return false;
            }

            return self::transmitRecords([$session], []);
        } catch (Throwable $e) {
            error_log('CloudSyncService::syncSession Error: ' . $e->getMessage());
            return false;
        }
    }

    public static function syncPendingBatch(int $limit = 50): array
    {
        if ($limit < 1 || $limit > 500) {
            return ['ok' => false, 'error' => 'Segment size must be between 1 and 500 records.'];
        }

        try {
            $config = require __DIR__ . '/../config/config.php';
            if (empty($config['cloud_sync']['enabled'])) {
                return ['ok' => false, 'error' => 'Cloud synchronization is disabled in the local configuration.'];
            }

            $db = get_db();
            self::ensureStateTable($db);

            $sessionLimit = (int)ceil($limit / 2);
            $sessionBackfill = self::getState($db, 'parking_session_backfill_complete', '0') !== '1';
            $sessionCursor = null;
            if ($sessionBackfill) {
                $sessionCursor = (int)self::getState($db, 'parking_session_cursor', '0');
                $sessions = self::fetchSessionBatch($db, $sessionCursor, $sessionLimit);
                if (!$sessions) {
                    self::setState($db, 'parking_session_backfill_complete', '1');
                    $sessionBackfill = false;
                }
            }
            if (!$sessionBackfill) {
                $sessions = self::fetchPendingSessions($db, $sessionLimit);
            }

            $vehicleLimit = max(0, $limit - count($sessions));
            $vehicles = $vehicleLimit > 0 ? self::nextVehicleBatch($db, $vehicleLimit) : [];
            if (!$sessions && !$vehicles) {
                return [
                    'ok' => true,
                    'synced' => 0,
                    'session_count' => 0,
                    'vehicle_count' => 0,
                    'message' => 'No unsynced sessions or vehicle profiles were found in this segment.',
                ];
            }

            if (!self::transmitRecords($sessions, $vehicles)) {
                return ['ok' => false, 'error' => 'Failed to synchronize the segment to Cloud Mirror. Local records remain unchanged.'];
            }

            if ($vehicles) {
                self::setState($db, 'registered_vehicle_cursor', (string)end($vehicles)['id']);
            }
            if ($sessionBackfill && $sessions) {
                self::setState($db, 'parking_session_cursor', (string)end($sessions)['id']);
            }

            $sessionCount = count($sessions);
            $vehicleCount = count($vehicles);
            return [
                'ok' => true,
                'synced' => $sessionCount + $vehicleCount,
                'session_count' => $sessionCount,
                'vehicle_count' => $vehicleCount,
                'message' => "Synchronized {$sessionCount} session(s) and {$vehicleCount} vehicle profile(s) to Cloud Mirror.",
            ];
        } catch (Throwable $e) {
            error_log('CloudSyncService::syncPendingBatch Error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function claimScheduledRun(int $intervalMinutes): array
    {
        $intervalMinutes = max(1, $intervalMinutes);
        $db = get_db();
        self::ensureStateTable($db);
        $db->prepare('INSERT IGNORE INTO ' . self::STATE_TABLE . ' (state_key, state_value) VALUES (?, "0")')
            ->execute(['scheduled_last_attempt']);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT state_value FROM ' . self::STATE_TABLE . ' WHERE state_key = ? FOR UPDATE');
            $stmt->execute(['scheduled_last_attempt']);
            $lastAttempt = (int)$stmt->fetchColumn();
            $remaining = ($lastAttempt + ($intervalMinutes * 60)) - time();
            if ($remaining > 0) {
                $db->commit();
                return ['due' => false, 'seconds_remaining' => $remaining];
            }

            self::setState($db, 'scheduled_last_attempt', (string)time());
            $db->commit();
            return ['due' => true, 'seconds_remaining' => 0];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function lastSuccessfulSyncAt(): ?string
    {
        $db = get_db();
        $tableCheck = $db->prepare('
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ');
        $tableCheck->execute([self::STATE_TABLE]);
        if ((int)$tableCheck->fetchColumn() === 0) {
            return null;
        }
        $timestamp = (int)self::getState($db, 'last_success_at', '0');
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function nextVehicleBatch(PDO $db, int $limit): array
    {
        $cursor = (int)self::getState($db, 'registered_vehicle_cursor', '0');
        $vehicles = self::fetchVehicleBatch($db, $cursor, $limit);

        if (!$vehicles && $cursor > 0) {
            self::setState($db, 'registered_vehicle_cursor', '0');
            $vehicles = self::fetchVehicleBatch($db, 0, $limit);
        }

        return $vehicles;
    }

    private static function fetchVehicleBatch(PDO $db, int $cursor, int $limit): array
    {
        $stmt = $db->prepare('
            SELECT id, plate_number, owner_name, phone, vehicle_type, category, notes, is_active,
                   visit_count, is_frequent, default_destination, last_visit_at, driver_name,
                   driver_phone, profile_archived, profile_reset_at, created_at
            FROM registered_vehicles
            WHERE id > :cursor
            ORDER BY id ASC
            LIMIT :limit
        ');
        $stmt->bindValue(':cursor', $cursor, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function fetchSessionBatch(PDO $db, int $cursor, int $limit): array
    {
        $stmt = $db->prepare('
            SELECT id, ticket_id, plate_number, driver_name, driver_phone, destination,
                   entry_time, exit_time, status, duration_minutes, entry_method, exit_method, notes
            FROM parking_sessions
            WHERE id > :cursor
            ORDER BY id ASC
            LIMIT :limit
        ');
        $stmt->bindValue(':cursor', $cursor, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function fetchPendingSessions(PDO $db, int $limit): array
    {
        $stmt = $db->prepare('
            SELECT id, ticket_id, plate_number, driver_name, driver_phone, destination,
                   entry_time, exit_time, status, duration_minutes, entry_method, exit_method, notes
            FROM parking_sessions
            WHERE synced_to_cloud = 0
            ORDER BY id ASC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function transmitRecords(array $sessions, array $vehicles): bool
    {
        if (!$sessions && !$vehicles) {
            return true;
        }

        $db = get_db();
        $config = require __DIR__ . '/../config/config.php';
        $cloudCfg = $config['cloud_sync'] ?? [];
        if (empty($cloudCfg['enabled'])) {
            return false;
        }

        $endpoint = $cloudCfg['endpoint'] ?? '';
        $apiKey = $cloudCfg['api_key'] ?? '';
        $hmacSecret = $cloudCfg['hmac_secret'] ?? '';
        if ($endpoint === '' || $apiKey === '' || $hmacSecret === '') {
            error_log('CloudSyncService: Cloud sync endpoint or credentials are not configured.');
            return false;
        }

        $payload = [
            'timestamp' => time(),
            'edge_pc' => 'Mombasa-Mall-Edge-01',
            'sessions' => $sessions,
            'vehicles' => $vehicles,
        ];
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $jsonPayload, $hmacSecret);

        $httpSuccess = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => (int)($cloudCfg['timeout_seconds'] ?? 10),
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8',
                    'X-API-Key: ' . $apiKey,
                    'X-Signature: ' . $signature,
                ],
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $responseData = is_string($response) ? json_decode($response, true) : null;
            $httpSuccess = $httpCode >= 200 && $httpCode < 300 && !empty($responseData['ok']);
            if (!$httpSuccess && $curlError !== '') {
                error_log('CloudSyncService HTTP transport error: ' . $curlError);
            }
        }

        if (!$httpSuccess) {
            $httpSuccess = self::transmitToConfiguredCloudDatabase($sessions, $vehicles);
        }

        if (!$httpSuccess) {
            return false;
        }

        if ($sessions) {
            $ids = array_map('intval', array_column($sessions, 'id'));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("UPDATE parking_sessions SET synced_to_cloud = 1, synced_at = NOW() WHERE id IN ({$placeholders})")
                ->execute($ids);
        }

        self::recordHealth($db, 'Synchronized ' . count($sessions) . ' session(s) and ' . count($vehicles) . ' vehicle profile(s)');
        return true;
    }

    private static function transmitToConfiguredCloudDatabase(array $sessions, array $vehicles): bool
    {
        try {
            $cloudConfig = require __DIR__ . '/../cloud/config.cloud.php';
            $dbConfig = $cloudConfig['db'];
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";
            $cloudDb = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            require_once __DIR__ . '/../cloud/schema_init.php';
            ensure_cloud_schema($cloudDb);

            $sessionUpsert = $cloudDb->prepare('
                INSERT INTO cloud_parking_sessions (
                    edge_session_id, ticket_id, plate_number, driver_name, driver_phone,
                    destination, entry_time, exit_time, status, duration_minutes,
                    entry_method, exit_method, notes, synced_at
                ) VALUES (
                    :edge_id, :ticket_id, :plate, :driver, :phone, :dest, :entry, :exit,
                    :status, :dur, :entry_method, :exit_method, :notes, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    destination = VALUES(destination), exit_time = VALUES(exit_time),
                    status = VALUES(status), duration_minutes = VALUES(duration_minutes),
                    exit_method = VALUES(exit_method), notes = VALUES(notes), synced_at = NOW()
            ');
            $vehicleUpsert = self::vehicleUpsertStatement($cloudDb);

            $cloudDb->beginTransaction();
            foreach ($sessions as $session) {
                $duration = isset($session['duration_minutes']) && (int)$session['duration_minutes'] > 0
                    ? (int)$session['duration_minutes']
                    : (!empty($session['exit_time'])
                        ? (int)round((strtotime($session['exit_time']) - strtotime($session['entry_time'])) / 60)
                        : null);
                $sessionUpsert->execute([
                    ':edge_id' => $session['id'],
                    ':ticket_id' => $session['ticket_id'],
                    ':plate' => $session['plate_number'],
                    ':driver' => $session['driver_name'],
                    ':phone' => $session['driver_phone'] ?? '',
                    ':dest' => $session['destination'] ?? '',
                    ':entry' => $session['entry_time'],
                    ':exit' => $session['exit_time'] ?? null,
                    ':status' => $session['status'],
                    ':dur' => $duration,
                    ':entry_method' => $session['entry_method'] ?? 'self_signin',
                    ':exit_method' => $session['exit_method'] ?? null,
                    ':notes' => $session['notes'] ?? null,
                ]);
            }
            foreach ($vehicles as $vehicle) {
                self::executeVehicleUpsert($vehicleUpsert, $vehicle);
            }

            $cloudDb->prepare('
                INSERT INTO cloud_edge_heartbeats (edge_pc, records_count, ip_address, last_synced_at)
                VALUES (?, ?, ?, NOW())
            ')->execute([
                'Mombasa-Mall-Edge-01',
                count($sessions) + count($vehicles),
                $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            ]);
            $cloudDb->commit();
            return true;
        } catch (Throwable $e) {
            if (isset($cloudDb) && $cloudDb->inTransaction()) {
                $cloudDb->rollBack();
            }
            error_log('CloudSyncService database fallback error: ' . $e->getMessage());
            return false;
        }
    }

    private static function vehicleUpsertStatement(PDO $pdo): PDOStatement
    {
        return $pdo->prepare('
            INSERT INTO registered_vehicles (
                id, plate_number, owner_name, phone, vehicle_type, category, notes, is_active,
                visit_count, is_frequent, default_destination, last_visit_at, driver_name,
                driver_phone, profile_archived, profile_reset_at, created_at
            ) VALUES (
                :id, :plate, :owner, :phone, :type, :category, :notes, :active, :visits,
                :frequent, :destination, :last_visit, :driver, :driver_phone, :archived,
                :reset_at, :created_at
            )
            ON DUPLICATE KEY UPDATE
                plate_number = VALUES(plate_number), owner_name = VALUES(owner_name),
                phone = VALUES(phone), vehicle_type = VALUES(vehicle_type),
                category = VALUES(category), notes = VALUES(notes), is_active = VALUES(is_active),
                visit_count = VALUES(visit_count), is_frequent = VALUES(is_frequent),
                default_destination = VALUES(default_destination), last_visit_at = VALUES(last_visit_at),
                driver_name = VALUES(driver_name), driver_phone = VALUES(driver_phone),
                profile_archived = VALUES(profile_archived), profile_reset_at = VALUES(profile_reset_at),
                created_at = VALUES(created_at)
        ');
    }

    private static function executeVehicleUpsert(PDOStatement $stmt, array $vehicle): void
    {
        $stmt->execute([
            ':id' => $vehicle['id'],
            ':plate' => $vehicle['plate_number'],
            ':owner' => $vehicle['owner_name'] ?? null,
            ':phone' => $vehicle['phone'] ?? null,
            ':type' => $vehicle['vehicle_type'] ?? 'car',
            ':category' => $vehicle['category'] ?? 'regular',
            ':notes' => $vehicle['notes'] ?? null,
            ':active' => $vehicle['is_active'] ?? 1,
            ':visits' => $vehicle['visit_count'] ?? 0,
            ':frequent' => $vehicle['is_frequent'] ?? 0,
            ':destination' => $vehicle['default_destination'] ?? null,
            ':last_visit' => $vehicle['last_visit_at'] ?? null,
            ':driver' => $vehicle['driver_name'] ?? null,
            ':driver_phone' => $vehicle['driver_phone'] ?? null,
            ':archived' => $vehicle['profile_archived'] ?? 0,
            ':reset_at' => $vehicle['profile_reset_at'] ?? null,
            ':created_at' => $vehicle['created_at'],
        ]);
    }

    private static function ensureStateTable(PDO $db): void
    {
        $db->exec('
            CREATE TABLE IF NOT EXISTS ' . self::STATE_TABLE . ' (
                state_key VARCHAR(64) NOT NULL PRIMARY KEY,
                state_value VARCHAR(255) NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    private static function getState(PDO $db, string $key, string $default): string
    {
        $stmt = $db->prepare('SELECT state_value FROM ' . self::STATE_TABLE . ' WHERE state_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    }

    private static function setState(PDO $db, string $key, string $value): void
    {
        $db->prepare('
            INSERT INTO ' . self::STATE_TABLE . ' (state_key, state_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)
        ')->execute([$key, $value]);
    }

    private static function recordHealth(PDO $db, string $message): void
    {
        self::ensureStateTable($db);
        self::setState($db, 'last_success_at', (string)time());
        $db->prepare('
            INSERT INTO device_health (device, status, last_checked_at, message)
            VALUES ("cloud_sync", "OK", NOW(), :message)
            ON DUPLICATE KEY UPDATE status = "OK", last_checked_at = NOW(), message = VALUES(message)
        ')->execute([':message' => $message]);
    }
}
