<?php
/**
 * Mombasa Mall Basement Parking - Driver Camera Plate Detection Fetcher
 * Public endpoint allowing arriving drivers to view recent camera-captured vehicles
 * so they can select their vehicle and seamlessly complete self check-in on mobile.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/plate_helper.php';

try {
    $db = get_db();

    // 1. Fetch pending visitors from entrance queue
    $visStmt = $db->query("
        SELECT 
            v.id AS visitor_id,
            v.plate_number AS plate_clean,
            v.driver_name,
            v.driver_phone,
            v.destination,
            v.source,
            v.created_at,
            TIMESTAMPDIFF(SECOND, v.created_at, NOW()) AS age_seconds,
            (SELECT snapshot_path FROM alpr_detections WHERE plate_clean = v.plate_number AND camera = 'entrance' ORDER BY id DESC LIMIT 1) AS snapshot_path,
            (SELECT confidence FROM alpr_detections WHERE plate_clean = v.plate_number AND camera = 'entrance' ORDER BY id DESC LIMIT 1) AS confidence
        FROM visitors v
        WHERE v.status = 'PENDING'
        ORDER BY v.id DESC
        LIMIT 20
    ");
    $pendingVisitors = $visStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch recent entrance ALPR detections (last 2 hours or latest 25 detections)
    $detStmt = $db->query("
        SELECT 
            ad.id AS detection_id,
            ad.plate_raw,
            ad.plate_clean,
            ad.confidence,
            ad.snapshot_path,
            ad.created_at,
            TIMESTAMPDIFF(SECOND, ad.created_at, NOW()) AS age_seconds
        FROM alpr_detections ad
        WHERE ad.camera = 'entrance'
        ORDER BY ad.id DESC
        LIMIT 25
    ");
    $detections = $detStmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge into unique vehicles keyed by clean plate
    $carsMap = [];

    // First process pending visitors (they are actively waiting in queue)
    foreach ($pendingVisitors as $v) {
        $clean = PlateHelper::clean($v['plate_clean'] ?? '');
        if (!$clean || !PlateHelper::validate($clean)) continue;
        if (isset($carsMap[$clean])) continue;

        $hasDetails = !empty($v['driver_name']) && $v['driver_name'] !== 'Visitor';
        $carsMap[$clean] = [
            'plate_number'      => $clean,
            'formatted_plate'   => PlateHelper::format($clean),
            'vehicle_type'      => PlateHelper::detectType($clean),
            'confidence'        => (float)($v['confidence'] ?? 0.95),
            'snapshot_url'      => !empty($v['snapshot_path']) ? $v['snapshot_path'] : null,
            'created_at'        => $v['created_at'],
            'age_seconds'       => (int)($v['age_seconds'] ?? 0),
            'visitor_id'        => (int)$v['visitor_id'],
            'source'            => $v['source'] ?? 'camera',
            'has_pending_info'  => $hasDetails,
            'existing_driver'   => $hasDetails ? $v['driver_name'] : '',
            'existing_phone'    => $v['driver_phone'] ?? '',
            'existing_dest'     => $v['destination'] ?? '',
        ];
    }

    // Next process camera detections
    foreach ($detections as $d) {
        $clean = PlateHelper::clean($d['plate_clean'] ?? '');
        if (!$clean || !PlateHelper::validate($clean)) continue;

        if (!isset($carsMap[$clean])) {
            $carsMap[$clean] = [
                'plate_number'      => $clean,
                'formatted_plate'   => PlateHelper::format($clean),
                'vehicle_type'      => PlateHelper::detectType($clean),
                'confidence'        => (float)($d['confidence'] ?? 0.95),
                'snapshot_url'      => !empty($d['snapshot_path']) ? $d['snapshot_path'] : null,
                'created_at'        => $d['created_at'],
                'age_seconds'       => (int)($d['age_seconds'] ?? 0),
                'visitor_id'        => null,
                'source'            => 'alpr_camera',
                'has_pending_info'  => false,
                'existing_driver'   => '',
                'existing_phone'    => '',
                'existing_dest'     => '',
            ];
        }
    }

    // Convert to list & augment with session status and formatted time
    $carsList = [];
    foreach ($carsMap as $clean => $car) {
        // Check if vehicle is already parked inside with an active session
        $actStmt = $db->prepare('SELECT id, ticket_id FROM parking_sessions WHERE plate_number = :plate AND status = "ACTIVE" LIMIT 1');
        $actStmt->execute([':plate' => $clean]);
        $activeSession = $actStmt->fetch(PDO::FETCH_ASSOC);

        $ageSec = (int)$car['age_seconds'];
        $ageMins = floor($ageSec / 60);
        if ($ageMins < 1) {
            $timeAgo = 'Just now';
        } elseif ($ageMins < 60) {
            $timeAgo = $ageMins == 1 ? '1 min ago' : "{$ageMins} mins ago";
        } else {
            $hrs = floor($ageMins / 60);
            $timeAgo = $hrs == 1 ? '1 hr ago' : "{$hrs} hrs ago";
        }

        $detTs = !empty($car['created_at']) ? strtotime($car['created_at']) : time();
        $detTimeDisplay = date('h:i A', $detTs);

        $statusBadge = 'NEEDS_DATA';
        $statusLabel = 'Awaiting Details';
        if ($activeSession) {
            $statusBadge = 'ALREADY_INSIDE';
            $statusLabel = 'Parked in Basement';
        } elseif ($car['has_pending_info']) {
            $statusBadge = 'PRE_FILLED';
            $statusLabel = 'Details Pre-Filled';
        }

        $carsList[] = [
            'plate_number'      => $car['plate_number'],
            'formatted_plate'   => $car['formatted_plate'],
            'vehicle_type'      => $car['vehicle_type'],
            'confidence'        => $car['confidence'],
            'snapshot_url'      => $car['snapshot_url'],
            'detected_at'       => $detTimeDisplay,
            'time_ago'          => $timeAgo,
            'age_seconds'       => $ageSec,
            'camera_name'       => 'Gate 1 Entrance Camera',
            'status_badge'      => $statusBadge,
            'status_label'      => $statusLabel,
            'already_inside'    => (bool)$activeSession,
            'active_ticket_id'  => $activeSession ? $activeSession['ticket_id'] : null,
            'has_pending_info'  => $car['has_pending_info'],
            'existing_driver'   => $car['existing_driver'],
            'existing_phone'    => $car['existing_phone'],
            'existing_dest'     => $car['existing_dest'],
        ];
    }

    // Limit to latest 12 vehicles for mobile convenience
    $carsList = array_slice($carsList, 0, 12);
    $topCar = !empty($carsList) ? $carsList[0] : null;

    echo json_encode([
        'ok'      => true,
        'found'   => count($carsList) > 0,
        'count'   => count($carsList),
        'data'    => $topCar,
        'cars'    => $carsList,
        'message' => count($carsList) > 0 ? 'Vehicles fetched from entrance camera' : 'No recent camera detections at gate.',
    ]);
} catch (Throwable $e) {
    error_log('DRIVER CAMERA PLATE ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Unable to read camera plate right now.']);
}
