<?php
/**
 * Mombasa Mall Basement Parking - User & Security Guard Management API
 * Role-based access control: Only Admin can create/edit users.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

$currUser = require_role(['supervisor', 'admin'], true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'list');

try {
    $db = get_db();

    // 1. LIST USERS
    if ($action === 'list') {
        $stmt = $db->query('
            SELECT id, username, full_name, role, phone, is_active, last_login_at, created_at,
                   (pin_hash IS NOT NULL) AS has_pin,
                   (password_hash IS NOT NULL) AS has_password
            FROM users
            ORDER BY role = "admin" DESC, role = "supervisor" DESC, id ASC
        ');
        $rows = $stmt->fetchAll();

        $users = [];
        foreach ($rows as $r) {
            $users[] = [
                'id'            => (int)$r['id'],
                'username'      => $r['username'],
                'full_name'     => $r['full_name'],
                'role'          => $r['role'],
                'phone'         => $r['phone'] ? PhoneHelper::formatDisplay($r['phone']) : '',
                'raw_phone'     => $r['phone'],
                'is_active'     => (bool)$r['is_active'],
                'has_pin'       => (bool)$r['has_pin'],
                'has_password'  => (bool)$r['has_password'],
                'last_login_at' => $r['last_login_at'],
                'created_at'    => $r['created_at'],
            ];
        }

        echo json_encode(['ok' => true, 'data' => $users]);
        exit;
    }

    // 2. CREATE / UPDATE USER (Admin only)
    if ($action === 'save') {
        if ($currUser['role'] !== 'admin') {
            echo json_encode(['ok' => false, 'error' => 'Only administrators can create or edit system user accounts.']);
            exit;
        }
        $userId   = (int)($input['id'] ?? 0);
        $username = trim((string)($input['username'] ?? ''));
        $fullName = trim((string)($input['full_name'] ?? ''));
        $role     = trim((string)($input['role'] ?? 'guard'));
        $phone    = trim((string)($input['phone'] ?? ''));
        $pin      = trim((string)($input['pin'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if (empty($fullName)) {
            echo json_encode(['ok' => false, 'error' => 'Full Name is required.']);
            exit;
        }

        $normPhone = !empty($phone) ? PhoneHelper::normalize($phone) : null;

        if ($userId > 0) {
            // Update Existing User
            $updSql = 'UPDATE users SET full_name = :name, role = :role, phone = :phone WHERE id = :id';
            $db->prepare($updSql)->execute([
                ':name'  => $fullName,
                ':role'  => $role,
                ':phone' => $normPhone,
                ':id'    => $userId,
            ]);

            // Optional PIN reset
            if (!empty($pin)) {
                $pinHash = password_hash($pin, PASSWORD_BCRYPT);
                $db->prepare('UPDATE users SET pin_hash = ? WHERE id = ?')->execute([$pinHash, $userId]);
            }

            // Optional Password reset
            if (!empty($password)) {
                $pwdHash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$pwdHash, $userId]);
            }

            audit_log($currUser['id'], 'UPDATE_USER', 'users', (string)$userId, null, [
                'name' => $fullName,
                'role' => $role,
            ]);

            echo json_encode(['ok' => true, 'message' => 'User updated successfully.']);
            exit;
        } else {
            // Create New User
            $pinHash = !empty($pin) ? password_hash($pin, PASSWORD_BCRYPT) : null;
            $pwdHash = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : null;

            $ins = $db->prepare('
                INSERT INTO users (username, full_name, role, pin_hash, password_hash, phone, is_active, created_at)
                VALUES (:user, :name, :role, :pin, :pwd, :phone, 1, NOW())
            ');
            $ins->execute([
                ':user'  => $username ?: null,
                ':name'  => $fullName,
                ':role'  => $role,
                ':pin'   => $pinHash,
                ':pwd'   => $pwdHash,
                ':phone' => $normPhone,
            ]);
            $newId = (int)$db->lastInsertId();

            audit_log($currUser['id'], 'CREATE_USER', 'users', (string)$newId, null, [
                'name' => $fullName,
                'role' => $role,
            ]);

            echo json_encode(['ok' => true, 'message' => 'User created successfully.']);
            exit;
        }
    }

    // 3. TOGGLE ACTIVE (Deactivate / Reactivate, never delete)
    if ($action === 'toggle_active') {
        $targetId = (int)($input['id'] ?? 0);
        if ($targetId === $currUser['id']) {
            echo json_encode(['ok' => false, 'error' => 'You cannot deactivate your own account.']);
            exit;
        }

        $stmt = $db->prepare('SELECT id, is_active, full_name FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if ($target) {
            $newActive = $target['is_active'] ? 0 : 1;
            $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newActive, $targetId]);

            audit_log($currUser['id'], 'TOGGLE_USER_ACTIVE', 'users', (string)$targetId, [
                'is_active' => $target['is_active'],
            ], [
                'is_active' => $newActive,
            ]);

            echo json_encode(['ok' => true, 'message' => 'User active status updated.']);
            exit;
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('USERS MANAGEMENT ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
