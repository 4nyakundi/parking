<?php
/**
 * Mombasa Mall Basement Parking - Guard Approve & Print Endpoint
 * Atomically creates a parking session, generates a unique ticket ID,
 * triggers thermal receipt printing, queues WhatsApp, audits, and fires realtime events.
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

$requestId = isset($input['request_id']) ? (int)$input['request_id'] : 0;
$reissue   = !empty($input['reissue']);

try {
    $db = get_db();

    $plateNumber = '';
    $driverName  = '';
    $driverPhone = '';
    $destination = '';
    $entryMethod = 'self_signin';

    // 1. Fetch details from pending request if request_id is supplied
    if ($requestId > 0) {
        $stmt = $db->prepare('SELECT * FROM visitors WHERE id = ?');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            echo json_encode(['ok' => false, 'error' => 'Pending driver request not found or already processed.']);
            exit;
        }

        if ($req['status'] !== 'PENDING' && !$reissue) {
            echo json_encode(['ok' => false, 'error' => "This request has already been {$req['status']}."]);
            exit;
        }

        $plateNumber = $req['plate_number'];

        // Guard may fill in or override name, phone, and destination
        $driverName  = trim((string)($input['driver_name'] ?? '')) ?: ($req['driver_name'] ?: 'Visitor');
        $driverPhone = !empty($input['driver_phone']) ? PhoneHelper::normalize((string)$input['driver_phone']) : ($req['driver_phone'] ?: '');
        $destination = trim((string)($input['destination'] ?? '')) ?: ($req['destination'] ?: 'Mombasa Mall');

        $source = $req['source'] ?? 'self_signin';
        $entryMethod = in_array($source, ['alpr_camera', 'alpr_only', 'alpr'], true) ? 'alpr' : ($source === 'manual_guard' ? 'manual' : 'self_signin');

        // Update the visitor request with filled details
        $db->prepare('UPDATE visitors SET driver_name = :name, driver_phone = :phone, destination = :dest WHERE id = :id')
           ->execute([':name' => $driverName, ':phone' => $driverPhone, ':dest' => $destination, ':id' => $requestId]);
    } else {
        $plateNumber = PlateHelper::clean((string)($input['plate_number'] ?? ''));
        $driverName  = trim((string)($input['driver_name'] ?? ''));
        $driverPhone = PhoneHelper::normalize((string)($input['driver_phone'] ?? ''));
        $destination = trim((string)($input['destination'] ?? ''));
        $entryMethod = 'manual';

        if (empty($plateNumber) || empty($driverName) || empty($destination)) {
            echo json_encode(['ok' => false, 'error' => 'Plate number, Driver name, and Destination are required.']);
            exit;
        }
    }

    $cleanPlate = PlateHelper::clean($plateNumber);
    $formattedPlate = PlateHelper::format($cleanPlate);

    // 2. Check if vehicle is already actively inside
    $actStmt = $db->prepare('
        SELECT id, ticket_id, entry_time 
        FROM parking_sessions 
        WHERE plate_number = :plate AND status = "ACTIVE"
        LIMIT 1
    ');
    $actStmt->execute([':plate' => $cleanPlate]);
    $activeSess = $actStmt->fetch();

    if ($activeSess && !$reissue) {
        echo json_encode([
            'ok'             => false,
            'is_duplicate'   => true,
            'error'          => "Vehicle {$formattedPlate} already has an ACTIVE ticket (#{$activeSess['ticket_id']}) issued at {$activeSess['entry_time']}.",
            'active_session' => $activeSess,
        ]);
        exit;
    }

    // 3. Generate unique Ticket ID: MM-YYYYMMDD-XXXX
    $datePart = date('Ymd');
    $seqStmt = $db->prepare('
        SELECT COUNT(*) 
        FROM parking_sessions 
        WHERE ticket_id LIKE :pattern
    ');
    $seqStmt->execute([':pattern' => "MM-{$datePart}-%"]);
    $seqNumber = (int)$seqStmt->fetchColumn() + 1;
    $ticketId = sprintf('MM-%s-%04d', $datePart, $seqNumber);

    // Look for recent entrance snapshot
    $snapStmt = $db->prepare('
        SELECT snapshot_path 
        FROM alpr_detections 
        WHERE camera = "entrance" AND plate_clean = :plate 
        ORDER BY id DESC LIMIT 1
    ');
    $snapStmt->execute([':plate' => $cleanPlate]);
    $entrySnapshot = $snapStmt->fetchColumn() ?: null;

    // 4. Begin Database Transaction
    $db->beginTransaction();

    $insSess = $db->prepare('
        INSERT INTO parking_sessions (
            ticket_id, plate_number, driver_name, driver_phone, destination,
            entry_time, status, entry_snapshot, approved_by, entry_method,
            whatsapp_status, print_status, synced_to_cloud, created_at
        ) VALUES (
            :ticket_id, :plate, :driver, :phone, :dest,
            NOW(), "ACTIVE", :snapshot, :guard_id, :method,
            "not_sent", "pending", 0, NOW()
        )
    ');
    $insSess->execute([
        ':ticket_id' => $ticketId,
        ':plate'     => $cleanPlate,
        ':driver'    => $driverName,
        ':phone'     => $driverPhone,
        ':dest'      => $destination,
        ':snapshot'  => $entrySnapshot,
        ':guard_id'  => $user['id'],
        ':method'    => $entryMethod,
    ]);
    $sessionId = (int)$db->lastInsertId();

    // Mark pending visitor request as APPROVED
    if ($requestId > 0) {
        $db->prepare('UPDATE visitors SET status = "APPROVED", handled_by = :guard WHERE id = :id')
           ->execute([':guard' => $user['id'], ':id' => $requestId]);
    }

    $db->commit();

    // 5. Thermal Print execution (Non-blocking: session is already safely saved)
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

    // 6. WhatsApp Queueing
    $waStatus = 'not_sent';
    if (!empty($driverPhone)) {
        $waQueued = WhatsAppService::queueEntry($sessionId, $driverPhone, $sessionPayload);
        $waStatus = $waQueued ? 'queued' : 'failed';
    }

    // 7. Audit Log
    audit_log($user['id'], 'APPROVE_ENTRY', 'parking_sessions', $ticketId, null, [
        'ticket_id'    => $ticketId,
        'plate'        => $cleanPlate,
        'driver'       => $driverName,
        'phone'        => $driverPhone,
        'destination'  => $destination,
        'print_status' => $printStatus,
        'wa_status'    => $waStatus,
    ]);

    // 8. Fire Realtime Broadcast Event
    fire_event('session_created', [
        'session_id'       => $sessionId,
        'ticket_id'        => $ticketId,
        'plate_number'     => $cleanPlate,
        'formatted_plate'  => $formattedPlate,
        'driver_name'      => $driverName,
        'destination'      => $destination,
        'entry_time'       => $entryTime,
        'expected_exit'    => $expectedExitTime,
        'print_status'     => $printStatus,
        'whatsapp_status'  => $waStatus,
        'handled_by'       => $user['full_name'],
        'time'             => date('H:i:s'),
    ]);

    echo json_encode([
        'ok'   => true,
        'data' => [
            'session_id'         => $sessionId,
            'ticket_id'          => $ticketId,
            'plate_number'       => $cleanPlate,
            'formatted_plate'    => $formattedPlate,
            'driver_name'        => $driverName,
            'driver_phone'       => $driverPhone,
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
    error_log('APPROVE SESSION ERROR: ' . $e->getMessage());
    echo json_encode([
        'ok'    => false,
        'error' => 'Failed to create parking session: ' . $e->getMessage(),
    ]);
}
