<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin_actions.php';
require_once __DIR__ . '/../../includes/firebase_staff.php';
$user = require_role(['guard', 'supervisor', 'admin']);
admin_require_mutation('token', ['token']);
try {
    $config = get_config()['firebase'];
    if (!is_file($config['service_account_file'])) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Secure cloud access is not configured. Local parking operations remain available.']);
        exit;
    }
    $serviceAccount = json_decode(file_get_contents($config['service_account_file']), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode(['ok' => true, 'token' => firebase_staff_token($user, $serviceAccount, $config['project_id']), 'expires_in' => 300]);
} catch (Throwable $e) {
    error_log('FIREBASE STAFF AUTH: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Secure cloud access is temporarily unavailable. Local parking operations remain available.']);
}
