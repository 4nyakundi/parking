<?php
/** Shared protection for changes made through the management panel. */
declare(strict_types=1);
require_once __DIR__ . '/csrf.php';
header('Cache-Control: no-store');
function admin_require_mutation(string $action, array $mutations): void
{
    if (!in_array($action, $mutations, true)) return;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Use POST to change records.']);
        exit;
    }
    require_csrf();
}
