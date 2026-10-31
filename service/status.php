<?php
/**
 * Mombasa Mall Basement Parking - Camera & Daemon Status API
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    
    // Fetch device health
    $stmt = $db->query('
        SELECT device, status, last_checked_at, message,
               TIMESTAMPDIFF(SECOND, last_checked_at, NOW()) AS age_seconds
        FROM device_health
    ');
    $devices = $stmt->fetchAll();
    
    // Fetch latest detection from Gate 1 Entrance
    $detStmt = $db->query('
        SELECT id, camera, plate_clean, confidence, snapshot_path, created_at,
               TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds
        FROM alpr_detections
        WHERE camera = "entrance"
        ORDER BY id DESC LIMIT 1
    ');
    $latestEntrance = $detStmt->fetch();

    // Fetch latest detection from Gate 2 Exit
    $exitStmt = $db->query('
        SELECT id, camera, plate_clean, confidence, snapshot_path, created_at,
               TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds
        FROM alpr_detections
        WHERE camera = "exit"
        ORDER BY id DESC LIMIT 1
    ');
    $latestExit = $exitStmt->fetch();

    echo json_encode([
        'ok'               => true,
        'devices'          => $devices,
        'latest_entrance'  => $latestEntrance ?: null,
        'latest_exit'      => $latestExit ?: null,
        'server_time'      => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
    ]);
}
