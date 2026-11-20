<?php
/** First administrator for a new installation; never resets an active administrator. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
$username = trim((string)(getenv('PARKING_INITIAL_ADMIN_USERNAME') ?: 'admin'));
$password = (string)getenv('PARKING_INITIAL_ADMIN_PASSWORD');
if (strlen($password) < 12 || strlen($password) > 72 || $username === '' || mb_strlen($username) > 50) {
    fwrite(STDERR, "Set PARKING_INITIAL_ADMIN_PASSWORD to a unique 12–72 character password and optionally PARKING_INITIAL_ADMIN_USERNAME.\n");
    exit(1);
}
$db = get_db();
try {
    $db->beginTransaction();
    $rows = $db->query('SELECT id, username, role, is_active FROM users ORDER BY id FOR UPDATE')->fetchAll();
    $target = null;
    foreach ($rows as $row) {
        if ($row['role'] === 'admin' && $row['is_active']) throw new RuntimeException('An active administrator already exists. Use account management to change credentials.');
        if (strcasecmp((string)$row['username'], $username) === 0) $target = $row;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    if ($target) {
        if ($target['role'] !== 'admin') throw new RuntimeException('That username belongs to another account.');
        $db->prepare('UPDATE users SET password_hash=?, pin_hash=NULL, is_active=1, must_change_password=0 WHERE id=?')->execute([$hash, $target['id']]);
    } else {
        $db->prepare("INSERT INTO users(username,full_name,role,password_hash,is_active) VALUES(?,?,'admin',?,1)")->execute([$username, 'System Administrator', $hash]);
    }
    $db->commit();
    echo "Initial administrator provisioned. Sign in with the configured username and password.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() . "\n" : "Could not provision the administrator.\n");
    exit(1);
} finally {
    putenv('PARKING_INITIAL_ADMIN_PASSWORD');
}
