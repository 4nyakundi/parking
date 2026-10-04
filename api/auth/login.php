<?php
/**
 * Mombasa Mall Basement Parking - User Authentication Endpoint
 * Supports 4-digit PIN for Security Guards and Username/Password for Supervisors/Admins
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/audit.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$authType = trim((string)($input['type'] ?? ''));

try {
    $db = get_db();

    // -------------------------------------------------------------
    // Guard PIN Authentication (Tablet keypad)
    // -------------------------------------------------------------
    if ($authType === 'pin' || (!empty($input['pin']) && empty($input['username']))) {
        $pin = trim((string)($input['pin'] ?? ''));

        if (!preg_match('/^[0-9]{4,6}$/', $pin)) {
            echo json_encode(['ok' => false, 'error' => 'Please enter a valid numeric PIN.']);
            exit;
        }

        // Fetch active guards & supervisors with PIN set
        $stmt = $db->prepare('
            SELECT id, username, full_name, role, pin_hash, phone, is_active
            FROM users
            WHERE is_active = 1 AND pin_hash IS NOT NULL AND role IN ("guard", "supervisor")
        ');
        $stmt->execute();
        $users = $stmt->fetchAll();

        $matchedUser = null;
        foreach ($users as $u) {
            if (password_verify($pin, $u['pin_hash'])) {
                $matchedUser = $u;
                break;
            }
        }

        if (!$matchedUser) {
            echo json_encode(['ok' => false, 'error' => 'Incorrect PIN. Please try again.']);
            exit;
        }

        // Setup session
        $_SESSION['user_id']       = (int)$matchedUser['id'];
        $_SESSION['username']      = $matchedUser['username'] ?? '';
        $_SESSION['full_name']     = $matchedUser['full_name'];
        $_SESSION['role']          = $matchedUser['role'];
        $_SESSION['phone']         = $matchedUser['phone'] ?? '';
        $_SESSION['last_activity'] = time();

        // Update last login
        $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$matchedUser['id']]);

        // Audit entry
        audit_log((int)$matchedUser['id'], 'LOGIN_PIN', 'users', (string)$matchedUser['id'], null, [
            'role' => $matchedUser['role'],
            'name' => $matchedUser['full_name'],
        ]);

        echo json_encode([
            'ok'   => true,
            'data' => [
                'id'        => (int)$matchedUser['id'],
                'full_name' => $matchedUser['full_name'],
                'role'      => $matchedUser['role'],
            ],
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // Supervisor / Admin Username & Password Authentication
    // -------------------------------------------------------------
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['ok' => false, 'error' => 'Please enter both username and password.']);
        exit;
    }

    $stmt = $db->prepare('
        SELECT id, username, full_name, role, password_hash, phone, is_active, must_change_password
        FROM users
        WHERE username = :username AND is_active = 1
        LIMIT 1
    ');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['ok' => false, 'error' => 'Invalid username or password.']);
        exit;
    }

    // Setup session
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['phone']         = $user['phone'] ?? '';
    $_SESSION['last_activity'] = time();

    // Update last login
    $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

    // Audit log
    audit_log((int)$user['id'], 'LOGIN_PASSWORD', 'users', (string)$user['id'], null, [
        'username' => $user['username'],
        'role'     => $user['role'],
    ]);

    echo json_encode([
        'ok'   => true,
        'data' => [
            'id'                   => (int)$user['id'],
            'username'             => $user['username'],
            'full_name'            => $user['full_name'],
            'role'                 => $user['role'],
            'must_change_password' => (bool)$user['must_change_password'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('LOGIN ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An internal error occurred during login. Please try again.']);
}
