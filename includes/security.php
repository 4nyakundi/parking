<?php
declare(strict_types=1);

final class ParkingRateLimit extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Too many sign-in attempts. Please wait before trying again.');
    }
}

/** Never trust a caller-supplied forwarding header for authentication or throttling. */
function security_peer_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/** Atomic, persistent reservations also count simultaneous password checks. */
function security_rate_reserve(string $scope, int $limit, int $window = 900, ?string $directory = null): array
{
    $directory ??= function_exists('get_config')
        ? (get_config()['security']['login_rate_directory'] ?? __DIR__ . '/../storage/security')
        : __DIR__ . '/../storage/security';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Sign-in protection storage is unavailable.');
    }
    $path = $directory . '/' . hash('sha256', $scope) . '.json';
    $handle = @fopen($path, 'c+');
    if (!$handle) throw new RuntimeException('Sign-in protection storage is unavailable.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock sign-in protection storage.');
        $raw = stream_get_contents($handle);
        $attempts = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $now = time();
        $attempts = array_filter($attempts, fn($at) => is_int($at) && $at > $now - $window);
        if (count($attempts) >= $limit) throw new ParkingRateLimit(max(1, min($attempts) + $window - $now));
        $ticket = bin2hex(random_bytes(16));
        $attempts[$ticket] = $now;
        $encoded = json_encode($attempts, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
            throw new RuntimeException('Cannot save sign-in protection state.');
        }
        return [$path, $ticket];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** A successful authentication removes only its own reservation, not earlier failures. */
function security_rate_success(array $reservations): void
{
    foreach ($reservations as [$path, $ticket]) {
        $handle = @fopen($path, 'r+');
        if (!$handle) continue;
        try {
            if (!flock($handle, LOCK_EX)) continue;
            $attempts = json_decode(stream_get_contents($handle), true, 512, JSON_THROW_ON_ERROR);
            unset($attempts[$ticket]);
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($attempts, JSON_THROW_ON_ERROR));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

/** All PIN writers lock the same account set before checking or updating hashes. */
function security_lock_pin_accounts(PDO $db): array
{
    return $db->query('SELECT id, pin_hash FROM users ORDER BY id FOR UPDATE')->fetchAll();
}

function security_require_unique_pin(array $accounts, string $pin, int $userId): void
{
    foreach ($accounts as $account) {
        if ((int)$account['id'] !== $userId && !empty($account['pin_hash']) && password_verify($pin, $account['pin_hash'])) {
            throw new InvalidArgumentException('That PIN is already assigned. Choose another.');
        }
    }
}

function security_machine_key_valid(array $config, mixed $provided): bool
{
    $expected = (string)($config['alpr']['api_key'] ?? '');
    return $expected !== '' && is_string($provided) && $provided !== '' && hash_equals($expected, $provided);
}
