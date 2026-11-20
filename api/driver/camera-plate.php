<?php
/** Public vehicle selection: recent plates only, never visitor details or photographs. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
try {
    $db = get_db();
    $rows = $db->query("SELECT plate_clean, MAX(created_at) detected_at, MAX(confidence) confidence,
        TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) age_seconds
        FROM alpr_detections WHERE camera='entrance' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        GROUP BY plate_clean ORDER BY detected_at DESC LIMIT 12")->fetchAll();
    $active = $db->prepare("SELECT EXISTS(SELECT 1 FROM parking_sessions WHERE plate_number=? AND status='ACTIVE')");
    $cars = [];
    foreach ($rows as $row) {
        $plate = PlateHelper::clean($row['plate_clean']);
        if (!PlateHelper::validate($plate)) continue;
        $active->execute([$plate]);
        $inside = (bool)$active->fetchColumn();
        $age = max(0, (int)$row['age_seconds']);
        $cars[] = [
            'plate_number' => $plate,
            'formatted_plate' => PlateHelper::format($plate),
            'vehicle_type' => PlateHelper::detectType($plate),
            'confidence' => (float)$row['confidence'],
            'detected_at' => date('h:i A', strtotime($row['detected_at'])),
            'age_seconds' => $age,
            'time_ago' => $age < 60 ? 'Just now' : (int)floor($age / 60) . ' min ago',
            'camera_name' => 'Gate 1 Entrance Camera',
            'already_inside' => $inside,
            'has_pending_info' => false,
            'status_badge' => $inside ? 'ALREADY_INSIDE' : 'NEEDS_DATA',
            'status_label' => $inside ? 'Parked in Basement' : 'Awaiting Details',
        ];
    }
    echo json_encode(['ok' => true, 'found' => count($cars) > 0, 'count' => count($cars),
        'data' => $cars[0] ?? null, 'cars' => $cars,
        'message' => $cars ? 'Vehicles fetched from entrance camera' : 'No recent camera detections at gate.']);
} catch (Throwable $e) {
    error_log('DRIVER CAMERA PLATE ERROR: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Unable to read camera plate right now.']);
}
