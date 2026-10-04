<?php
/**
 * Mombasa Mall Basement Parking - Change Password Endpoint (Admins & Supervisors)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

$user = require_auth(true);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$oldPassword = (string)($input['old_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');

if (strlen($newPassword) < 6) {
    echo json_encode(['ok' => false, 'error' => 'Password must be at least 6 characters long.']);
    exit;
}

try {
    $db = get_db();

    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $curr = $stmt->fetch();

    if (!empty($curr['password_hash']) && !password_verify($oldPassword, $curr['password_hash'])) {
        echo json_encode(['ok' => false, 'error' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $upd = $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
    $upd->execute([$newHash, $user['id']]);

    audit_log($user['id'], 'CHANGE_PASSWORD', 'users', (string)$user['id'], null, ['status' => 'Password changed']);

    echo json_encode(['ok' => true, 'message' => 'Password updated successfully.']);
} catch (Throwable $e) {
    error_log('CHANGE PASSWORD ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to update password.']);
}
