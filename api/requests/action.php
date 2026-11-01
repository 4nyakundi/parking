<?php
/**
 * Mombasa Mall Basement Parking - Request Action Endpoint (Accept/Reject)
 * Routes actions to approve-session.php or reject.php
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = strtolower(trim((string)($input['action'] ?? 'accept')));

if ($action === 'reject') {
    require __DIR__ . '/../gate/reject.php';
} else {
    // Default to accept / approve
    require __DIR__ . '/../gate/approve-session.php';
}
