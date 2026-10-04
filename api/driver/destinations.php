<?php
/**
 * Mombasa Mall Basement Parking - Public Destinations List
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = get_db();
    $stmt = $db->query('
        SELECT id, name, icon, sort_order 
        FROM destinations 
        WHERE is_active = 1 
        ORDER BY sort_order ASC, name ASC
    ');
    $destinations = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'data' => $destinations]);
} catch (Throwable $e) {
    error_log('DESTINATIONS FETCH ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Unable to load destinations at this time.']);
}
