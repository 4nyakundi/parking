<?php
/** Serve camera images only to current staff sessions, retaining existing image URLs. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_role(['guard', 'supervisor', 'admin']);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$file = (string)($_GET['file'] ?? '');
$root = realpath(get_config()['paths']['snapshots']);
$path = $root ? realpath($root . DIRECTORY_SEPARATOR . $file) : false;
$prefix = $root ? $root . DIRECTORY_SEPARATOR : '';
$inside = $path && (PHP_OS_FAMILY === 'Windows'
    ? strncasecmp($path, $prefix, strlen($prefix)) === 0
    : str_starts_with($path, $prefix));
if (!$inside || !is_file($path) || !preg_match('~^[A-Za-z0-9_./-]+\.(jpe?g|png|webp|gif)$~i', $file)) {
    http_response_code(404);
    exit;
}
$info = @getimagesize($path);
if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $info['mime']);
header('Content-Length: ' . filesize($path));
readfile($path);
