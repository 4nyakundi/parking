<?php
/** Pure security checks; no live records, hardware or cloud services. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/firebase_staff.php';
function security_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: {$label}\n";
}
$directory = sys_get_temp_dir() . '/parking-security-' . bin2hex(random_bytes(8));
try {
    $first = security_rate_reserve('same-client', 2, 900, $directory);
    $second = security_rate_reserve('same-client', 2, 900, $directory);
    try { security_rate_reserve('same-client', 2, 900, $directory); throw new LogicException('Limit bypassed'); }
    catch (ParkingRateLimit $e) { security_check($e->retryAfter > 0 && $e->retryAfter <= 900, 'Repeated attempts are limited with a retry interval'); }
    security_rate_success([$second]);
    security_check(count(json_decode(file_get_contents($first[0]), true)) === 1, 'Success preserves prior failed attempts');
    security_rate_reserve('same-client', 2, 900, $directory);
    $old = time() - 901;
    file_put_contents($first[0], json_encode(['old' => $old]));
    security_rate_reserve('same-client', 2, 900, $directory);
    security_check(count(json_decode(file_get_contents($first[0]), true)) === 1, 'Expired attempts no longer lock out users');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.5'; $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
    security_check(security_peer_ip() === '203.0.113.5', 'Forwarded headers cannot change throttle identity');
    security_check(!security_machine_key_valid(['alpr' => ['api_key' => '']], ''), 'An empty machine secret fails closed');
    security_check(!security_machine_key_valid(['alpr' => ['api_key' => 'secret']], null), 'Missing camera credentials are rejected');
    security_check(security_machine_key_valid(['alpr' => ['api_key' => 'secret']], 'secret'), 'Configured camera credentials are accepted');
    $hash = password_hash('834672', PASSWORD_BCRYPT);
    try { security_require_unique_pin([['id' => 2, 'pin_hash' => $hash]], '834672', 1); throw new LogicException('Duplicate allowed'); }
    catch (InvalidArgumentException $e) { security_check(true, 'Duplicate PINs are rejected across accounts'); }
    security_require_unique_pin([['id' => 2, 'pin_hash' => $hash]], '834672', 2);
    security_check(true, 'Retaining your own PIN is allowed');
    $opensslConfig = getenv('OPENSSL_CONF') ?: 'C:/xampp/php/extras/ssl/openssl.cnf';
    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    if (is_file($opensslConfig)) $options['config'] = $opensslConfig;
    $key = openssl_pkey_new($options);
    if (!$key || !openssl_pkey_export($key, $private, null, $options)) throw new RuntimeException('Unable to generate a test signing key.');
    $token = firebase_staff_token(['id' => 7, 'role' => 'guard'], ['project_id' => 'test', 'client_email' => 'signer@test.iam.gserviceaccount.com', 'private_key' => $private], 'test', 1700000000);
    [$header, $body, $signature] = explode('.', $token);
    $decode = fn($part) => base64_decode(strtr($part, '-_', '+/'));
    $claims = json_decode($decode($body), true);
    security_check(openssl_verify($header . '.' . $body, $decode($signature), openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256) === 1, 'Staff cloud tokens have a valid RSA signature');
    security_check($claims['uid'] === 'parking-staff-7' && $claims['claims']['parking_staff'] === true && $claims['claims']['parking_until'] === 1700000300, 'Cloud staff access has a five minute authorization window');
    try { firebase_staff_token(['id' => 7, 'role' => 'visitor'], [], 'test'); throw new LogicException('Visitor received staff token'); }
    catch (InvalidArgumentException $e) { security_check(true, 'Visitors cannot receive staff cloud credentials'); }
} finally {
    foreach (glob($directory . '/*.json') ?: [] as $path) unlink($path);
    if (is_dir($directory)) rmdir($directory);
}
