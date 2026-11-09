<?php
/**
 * Mombasa Mall Basement Parking - User & Security Guard Management API
 * Administrators manage all accounts; supervisors manage guards only.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/parking_operations.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

$currUser = require_role(['supervisor', 'admin'], true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'list');
require_once __DIR__ . '/../../includes/admin_actions.php';
admin_require_mutation($action, ['save', 'toggle_active']);

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

    // 2. CREATE / UPDATE USER (supervisors are restricted to guard accounts)
    if ($action === 'save') {

        $userId   = (int)($input['id'] ?? 0);
        $username = trim((string)($input['username'] ?? ''));
        $fullName = trim((string)($input['full_name'] ?? ''));
        $role     = trim((string)($input['role'] ?? 'guard'));
        $phone    = trim((string)($input['phone'] ?? ''));
        $pin      = trim((string)($input['pin'] ?? ''));
        $password = (string)($input['password'] ?? '');

        $db->beginTransaction();
        $db->query('SELECT id FROM users ORDER BY id FOR UPDATE')->fetchAll();
        $target = null;
        if ($userId > 0) {
            $q=$db->prepare('SELECT * FROM users WHERE id=?'); $q->execute([$userId]); $target=$q->fetch();
            if (!$target) throw new InvalidArgumentException('Account not found.');
        }
        if ($currUser['role'] !== 'admin' && ($role !== 'guard' || ($target && $target['role'] !== 'guard'))) {
            http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Supervisors can manage guard accounts only.']); exit;
        }
        if (mb_strlen($fullName)>100 || mb_strlen($username)>50) throw new InvalidArgumentException('Name or username is too long.');
        if ($phone!=='' && !PhoneHelper::validate(PhoneHelper::normalize($phone))) throw new InvalidArgumentException('Enter a valid Kenyan phone number.');
        if (!$target && $role==='guard' && $pin==='') throw new InvalidArgumentException('A new guard requires a PIN.');
        if ($role!=='guard' && ($username==='' || (!$target && $password===''))) throw new InvalidArgumentException('Management accounts require a username and password.');
        if ($pin!=='') {
            foreach ($db->query('SELECT id,pin_hash FROM users WHERE pin_hash IS NOT NULL')->fetchAll() as $other) {
                if ((int)$other['id']!==$userId && password_verify($pin,$other['pin_hash'])) throw new InvalidArgumentException('That PIN is already assigned. Choose another.');
            }
        }
        if (empty($fullName)) {
            echo json_encode(['ok' => false, 'error' => 'Full Name is required.']);
            exit;
        }

        if (!in_array($role,['guard','supervisor','admin'],true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid account role.']); exit; }
        if ($userId===$currUser['id'] && $role!==$currUser['role']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Ask another administrator to change your role.']); exit; }
        if ($pin!=='' && !preg_match('/^[0-9]{4,6}$/',$pin)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Use a PIN with 4 to 6 digits.']); exit; }
        if ($password!=='' && strlen($password)<8) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Use a password with at least 8 characters.']); exit; }
        $normPhone = !empty($phone) ? PhoneHelper::normalize($phone) : null;

        if ($userId > 0) {
            // Update Existing User
            $updSql = 'UPDATE users SET full_name = :name, username = :username, role = :role, phone = :phone WHERE id = :id';
            $db->prepare($updSql)->execute([
                ':name' => $fullName, ':username'=>$username ?: null,
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

            operation_audit($db,$currUser, 'UPDATE_USER', 'users', (string)$userId, [
                'name' => $fullName,
                'role' => $role,
                'phone' => $normPhone,
                'username' => $username,
                'pin_changed' => $pin!=='',
                'password_changed' => $password!=='',
            ]);

            $db->commit();
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

            operation_audit($db,$currUser, 'CREATE_USER', 'users', (string)$newId, [
                'name' => $fullName,
                'role' => $role,
                'phone' => $normPhone,
                'username' => $username,
                'pin_changed' => $pin!=='',
                'password_changed' => $password!=='',
            ]);

            $db->commit();
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

        $db->beginTransaction();
        $stmt = $db->prepare('SELECT id, role, is_active, full_name FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if ($target && $currUser['role']!=='admin' && $target['role']!=='guard') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Supervisors can manage guard accounts only.']); exit; }
        if ($target) {
            $newActive = $target['is_active'] ? 0 : 1;
            $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newActive, $targetId]);

            operation_audit($db,$currUser,'TOGGLE_USER_ACTIVE','users',(string)$targetId,['was_active'=>(bool)$target['is_active'],'is_active'=>(bool)$newActive]);
            $db->commit();

            echo json_encode(['ok' => true, 'message' => 'User active status updated.']);
            exit;
        }
        throw new InvalidArgumentException('Account not found.');
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    error_log('USERS MANAGEMENT ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not save the account. Check whether the username is already assigned.']);
}
