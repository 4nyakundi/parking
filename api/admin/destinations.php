<?php
/**
 * Mombasa Mall Basement Parking - Destinations Configuration API
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

$user = require_role(['supervisor', 'admin'], true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'list');

try {
    $db = get_db();

    // 1. LIST
    if ($action === 'list') {
        $stmt = $db->query('SELECT * FROM destinations ORDER BY sort_order ASC, name ASC');
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // 2. SAVE
    if ($action === 'save') {
        $id          = (int)($input['id'] ?? 0);
        $name        = trim((string)($input['name'] ?? ''));
        $unitCode    = trim((string)($input['unit_code'] ?? ''));
        $floorLevel  = trim((string)($input['floor_level'] ?? 'Ground Floor'));
        $category    = trim((string)($input['category'] ?? 'Retail'));
        $description = trim((string)($input['description'] ?? ''));
        $sortOrder   = (int)($input['sort_order'] ?? 0);
        $icon        = trim((string)($input['icon'] ?? 'store'));
        $isActive    = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

        if (empty($name)) {
            echo json_encode(['ok' => false, 'error' => 'Destination name cannot be blank.']);
            exit;
        }

        if ($id > 0) {
            $stmt = $db->prepare('
                UPDATE destinations 
                SET name = :name, 
                    unit_code = :uc, 
                    floor_level = :fl, 
                    category = :cat, 
                    description = :desc, 
                    sort_order = :sort, 
                    icon = :icon, 
                    is_active = :act 
                WHERE id = :id
            ');
            $stmt->execute([
                ':name' => $name,
                ':uc'   => $unitCode,
                ':fl'   => $floorLevel,
                ':cat'  => $category,
                ':desc' => $description,
                ':sort' => $sortOrder,
                ':icon' => $icon,
                ':act'  => $isActive,
                ':id'   => $id
            ]);
            audit_log($user['id'], 'UPDATE_DESTINATION', 'destinations', (string)$id, null, ['name' => $name]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO destinations (name, unit_code, floor_level, category, description, sort_order, icon, is_active) 
                VALUES (:name, :uc, :fl, :cat, :desc, :sort, :icon, :act)
            ');
            $stmt->execute([
                ':name' => $name,
                ':uc'   => $unitCode,
                ':fl'   => $floorLevel,
                ':cat'  => $category,
                ':desc' => $description,
                ':sort' => $sortOrder,
                ':icon' => $icon,
                ':act'  => $isActive
            ]);
            $newId = (int)$db->lastInsertId();
            audit_log($user['id'], 'ADD_DESTINATION', 'destinations', (string)$newId, null, ['name' => $name]);
        }

        echo json_encode(['ok' => true, 'message' => 'Destination saved successfully.']);
        exit;
    }

    // 3. TOGGLE ACTIVE
    if ($action === 'toggle_active') {
        $id = (int)($input['id'] ?? 0);
        $db->prepare('UPDATE destinations SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true, 'message' => 'Destination status updated.']);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
} catch (Throwable $e) {
    error_log('DESTINATIONS API ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
