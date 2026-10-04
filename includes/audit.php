<?php
/**
 * Mombasa Mall Basement Parking - Audit Trail Logger
 * Permanent, immutable logging of all state transitions and system modifications.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function audit_log(
    ?int $userId,
    string $action,
    string $entity,
    ?string $entityId = null,
    mixed $oldValue = null,
    mixed $newValue = null
): void {
    try {
        $db = get_db();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }

        $oldJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare('
            INSERT INTO audit_log (user_id, action, entity, entity_id, old_value, new_value, ip, created_at)
            VALUES (:user_id, :action, :entity, :entity_id, :old_value, :new_value, :ip, NOW())
        ');

        $stmt->execute([
            ':user_id'   => $userId,
            ':action'    => $action,
            ':entity'    => $entity,
            ':entity_id' => $entityId,
            ':old_value' => $oldJson,
            ':new_value' => $newJson,
            ':ip'        => substr($ip, 0, 45),
        ]);
    } catch (Throwable $e) {
        // Critical: audit log failure should never crash application, but must be logged to disk
        error_log('AUDIT LOG FAILURE: ' . $e->getMessage());
    }
}
