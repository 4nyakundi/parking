<?php
/**
 * Mombasa Mall Basement Parking - Registered Vehicles API
 * Supports VIP, Staff, Tenants, and Blacklisted vehicles management.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/plate_helper.php';
require_once __DIR__ . '/../../includes/phone_helper.php';

$user = require_role(['supervisor', 'admin'], true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'list');

try {
    $db = get_db();

    // 1. LIST VEHICLES
    if ($action === 'list') {
        $stmt = $db->query('
            SELECT id, plate_number, owner_name, phone, vehicle_type, category, notes, is_active, created_at
            FROM registered_vehicles
            ORDER BY category = "blacklisted" DESC, category = "vip" DESC, id DESC
        ');
        $rows = $stmt->fetchAll();

        $vehicles = [];
        foreach ($rows as $r) {
            $vehicles[] = [
                'id'              => (int)$r['id'],
                'plate_number'    => $r['plate_number'],
                'formatted_plate' => PlateHelper::format($r['plate_number']),
                'owner_name'      => $r['owner_name'],
                'phone'           => $r['phone'] ? PhoneHelper::formatDisplay($r['phone']) : '',
                'raw_phone'       => $r['phone'],
                'vehicle_type'    => $r['vehicle_type'],
                'category'        => $r['category'],
                'notes'           => $r['notes'],
                'is_active'       => (bool)$r['is_active'],
                'created_at'      => $r['created_at'],
            ];
        }

        echo json_encode(['ok' => true, 'data' => $vehicles]);
        exit;
    }

    // 2. SAVE / UPDATE VEHICLE
    if ($action === 'save') {
        $vehicleId   = (int)($input['id'] ?? 0);
        $rawPlate    = trim((string)($input['plate_number'] ?? ''));
        $ownerName   = trim((string)($input['owner_name'] ?? ''));
        $phone       = trim((string)($input['phone'] ?? ''));
        $vehicleType = trim((string)($input['vehicle_type'] ?? 'car'));
        $category    = trim((string)($input['category'] ?? 'regular'));
        $notes       = trim((string)($input['notes'] ?? ''));
        $isActive    = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

        if (empty($rawPlate)) {
            echo json_encode(['ok' => false, 'error' => 'Vehicle plate number is required.']);
            exit;
        }

        $cleanPlate = PlateHelper::clean($rawPlate);
        $normPhone  = !empty($phone) ? PhoneHelper::normalize($phone) : null;

        if ($vehicleId > 0) {
            // Update
            $stmt = $db->prepare('
                UPDATE registered_vehicles 
                SET plate_number = :plate, owner_name = :owner, phone = :phone, 
                    vehicle_type = :type, category = :cat, notes = :notes, is_active = :act
                WHERE id = :id
            ');
            $stmt->execute([
                ':plate' => $cleanPlate,
                ':owner' => $ownerName,
                ':phone' => $normPhone,
                ':type'  => $vehicleType,
                ':cat'   => $category,
                ':notes' => $notes,
                ':act'   => $isActive,
                ':id'    => $vehicleId,
            ]);

            audit_log($user['id'], 'UPDATE_REGISTERED_VEHICLE', 'registered_vehicles', (string)$vehicleId, null, [
                'plate'    => $cleanPlate,
                'category' => $category,
            ]);

            echo json_encode(['ok' => true, 'message' => 'Vehicle registration updated.']);
            exit;
        } else {
            // Insert
            $ins = $db->prepare('
                INSERT INTO registered_vehicles (plate_number, owner_name, phone, vehicle_type, category, notes, is_active, created_at)
                VALUES (:plate, :owner, :phone, :type, :cat, :notes, :act, NOW())
            ');
            $ins->execute([
                ':plate' => $cleanPlate,
                ':owner' => $ownerName,
                ':phone' => $normPhone,
                ':type'  => $vehicleType,
                ':cat'   => $category,
                ':notes' => $notes,
                ':act'   => $isActive,
            ]);
            $newId = (int)$db->lastInsertId();

            audit_log($user['id'], 'ADD_REGISTERED_VEHICLE', 'registered_vehicles', (string)$newId, null, [
                'plate'    => $cleanPlate,
                'category' => $category,
            ]);

            echo json_encode(['ok' => true, 'message' => 'Vehicle registered successfully.']);
            exit;
        }
    }

    // 3. TOGGLE ACTIVE (Soft-delete only - Permanent Data Rule)
    if ($action === 'toggle_active') {
        $vehicleId = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('SELECT id, is_active, plate_number FROM registered_vehicles WHERE id = ?');
        $stmt->execute([$vehicleId]);
        $veh = $stmt->fetch();

        if ($veh) {
            $newActive = $veh['is_active'] ? 0 : 1;
            $db->prepare('UPDATE registered_vehicles SET is_active = ? WHERE id = ?')->execute([$newActive, $vehicleId]);

            audit_log($user['id'], 'TOGGLE_VEHICLE_ACTIVE', 'registered_vehicles', (string)$vehicleId, [
                'is_active' => $veh['is_active'],
            ], [
                'is_active' => $newActive,
            ]);

            echo json_encode(['ok' => true, 'message' => 'Vehicle status changed.']);
            exit;
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
} catch (Throwable $e) {
    error_log('REGISTERED VEHICLES ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
