<?php
/**
 * Mombasa Mall Basement Parking - Guard Manual Check-in Endpoint
 * Creates session for vehicles where driver did not use the mobile self sign-in page.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';
require_once __DIR__ . '/../../includes/printer_service.php';
require_once __DIR__ . '/../../includes/whatsapp_service.php';

$user = require_auth(true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

$rawPlate    = trim((string)($input['plate_number'] ?? ''));
$rawName     = trim((string)($input['driver_name'] ?? ''));
$rawPhone    = trim((string)($input['driver_phone'] ?? ''));
$destination = trim((string)($input['destination'] ?? ''));
$notes       = trim((string)($input['notes'] ?? ''));

if (empty($rawPlate) || empty($destination)) {
    echo json_encode(['ok' => false, 'error' => 'Vehicle plate number and destination are required.']);
    exit;
}

$cleanPlate = PlateHelper::clean($rawPlate);
if (!PlateHelper::validate($cleanPlate)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid Kenyan vehicle plate format.']);
    exit;
}
$displayPlate = PlateHelper::format($cleanPlate);

$cleanPhone = !empty($rawPhone) ? PhoneHelper::normalize($rawPhone) : '';
$driverName = !empty($rawName) ? mb_substr(strip_tags($rawName), 0, 100) : 'Visitor';

try {
    $db = get_db();

    // Check for active session
    $chkStmt = $db->prepare('SELECT id, ticket_id, entry_time FROM parking_sessions WHERE plate_number = ? AND status = "ACTIVE"');
    $chkStmt->execute([$cleanPlate]);
    $existing = $chkStmt->fetch();

    if ($existing) {
        echo json_encode([
            'ok'             => false,
            'is_duplicate'   => true,
            'error'          => "Vehicle {$displayPlate} is already ACTIVE in the basement (Ticket #{$existing['ticket_id']}).",
            'active_session' => $existing,
        ]);
        exit;
    }

    // Generate unique Ticket ID
    $datePart = date('Ymd');
    $seqStmt = $db->prepare('SELECT COUNT(*) FROM parking_sessions WHERE ticket_id LIKE ?');
    $seqStmt->execute(["MM-{$datePart}-%"]);
    $seqNumber = (int)$seqStmt->fetchColumn() + 1;
    $ticketId = sprintf('MM-%s-%04d', $datePart, $seqNumber);

    // Look for recent entrance snapshot from ALPR
    $snapStmt = $db->prepare('
        SELECT snapshot_path 
        FROM alpr_detections 
        WHERE camera = "entrance" AND plate_clean = ? 
        ORDER BY id DESC LIMIT 1
    ');
    $snapStmt->execute([$cleanPlate]);
    $snapshotPath = $snapStmt->fetchColumn() ?: null;

    $db->beginTransaction();

    $stmt = $db->prepare('
        INSERT INTO parking_sessions (
            ticket_id, plate_number, driver_name, driver_phone, destination,
            entry_time, status, entry_snapshot, approved_by, entry_method,
            notes, whatsapp_status, print_status, synced_to_cloud, created_at
        ) VALUES (
            :ticket_id, :plate, :driver, :phone, :dest,
            NOW(), "ACTIVE", :snapshot, :guard_id, "manual",
            :notes, "not_sent", "pending", 0, NOW()
        )
    ');
    $stmt->execute([
        ':ticket_id' => $ticketId,
        ':plate'     => $cleanPlate,
        ':driver'    => $driverName,
        ':phone'     => $cleanPhone,
        ':dest'      => $destination,
        ':snapshot'  => $snapshotPath,
        ':guard_id'  => $user['id'],
        ':notes'     => $notes ?: null,
    ]);
    $sessionId = (int)$db->lastInsertId();

    $db->commit();

    // Print thermal ticket
    $now = time();
    $entryTime = date('Y-m-d H:i:s', $now);
    $expectedExitTime = date('Y-m-d H:i:s', $now + (150 * 60)); // 2.5 hours = 150 minutes

    $sessionPayload = [
        'session_id'         => $sessionId,
        'ticket_id'          => $ticketId,
        'plate_number'       => $cleanPlate,
        'driver_name'        => $driverName,
        'destination'        => $destination,
        'entry_time'         => $entryTime,
        'expected_exit_time' => $expectedExitTime,
        'guard_name'         => $user['full_name'],
    ];

    $printResult = PrinterService::printTicket($sessionPayload);
    $printStatus = $printResult['success'] ? 'printed' : 'failed';

    $db->prepare('UPDATE parking_sessions SET print_status = ? WHERE id = ?')
       ->execute([$printStatus, $sessionId]);

    // Queue WhatsApp if phone provided
    $waStatus = 'not_sent';
    if (!empty($cleanPhone) && PhoneHelper::validate($cleanPhone)) {
        $waQueued = WhatsAppService::queueEntry($sessionId, $cleanPhone, $sessionPayload);
        $waStatus = $waQueued ? 'queued' : 'failed';
    }

    // Audit Log
    audit_log($user['id'], 'MANUAL_CHECKIN', 'parking_sessions', $ticketId, null, [
        'ticket_id'    => $ticketId,
        'plate'        => $cleanPlate,
        'driver'       => $driverName,
        'destination'  => $destination,
        'print_status' => $printStatus,
    ]);

    // Realtime broadcast
    fire_event('session_created', [
        'session_id'      => $sessionId,
        'ticket_id'       => $ticketId,
        'plate_number'    => $cleanPlate,
        'formatted_plate' => $displayPlate,
        'driver_name'     => $driverName,
        'destination'     => $destination,
        'entry_time'      => $entryTime,
        'expected_exit'   => $expectedExitTime,
        'print_status'    => $printStatus,
        'whatsapp_status' => $waStatus,
        'handled_by'      => $user['full_name'],
        'time'            => date('H:i:s'),
    ]);

    echo json_encode([
        'ok'   => true,
        'data' => [
            'session_id'         => $sessionId,
            'ticket_id'          => $ticketId,
            'plate_number'       => $cleanPlate,
            'formatted_plate'    => $displayPlate,
            'driver_name'        => $driverName,
            'driver_phone'       => $cleanPhone,
            'destination'        => $destination,
            'entry_time'         => $entryTime,
            'entry_time_fmt'     => date('H:i:s - d/m/Y', $now),
            'expected_exit_time' => $expectedExitTime,
            'expected_exit_fmt'  => date('H:i:s - d/m/Y', $now + (150 * 60)),
            'max_hours'          => 2.5,
            'guard_name'         => $user['full_name'],
            'print_status'       => $printStatus,
            'print_message'      => $printResult['message'],
            'whatsapp_status'    => $waStatus,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('MANUAL CHECKIN ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error creating manual session: ' . $e->getMessage()]);
}
