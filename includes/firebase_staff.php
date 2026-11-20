<?php
declare(strict_types=1);

/** Sign a Firebase custom token for a server-verified parking account. */
function firebase_staff_token(array $account, array $serviceAccount, string $projectId, ?int $now = null): string
{
    if (!in_array($account['role'] ?? '', ['guard', 'supervisor', 'admin'], true) || empty($account['id'])) {
        throw new InvalidArgumentException('A verified staff account is required.');
    }
    if (($serviceAccount['project_id'] ?? '') !== $projectId || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
        throw new RuntimeException('Invalid Firebase signing configuration.');
    }
    $now ??= time();
    $encode = fn($value) => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    $email = $serviceAccount['client_email'];
    $payload = $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' . $encode([
        'iss' => $email, 'sub' => $email,
        'aud' => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit',
        'iat' => $now, 'exp' => $now + 300, 'uid' => 'parking-staff-' . (int)$account['id'],
        'claims' => ['parking_staff' => true, 'parking_until' => $now + 300],
    ]);
    if (!openssl_sign($payload, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Unable to sign Firebase credentials.');
    }
    return $payload . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
}
