<?php
/**
 * Mombasa Mall Basement Parking - Admin Login Proxy
 * Opens the sign-in form for browser visits; forwards submitted credentials.
 */

declare(strict_types=1);

if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Location: ../../admin/login.php', true, 302);
    exit;
}

require __DIR__ . '/../auth/login.php';
