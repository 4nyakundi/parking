<?php
/** Endpoint regressions against connection-local temporary tables only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
$db = get_db();
foreach (['users', 'parking_shifts', 'audit_log', 'parking_sessions', 'visitors', 'alpr_detections', 'events', 'device_health', 'registered_vehicles', 'parking_actions', 'parking_extensions', 'destinations', 'visit_destinations', 'system_settings'] as $table) {
    $db->exec("CREATE TEMPORARY TABLE security_template_{$table} LIKE {$table}");
    $db->exec("CREATE TEMPORARY TABLE {$table} LIKE security_template_{$table}");
}
$db->exec("INSERT INTO users(id,username,full_name,role,is_active) VALUES (1,'security-admin','Security Admin','admin',1),(2,'security-supervisor','Security Supervisor','supervisor',1),(3,'security-guard','Security Guard','guard',1)");
$password = 'Security Test Pass123!';
$hash = password_hash($password, PASSWORD_BCRYPT);
$db->prepare('UPDATE users SET password_hash=? WHERE id IN (1,2)')->execute([$hash]);
foreach ([2 => '834672', 3 => '123456'] as $id => $pin) $db->prepare('UPDATE users SET pin_hash=? WHERE id=?')->execute([password_hash($pin, PASSWORD_BCRYPT), $id]);
$db->exec("INSERT INTO system_settings(`key`,value) VALUES('capacity','60'),('cam_password','private-camera-secret')");
session_save_path(sys_get_temp_dir()); session_id('security-test-' . bin2hex(random_bytes(8))); session_start();
$sessionPath = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . session_id();
$_SESSION = []; $_POST = []; $_GET = [];
$_SERVER['REMOTE_ADDR'] = 'test-' . bin2hex(random_bytes(8));
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'security-test-token';
$case = $argv[1] ?? 'guard_login'; $endpoint = 'auth/login.php'; $expected = 200; $success = true;
$fixturePath = null;
switch ($case) {
    case 'guard_login': $_POST = ['type' => 'pin', 'pin' => '123456']; break;
    case 'supervisor_login': $_POST = ['username' => 'security-supervisor', 'password' => $password]; break;
    case 'admin_login': $_POST = ['username' => 'security-admin', 'password' => $password]; break;
    case 'supervisor_pin': $_POST = ['type' => 'pin', 'pin' => '834672']; $expected = 401; $success = false; break;
    case 'legacy_duplicate':
        $db->prepare('UPDATE users SET pin_hash=? WHERE id=2')->execute([password_hash('123456', PASSWORD_BCRYPT)]);
        $_POST = ['type' => 'pin', 'pin' => '123456']; $expected = 401; $success = false; break;
    case 'wrong_pin': $_POST = ['type' => 'pin', 'pin' => '999999']; $expected = 401; $success = false; break;
    case 'login_get': $_SERVER['REQUEST_METHOD'] = 'GET'; $expected = 405; $success = false; break;
    case 'login_throttled':
        for ($i = 0; $i < 5; $i++) security_rate_reserve('login-pin:' . security_peer_ip(), 5);
        $_POST = ['type' => 'pin', 'pin' => '123456']; $expected = 429; $success = false; break;
    case 'account_throttled':
        for ($i = 0; $i < 5; $i++) security_rate_reserve('login-user-id:2', 5);
        $_POST = ['username' => ' SECURITY-SUPERVISOR ', 'password' => $password]; $expected = 429; $success = false; break;
    case 'change_pin': case 'duplicate_pin': case 'pin_csrf': case 'pin_get':
        $endpoint = 'auth/change-pin.php';
        $_SESSION = ['user_id' => 3, 'role' => 'guard', 'last_activity' => time(), 'csrf_token' => 'security-test-token'];
        $_POST = ['old_pin' => '123456', 'new_pin' => $case === 'duplicate_pin' ? '834672' : '729041'];
        if ($case === 'duplicate_pin') { $expected = 422; $success = false; }
        if ($case === 'pin_csrf') { $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong'; $expected = 403; $success = false; }
        if ($case === 'pin_get') { $_SERVER['REQUEST_METHOD'] = 'GET'; $expected = 405; $success = false; }
        break;
    case 'public_plates':
        $endpoint = 'driver/camera-plate.php'; $_SERVER['REQUEST_METHOD'] = 'GET';
        $db->exec("INSERT INTO alpr_detections(camera,plate_raw,plate_clean,confidence,snapshot_path,created_at) VALUES ('entrance','KDA123A','KDA123A',0.95,'private-photo.jpg',NOW()),('entrance','KDA124A','KDA124A',0.95,'old-photo.jpg',DATE_SUB(NOW(),INTERVAL 1 HOUR))");
        $db->exec("INSERT INTO visitors(plate_number,driver_name,driver_phone,destination) VALUES('KDA123A','Private Person','254712345678','Private Destination')");
        $db->exec("INSERT INTO parking_sessions(ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,status) VALUES('PRIVATE-TICKET','KDA123A','Private Person','254712345678','Private Destination',NOW(),'ACTIVE')");
        break;
    case 'settings_secrets':
        $endpoint = 'admin/settings.php'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SESSION = ['user_id' => 1]; break;
    case 'machine_valid': case 'machine_missing': case 'machine_forwarded': case 'machine_wrong': case 'machine_query_key':
        $endpoint = 'gate/plate-detected.php'; $_POST = ['camera' => 'entrance', 'plate' => 'KDA123A', 'confidence' => 0.95];
        if ($case === 'machine_valid') $_SERVER['HTTP_X_API_KEY'] = get_config()['alpr']['api_key'];
        else { $expected = 401; $success = false; }
        if ($case === 'machine_missing') $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        if ($case === 'machine_forwarded') $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.230';
        if ($case === 'machine_wrong') $_SERVER['HTTP_X_API_KEY'] = 'wrong';
        if ($case === 'machine_query_key') $_GET['api_key'] = get_config()['alpr']['api_key'];
        break;
    case 'firebase_unauthenticated': $endpoint = 'auth/firebase-token.php'; $expected = 401; $success = false; break;
    case 'firebase_csrf': $endpoint = 'auth/firebase-token.php'; $_SESSION = ['user_id' => 1]; $expected = 403; $success = false; break;
    case 'bootstrap_admin':
        $endpoint = '../scripts/provision_admin.php';
        $db->exec('UPDATE users SET is_active=0, password_hash=NULL, pin_hash=NULL');
        putenv('PARKING_INITIAL_ADMIN_USERNAME=security-admin');
        putenv('PARKING_INITIAL_ADMIN_PASSWORD=Security Test Pass123!');
        break;
    case 'snapshot_unauthenticated': $endpoint = 'guard/snapshot-image.php'; $expected = 401; $success = false; break;
    case 'snapshot_traversal':
        $endpoint = 'guard/snapshot-image.php'; $_SESSION = ['user_id' => 3]; $_GET = ['file' => '../../config/secrets.local.php']; $expected = 404; $success = false; break;
    case 'snapshot_staff':
        $endpoint = 'guard/snapshot-image.php'; $_SESSION = ['user_id' => 3];
        $filename = 'security-test-' . bin2hex(random_bytes(8)) . '.png';
        $fixturePath = get_config()['paths']['snapshots'] . '/' . $filename;
        if (!is_dir(dirname($fixturePath))) mkdir(dirname($fixturePath), 0700, true);
        file_put_contents($fixturePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ9kAAAAASUVORK5CYII='));
        $_GET = ['file' => $filename]; break;
    default: throw new RuntimeException('Unknown security case');
}
ob_start();
register_shutdown_function(function () use ($db, $case, $expected, $success, $sessionPath, $fixturePath) {
    $output = ob_get_clean(); $json = json_decode($output, true); $status = http_response_code() ?: 200;
    $good = is_array($json) && $status === $expected && !empty($json['ok']) === $success;
    if ($case === 'bootstrap_admin') {
        $row = $db->query('SELECT * FROM users WHERE id=1')->fetch();
        $good = str_contains($output, 'Initial administrator provisioned.') && $row['is_active'] && password_verify('Security Test Pass123!', $row['password_hash']);
    }
    if ($case === 'snapshot_traversal') $good = $status === 404 && $output === '';
    if ($case === 'snapshot_staff') $good = $status === 200 && $output === file_get_contents($fixturePath);
    if ($fixturePath && is_file($fixturePath)) unlink($fixturePath);
    if (in_array($case, ['supervisor_pin', 'legacy_duplicate', 'wrong_pin', 'login_throttled', 'account_throttled'], true)) $good = $good && empty($_SESSION['user_id']) && (int)$db->query('SELECT COUNT(*) FROM parking_shifts')->fetchColumn() === 0;
    if ($case === 'wrong_pin') $good = $good && (int)$db->query("SELECT COUNT(*) FROM audit_log WHERE action='LOGIN_FAILED'")->fetchColumn() === 1;
    if ($case === 'guard_login') $good = $good && $_SESSION['user_id'] === 3 && !empty($_SESSION['parking_shift_id']);
    if ($case === 'supervisor_login') $good = $good && $_SESSION['user_id'] === 2;
    if ($case === 'admin_login') $good = $good && $_SESSION['user_id'] === 1;
    if ($case === 'change_pin') $good = $good && password_verify('729041', $db->query('SELECT pin_hash FROM users WHERE id=3')->fetchColumn());
    if (in_array($case, ['duplicate_pin', 'pin_csrf', 'pin_get'], true)) $good = $good && password_verify('123456', $db->query('SELECT pin_hash FROM users WHERE id=3')->fetchColumn());
    if ($case === 'public_plates') {
        $good = $good && count($json['cars']) === 1 && $json['cars'][0]['plate_number'] === 'KDA123A' && $json['cars'][0]['already_inside'];
        foreach (['Private Person', '254712345678', 'Private Destination', 'PRIVATE-TICKET', 'private-photo', 'existing_phone', 'existing_driver', 'existing_dest', 'snapshot_url', 'active_ticket_id'] as $secret) $good = $good && !str_contains($output, $secret);
    }
    if ($case === 'settings_secrets') $good = $good && !str_contains($output, 'private-camera-secret') && !isset($json['data']['cam_password']) && isset($json['data']['capacity']);
    if (str_starts_with($case, 'machine_')) $good = $good && (int)$db->query('SELECT COUNT(*) FROM alpr_detections')->fetchColumn() === ($success ? 1 : 0);
    $current = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . session_id();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    foreach (array_unique([$sessionPath, $current]) as $file) if (is_file($file)) unlink($file);
    $rateDirectory = get_config()['security']['login_rate_directory'] ?? null;
    if ($rateDirectory && is_dir($rateDirectory)) {
        foreach (glob($rateDirectory . '/*.json') ?: [] as $file) unlink($file);
        rmdir($rateDirectory);
    }
    echo ($good ? 'PASS: ' : 'FAIL: ') . $case . ' with temporary tables' . PHP_EOL;
    if (!$good) { echo 'Status ' . $status . ': ' . substr($output, 0, 800) . PHP_EOL; exit(1); }
});
require __DIR__ . '/../api/' . $endpoint;
