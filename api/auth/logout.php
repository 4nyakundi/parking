<?php
/**
 * Mombasa Mall Basement Parking - User Logout Endpoint
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

$user = current_user();

if ($user) {
    audit_log($user['id'], 'LOGOUT', 'users', (string)$user['id'], null, ['name' => $user['full_name']]);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

echo json_encode(['ok' => true, 'message' => 'Logged out successfully.']);
