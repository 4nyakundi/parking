<?php
/**
 * Mombasa Mall Basement Parking - User Authentication Endpoint
 * Supports 4-digit PIN for Security Guards and Username/Password for Supervisors/Admins
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/parking_operations.php';
require_once __DIR__ . '/../../includes/security.php';

header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Use POST to sign in.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$authType = trim((string)($input['type'] ?? ''));
$isPin = $authType === 'pin' || (!empty($input['pin']) && empty($input['username']));
$reservations = [];
function login_reject(string $message, string $method): never
{
    audit_log(null, 'LOGIN_FAILED', 'authentication', null, null, ['method' => $method, 'peer_ip' => security_peer_ip()]);
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

try {
    $reservations[] = security_rate_reserve('login-ip:' . security_peer_ip(), 20);
    if ($isPin) $reservations[] = security_rate_reserve('login-pin:' . security_peer_ip(), 5);
    $db = get_db();

    // -------------------------------------------------------------
    // Guard PIN Authentication (Tablet keypad)
    // -------------------------------------------------------------
    if ($isPin) {
        $pin = trim((string)($input['pin'] ?? ''));

        if (!preg_match('/^[0-9]{4,6}$/', $pin)) {
            login_reject('Please enter a valid numeric PIN.', 'pin');
        }

        // Check all active PINs so legacy collisions fail closed. Only guards may sign in by PIN.
        $stmt = $db->prepare('
            SELECT id, username, full_name, role, pin_hash, phone, is_active
            FROM users
            WHERE is_active = 1 AND pin_hash IS NOT NULL
        ');
        $stmt->execute();
        $users = $stmt->fetchAll();

        $matchedUser = null;
        $matches = 0;
        foreach ($users as $u) {
            if (password_verify($pin, $u['pin_hash'])) {
                $matchedUser = $u;
                $matches++;
            }
        }

        if ($matches !== 1 || $matchedUser['role'] !== 'guard') {
            login_reject('PIN sign-in is unavailable for these credentials. Guards: check your PIN. Management: use username and password.', 'pin');
        }

        $signedInAt=date('Y-m-d H:i:s');
        $activeShift=parking_use_current_shift($db,$matchedUser,$signedInAt);
        session_regenerate_id(true);
        $_SESSION['signed_in_at']=$signedInAt;
        $_SESSION['parking_shift_id']=(int)$activeShift['id'];
        unset($_SESSION['parking_shift_ended']);
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
        security_rate_success($reservations);

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
        login_reject('Please enter both username and password.', 'password');
    }

    $stmt = $db->prepare('
        SELECT id, username, full_name, role, password_hash, phone, is_active, must_change_password
        FROM users
        WHERE username = :username AND is_active = 1
        LIMIT 1
    ');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    // Unknown usernames use the address limit; avoid creating unbounded files for invented names.
    if ($user) $reservations[] = security_rate_reserve('login-user-id:' . (int)$user['id'], 5);

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        login_reject('Invalid username or password.', 'password');
    }

    // Reuse the account's present shift when signing in to management.
    $signedInAt=date('Y-m-d H:i:s');
    $activeShift=parking_use_current_shift($db,$user,$signedInAt);
    // Setup session
    session_regenerate_id(true);
    $_SESSION['signed_in_at']=$signedInAt;
    $_SESSION['parking_shift_id']=(int)$activeShift['id'];
    unset($_SESSION['parking_shift_ended']);
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
    security_rate_success($reservations);

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
} catch (ParkingRateLimit $e) {
    http_response_code(429);
    header('Retry-After: ' . $e->retryAfter);
    audit_log(null, 'LOGIN_THROTTLED', 'authentication', null, null, ['peer_ip' => security_peer_ip()]);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'retry_after' => $e->retryAfter]);
} catch (Throwable $e) {
    http_response_code(503);
    error_log('LOGIN ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An internal error occurred during login. Please try again.']);
}
