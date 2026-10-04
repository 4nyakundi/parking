<?php
/**
 * Mombasa Mall Basement Parking - Public Destinations List
 * Returns all active retail stores, dining, services, and facilities
 * categorized by floor level with unit codes and descriptions.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = get_db();
    $stmt = $db->query('
        SELECT 
            id, 
            name, 
            unit_code, 
            floor_level, 
            category, 
            description, 
            icon, 
            sort_order 
        FROM destinations 
        WHERE is_active = 1 
        ORDER BY sort_order ASC, name ASC
    ');
    $destinations = $stmt->fetchAll();

    echo json_encode([
        'ok' => true, 
        'count' => count($destinations),
        'data' => $destinations
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('DESTINATIONS FETCH ERROR: ' . $e->getMessage());
    echo json_encode([
        'ok' => false, 
        'error' => 'Unable to load destinations at this time.'
    ], JSON_UNESCAPED_UNICODE);
}
