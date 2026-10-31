<?php
/**
 * Mombasa Mall Basement Parking - User Logout Endpoint
 */

declare(strict_types=1);

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

$redirect = $_GET['redirect'] ?? '';
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

if (!empty($redirect)) {
    header('Location: ' . $redirect);
    exit;
}

if (!$isAjax && php_sapi_name() !== 'cli') {
    header('Location: ../../admin/login.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'message' => 'Logged out successfully.']);
