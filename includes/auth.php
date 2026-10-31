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

        $loginUrl = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) ? 'login.php' : '/parking/admin/login.php';
        header('Location: ' . $loginUrl);
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
                'ok'         => false,
                'error'      => 'Unauthorized: This action requires ' . implode(' or ', $allowed) . ' privileges.',
                'need_login' => true,
            ]);
            exit;
        }

        // Render polished light-theme Access Required screen instead of dead-end raw 403
        http_response_code(403);
        $roleTitle = implode(' or ', array_map('ucfirst', $allowed));
        $currentName = htmlspecialchars($user['full_name'] ?: $user['username'] ?: 'User');
        $currentRole = strtoupper($user['role'] ?: 'GUEST');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Access Required — Mombasa Mall Parking</title>
    <style>
        :root {
            --bg: #eef5fc;
            --card: #ffffff;
            --border: #b0cfec;
            --blue: #116FC7;
            --blue-hover: #0d5ca8;
            --text: #0d2b4e;
            --text-sub: #2e5c8a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .perm-card { background: var(--card); border: 1px solid var(--border); border-top: 4px solid var(--blue); border-radius: 12px; max-width: 440px; width: 100%; padding: 32px 28px; box-shadow: 0 10px 25px rgba(17,111,199,0.12); text-align: center; }
        .perm-icon { width: 54px; height: 54px; border-radius: 50%; background: #e0f2fe; color: var(--blue); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; }
        .perm-icon svg { width: 28px; height: 28px; stroke: currentColor; fill: none; stroke-width: 2; }
        h1 { font-size: 20px; font-weight: 900; color: var(--text); margin-bottom: 8px; }
        p { font-size: 13.5px; color: var(--text-sub); line-height: 1.55; margin-bottom: 18px; }
        .user-tag { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 12px; color: var(--text-sub); margin-bottom: 22px; text-align: left; }
        .btn-group { display: flex; flex-direction: column; gap: 10px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 6px; font-size: 13px; font-weight: 800; text-decoration: none; transition: all 0.15s ease; text-transform: uppercase; letter-spacing: 0.04em; }
        .btn-primary { background: var(--blue); color: #fff; border: none; }
        .btn-primary:hover { background: var(--blue-hover); }
        .btn-outline { background: #fff; color: var(--text-sub); border: 1px solid var(--border); }
        .btn-outline:hover { background: #f8fbff; border-color: var(--blue); }
    </style>
</head>
<body>
    <div class="perm-card">
        <div class="perm-icon">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <h1>{$roleTitle} Access Required</h1>
        <p>This management portal requires <strong>{$roleTitle}</strong> authorization to access records and executive configurations.</p>
        <div class="user-tag">
            Currently active session: <strong>{$currentName}</strong> &bull; <span style="color:#0284c7; font-weight:700;">{$currentRole}</span>
        </div>
        <div class="btn-group">
            <a href="../api/auth/logout.php?redirect=../admin/login.php" class="btn btn-primary">
                Switch to Supervisor / Admin Login
            </a>
            <a href="../guard/" class="btn btn-outline">
                Return to Guard Tablet Station
            </a>
        </div>
    </div>
</body>
</html>
HTML;
        exit;
    }

    return $user;
}
