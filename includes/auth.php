<?php
/**
 * Mombasa Mall Basement Parking - Authentication & Access Control
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'        => (int)$_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? 'guard',
        'phone'     => $_SESSION['phone'] ?? '',
    ];
}

function is_authenticated(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_auth(bool $apiMode = true): array
{
    $user = current_user();
    if (!$user) {
        if ($apiMode) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'         => false,
                'error'      => 'Session expired or not logged in. Please sign in.',
                'need_login' => true,
            ]);
            exit;
        }

        header('Location: /parking/admin/login.php');
        exit;
    }

    // Check idle timeout (15 mins for guards)
    $config = require __DIR__ . '/../config/config.php';
    $idleLimit = $config['security']['guard_idle_timeout'] ?? 900;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $idleLimit) && $user['role'] === 'guard') {
        session_unset();
        session_destroy();
        if ($apiMode) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'         => false,
                'error'      => 'Session timed out due to inactivity. Please enter your PIN again.',
                'need_login' => true,
            ]);
            exit;
        }
        header('Location: /parking/guard/');
        exit;
    }

    $_SESSION['last_activity'] = time();
    return $user;
}

function require_role(string|array $roles, bool $apiMode = true): array
{
    $user = require_auth($apiMode);
    $allowed = is_array($roles) ? $roles : [$roles];

    if (!in_array($user['role'], $allowed, true)) {
        if ($apiMode) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'    => false,
                'error' => 'Unauthorized: This action requires ' . implode(' or ', $allowed) . ' privileges.',
            ]);
            exit;
        }

        http_response_code(403);
        echo '<h1>403 Forbidden - Insufficient Permissions</h1>';
        exit;
    }

    return $user;
}
