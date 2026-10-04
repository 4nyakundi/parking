<?php
/**
 * Mombasa Mall Basement Parking - Current Authenticated User & CSRF Info
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$user = current_user();

if (!$user) {
    echo json_encode([
        'ok'            => false,
        'authenticated' => false,
        'csrf_token'    => csrf_token(),
    ]);
    exit;
}

echo json_encode([
    'ok'            => true,
    'authenticated' => true,
    'user'          => $user,
    'csrf_token'    => csrf_token(),
]);
