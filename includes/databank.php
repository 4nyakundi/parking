<?php
/**
 * Mombasa Mall Basement Parking - Frequent Client Data Bank Service
 * Automatically tracks vehicle visit counts across gate entries and matches
 * frequent clients (>= 3 visits) so guards never need to ask for details again,
 * allowing instant 1-tap destination selection and ticket issuance.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plate_helper.php';
require_once __DIR__ . '/phone_helper.php';

class DataBank {

    /**
     * Look up a vehicle by plate number in the Data Bank.
     * Computes live total visits across parking_sessions and registered_vehicles.
     */
    public static function lookup(string $plate): ?array {
        $cleanPlate = PlateHelper::clean($plate);
        if (empty($cleanPlate)) return null;

        $db = get_db();

        // 1. Check registered_vehicles record
        $stmt = $db->prepare('
            SELECT id, plate_number, owner_name, phone, vehicle_type, visit_count, is_frequent, default_destination, category, notes, is_active
            FROM registered_vehicles
            WHERE plate_number = :plate AND is_active = 1
            LIMIT 1
        ');
        $stmt->execute([':plate' => $cleanPlate]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Count actual visits in parking_sessions
        $sessStmt = $db->prepare('
            SELECT COUNT(*) as session_count,
                   MAX(driver_name) as latest_driver_name,
                   MAX(driver_phone) as latest_driver_phone,
                   MAX(destination) as latest_destination,
                   MAX(entry_time) as last_seen
            FROM parking_sessions
            WHERE plate_number = :plate
        ');
        $sessStmt->execute([':plate' => $cleanPlate]);
        $sess = $sessStmt->fetch(PDO::FETCH_ASSOC);

        $sessionCount = (int)($sess['session_count'] ?? 0);
        $regVisits = (int)($reg['visit_count'] ?? 0);
        $totalVisits = max($sessionCount, $regVisits);

        // Frequent threshold is 3 visits
        $isFrequent = ($totalVisits >= 3) || (!empty($reg['is_frequent'])) || (isset($reg['category']) && in_array($reg['category'], ['frequent', 'vip', 'staff', 'tenant'], true));

        // Resolve driver name
        $driverName = '';
        if (!empty($reg['owner_name']) && $reg['owner_name'] !== 'Visitor') {
            $driverName = $reg['owner_name'];
        } elseif (!empty($sess['latest_driver_name']) && $sess['latest_driver_name'] !== 'Visitor') {
            $driverName = $sess['latest_driver_name'];
        }

        // Resolve phone
        $driverPhone = !empty($reg['phone']) ? $reg['phone'] : (!empty($sess['latest_driver_phone']) ? $sess['latest_driver_phone'] : '');

        // Resolve preferred destination
        $destination = !empty($reg['default_destination']) ? $reg['default_destination'] : (!empty($sess['latest_destination']) ? $sess['latest_destination'] : 'G-01 Naivas Supermarket');

        $category = $reg['category'] ?? ($isFrequent ? 'frequent' : 'regular');

        return [
            'plate_number'        => $cleanPlate,
            'formatted_plate'     => PlateHelper::format($cleanPlate),
            'driver_name'         => $driverName,
            'driver_phone'        => $driverPhone,
            'driver_phone_display'=> PhoneHelper::formatDisplay($driverPhone),
            'vehicle_type'        => $reg['vehicle_type'] ?? PlateHelper::detectType($cleanPlate),
            'visit_count'         => $totalVisits,
            'is_frequent'         => $isFrequent,
            'default_destination' => $destination,
            'category'            => $category,
            'notes'               => $reg['notes'] ?? '',
            'has_bank_profile'    => !empty($reg) || $totalVisits > 0,
        ];
    }

    /**
     * Record a completed or new entry into the Data Bank.
     * Updates visit count and promotes to Frequent Client if visits >= 3.
     */
    public static function recordVisit(string $plate, string $driverName = '', string $driverPhone = '', string $destination = ''): array {
        $cleanPlate = PlateHelper::clean($plate);
        if (empty($cleanPlate)) return ['ok' => false];

        $db = get_db();

        // Count sessions
        $cStmt = $db->prepare('SELECT COUNT(*) FROM parking_sessions WHERE plate_number = ?');
        $cStmt->execute([$cleanPlate]);
        $sessCount = (int)$cStmt->fetchColumn();

        $name = trim($driverName);
        if ($name === 'Visitor') $name = '';
        $phone = PhoneHelper::normalize($driverPhone);
        $dest = trim($destination);

        // Check if exists in registered_vehicles
        $chk = $db->prepare('SELECT id, owner_name, phone, visit_count, category, is_frequent FROM registered_vehicles WHERE plate_number = ?');
        $chk->execute([$cleanPlate]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        $newVisits = $existing ? max((int)$existing['visit_count'] + 1, $sessCount) : max(1, $sessCount);
        $isFrequent = ($newVisits >= 3) ? 1 : 0;

        if ($existing) {
            $cat = ($isFrequent && $existing['category'] === 'regular') ? 'frequent' : $existing['category'];
            $upd = $db->prepare('
                UPDATE registered_vehicles
                SET visit_count = :visits,
                    is_frequent = :is_frequent,
                    owner_name = COALESCE(NULLIF(:name, ""), owner_name),
                    phone = COALESCE(NULLIF(:phone, ""), phone),
                    default_destination = COALESCE(NULLIF(:dest, ""), default_destination),
                    last_visit_at = NOW(),
                    category = :category
                WHERE id = :id
            ');
            $upd->execute([
                ':visits'      => $newVisits,
                ':is_frequent' => $isFrequent,
                ':name'        => $name,
                ':phone'       => $phone,
                ':dest'        => $dest,
                ':category'    => $cat,
                ':id'          => $existing['id'],
            ]);
        } else {
            $cat = $isFrequent ? 'frequent' : 'regular';
            $ins = $db->prepare('
                INSERT INTO registered_vehicles (
                    plate_number, owner_name, phone, vehicle_type, visit_count, is_frequent,
                    default_destination, last_visit_at, category, notes, is_active, created_at
                ) VALUES (
                    :plate, :name, :phone, "car", :visits, :is_frequent,
                    :dest, NOW(), :category, :notes, 1, NOW()
                )
            ');
            $ins->execute([
                ':plate'       => $cleanPlate,
                ':name'        => $name,
                ':phone'       => $phone,
                ':visits'      => $newVisits,
                ':is_frequent' => $isFrequent,
                ':dest'        => $dest,
                ':category'    => $cat,
                ':notes'       => $isFrequent ? 'Frequent Client (Auto-enrolled)' : null,
            ]);
        }

        return [
            'ok'          => true,
            'plate'       => $cleanPlate,
            'visit_count' => $newVisits,
            'is_frequent' => ($isFrequent === 1),
            'category'    => $cat,
        ];
    }
}
