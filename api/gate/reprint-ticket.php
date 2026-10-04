<?php
/**
 * Mombasa Mall Basement Parking - Ticket Reprint Endpoint
 * Allows guards to reprint thermal ticket upon printer error or paper jam.
 * Logged to audit trail.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/printer_service.php';

$user = require_auth(true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$ticketId  = trim((string)($input['ticket_id'] ?? ''));

try {
    $db = get_db();

    if ($sessionId > 0) {
        $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
    } else {
        $stmt = $db->prepare('SELECT * FROM parking_sessions WHERE ticket_id = ?');
        $stmt->execute([$ticketId]);
    }

    $session = $stmt->fetch();
    if (!$session) {
        echo json_encode(['ok' => false, 'error' => 'Parking record not found.']);
        exit;
    }

    // Attempt reprint
    $sessionPayload = [
        'session_id'   => $session['id'],
        'ticket_id'    => $session['ticket_id'],
        'plate_number' => $session['plate_number'],
        'driver_name'  => $session['driver_name'],
        'destination'  => $session['destination'],
        'entry_time'   => $session['entry_time'],
        'guard_name'   => $user['full_name'],
    ];

    $printResult = PrinterService::printTicket($sessionPayload);
    $newStatus = $printResult['success'] ? 'printed' : 'failed';

    $db->prepare('UPDATE parking_sessions SET print_status = ? WHERE id = ?')
       ->execute([$newStatus, $session['id']]);

    // Audit log
    audit_log($user['id'], 'REPRINT_TICKET', 'parking_sessions', $session['ticket_id'], [
        'old_print_status' => $session['print_status'],
    ], [
        'new_print_status' => $newStatus,
        'reprinted_by'     => $user['full_name'],
    ]);

    echo json_encode([
        'ok'           => $printResult['success'],
        'print_status' => $newStatus,
        'message'      => $printResult['message'],
    ]);
} catch (Throwable $e) {
    error_log('REPRINT TICKET ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Reprint failure: ' . $e->getMessage()]);
}
