<?php
/**
 * Mombasa Mall Basement Parking - Change PIN Endpoint (Guards / Supervisors)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/security.php';

$user = require_auth(true);
require_once __DIR__ . '/../../includes/admin_actions.php';
admin_require_mutation('change_pin', ['change_pin']);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$oldPin = trim((string)($input['old_pin'] ?? ''));
$newPin = trim((string)($input['new_pin'] ?? ''));

if (!preg_match('/^[0-9]{4,6}$/', $newPin)) {
    echo json_encode(['ok' => false, 'error' => 'New PIN must be 4 to 6 numeric digits.']);
    exit;
}

try {
    $db = get_db();
    $db->beginTransaction();
    $accounts = security_lock_pin_accounts($db);

    // Check old PIN if user is not admin
    $stmt = $db->prepare('SELECT pin_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $curr = $stmt->fetch();

    if (!empty($curr['pin_hash']) && !password_verify($oldPin, $curr['pin_hash'])) {
        $db->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Current PIN is incorrect.']);
        exit;
    }
    security_require_unique_pin($accounts, $newPin, $user['id']);

    $newHash = password_hash($newPin, PASSWORD_BCRYPT);
    $upd = $db->prepare('UPDATE users SET pin_hash = ? WHERE id = ?');
    $upd->execute([$newHash, $user['id']]);
    $db->commit();

    audit_log($user['id'], 'CHANGE_PIN', 'users', (string)$user['id'], null, ['status' => 'PIN changed successfully']);

    echo json_encode(['ok' => true, 'message' => 'PIN updated successfully.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    error_log('CHANGE PIN ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Failed to update PIN.']);
}
