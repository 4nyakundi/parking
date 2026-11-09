<?php
/**
 * Mombasa Mall Basement Parking - Guard Approve & Print Endpoint
 * Atomically creates a parking session, generates a unique ticket ID,
 * triggers thermal receipt printing, queues WhatsApp, audits, and fires realtime events.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/visit_destinations.php';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';
require_once __DIR__ . '/../../includes/printer_service.php';
require_once __DIR__ . '/../../includes/whatsapp_service.php';
require_once __DIR__ . '/../../includes/cloud_sync_service.php';
require_once __DIR__ . '/../../includes/databank.php';
require_once __DIR__.'/../../includes/vehicle_restrictions.php';

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
        $driverName = array_key_exists('driver_name', $input) ? (trim((string)$input['driver_name']) ?: 'Visitor') : ($req['driver_name'] ?: 'Visitor');
        $driverPhone = array_key_exists('driver_phone', $input) ? PhoneHelper::normalize((string)$input['driver_phone']) : ($req['driver_phone'] ?: '');
        $destination = array_key_exists('destination', $input) ? trim((string)$input['destination']) : ($req['destination'] ?: '');
        if ($destination === '') { http_response_code(422); echo json_encode(['ok' => false, 'error' => 'Please confirm the destination for this visit.']); exit; }

        $source = $req['source'] ?? 'self_signin';
        $entryMethod = in_array($source, ['alpr_camera', 'alpr_only', 'alpr'], true) ? 'alpr' : ($source === 'manual_guard' ? 'manual' : 'self_signin');

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

    $stores = visit_stores($db,$input,$destination);
    if (!$stores && $requestId>0 && !array_key_exists('destination',$input)) $stores=visit_store_rows($db,'visitor',$requestId);
    if ($stores) $destination=$stores[0]['name'];
    $submittedPhone = (string)($input['driver_phone'] ?? $driverPhone);
    if ($submittedPhone !== '' && (!in_array(strlen(preg_replace('/\D/', '', $submittedPhone)), [9,10,12], true) || !PhoneHelper::validate($driverPhone))) {
        http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Please enter a valid Kenyan mobile number or leave it blank.']); exit;
    }
    $cleanPlate = PlateHelper::clean($plateNumber);
    $formattedPlate = PlateHelper::format($cleanPlate);

    $entryPolicy=vehicle_exit_policy($db,$cleanPlate);
    if($entryPolicy['is_blacklisted']){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Blacklisted car approaching. Do not admit; ask a supervisor to authorize departure.']);exit;}

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
    if(vehicle_exit_policy($db,$cleanPlate,0,true)['is_blacklisted'])throw new DomainException('Blacklisted vehicle: admission is not permitted.');

    // Preserve exact camera detection entry timestamp if available
    $actualEntryTime = date('Y-m-d H:i:s');
    if (!empty($req['created_at'])) {
        $reqTs = strtotime($req['created_at']);
        if ($reqTs && (time() - $reqTs) < 86400) {
            $actualEntryTime = $req['created_at'];
        }
    }

    $insSess = $db->prepare('
        INSERT INTO parking_sessions (
            ticket_id, plate_number, driver_name, driver_phone, destination,
            entry_time, status, entry_snapshot, approved_by, entry_method,
            whatsapp_status, print_status, synced_to_cloud, created_at
        ) VALUES (
            :ticket_id, :plate, :driver, :phone, :dest,
            :entry_time, "ACTIVE", :snapshot, :guard_id, :method,
            "not_sent", "pending", 0, NOW()
        )
    ');
    $insSess->execute([
        ':ticket_id'  => $ticketId,
        ':plate'      => $cleanPlate,
        ':driver'     => $driverName,
        ':phone'      => $driverPhone,
        ':dest'       => $destination,
        ':entry_time' => $actualEntryTime,
        ':snapshot'   => $entrySnapshot,
        ':guard_id'   => $user['id'],
        ':method'     => $entryMethod,
    ]);
    $sessionId = (int)$db->lastInsertId();

    // Mark pending visitor request as APPROVED
    if ($requestId > 0) {
        $db->prepare('UPDATE visitors SET status = "APPROVED", handled_by = :guard,driver_name=:name,driver_phone=:phone,destination=:destination WHERE id = :id')
           ->execute([':guard' => $user['id'], ':id' => $requestId,':name'=>$driverName,':phone'=>$driverPhone,':destination'=>$destination]);
    }

    visit_store_save($db,'session',$sessionId,$stores);
    $db->commit();

    if ($stores) $destination=implode(', ',array_column($stores,'name'));
    $profileSaved = false;
    // 4b. Record visit in Data Bank (enables frequent client promotion after 3 visits)
    try {
        $bankResult = DataBank::recordVisit($cleanPlate, $driverName, $driverPhone, $destination, $sessionId, !empty($input['remember_details']), $user['id']);
        $profileSaved = !empty($bankResult['profile_saved']);
    } catch (Throwable $e) { error_log('DATA BANK SAVE: ' . $e->getMessage()); }

    // 5. Thermal Print execution (Non-blocking: session is already safely saved)
    $entryTime = $actualEntryTime;
    $entryTsVal = strtotime($entryTime);
    $expectedExitTime = date('Y-m-d H:i:s', $entryTsVal + (120 * 60)); // 2 hours = 120 minutes

    $sessionPayload = [
        'session_id'         => $sessionId,
        'ticket_id'          => $ticketId,
        'plate_number'       => $cleanPlate,
        'driver_name'        => $driverName,
        'destination'        => $destination,
        'destinations' => $stores ? array_column($stores,'name') : [$destination],
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
        'destinations' => $stores ? array_column($stores,'name') : [$destination],
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
        'destinations' => $stores ? array_column($stores,'name') : [$destination],
        'entry_time'       => $entryTime,
        'expected_exit'    => $expectedExitTime,
        'print_status'     => $printStatus,
        'whatsapp_status'  => $waStatus,
        'handled_by'       => $user['full_name'],
        'time'             => date('H:i:s'),
    ]);

    // 9. Asynchronously mirror session to Cloud Executive Dashboard
    CloudSyncService::syncSession($sessionId);

    echo json_encode([
        'ok'   => true,
        'data' => [
            'session_id'         => $sessionId,
            'profile_saved' => $profileSaved,
            'ticket_id'          => $ticketId,
            'plate_number'       => $cleanPlate,
            'formatted_plate'    => $formattedPlate,
            'driver_name'        => $driverName,
            'driver_phone'       => $driverPhone,
            'destination'        => $destination,
        'destinations' => $stores ? array_column($stores,'name') : [$destination],
            'entry_time'         => $entryTime,
            'entry_time_fmt'     => date('H:i:s - d/m/Y', $entryTsVal),
            'expected_exit_time' => $expectedExitTime,
            'expected_exit_fmt'  => date('H:i:s - d/m/Y', $entryTsVal + (120 * 60)),
            'max_hours'          => 2.0,
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
