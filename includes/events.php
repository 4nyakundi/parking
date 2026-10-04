<?php
/**
 * Mombasa Mall Basement Parking - Event Bus for Short Polling
 * Dispatches events to the `events` table to notify active guard screens and dashboards.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function fire_event(string $type, array $payload): int
{
    try {
        $db = get_db();

        $stmt = $db->prepare('
            INSERT INTO events (type, payload, created_at)
            VALUES (:type, :payload, NOW())
        ');

        $stmt->execute([
            ':type'    => $type,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        return (int)$db->lastInsertId();
    } catch (Throwable $e) {
        error_log('EVENT DISPATCH ERROR: ' . $e->getMessage());
        return 0;
    }
}
