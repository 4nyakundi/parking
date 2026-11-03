<?php
/** Returning vehicle profiles. Vehicle ownership and individual visits remain separate. */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plate_helper.php';
require_once __DIR__ . '/phone_helper.php';
class DataBank {
    public static function validVisitsSql(): string {
        return "status IN ('ACTIVE','COMPLETED') AND driver_name <> 'Uncollected Driver'";
    }
    public static function usefulName(string $name): string {
        $name = trim($name);
        return in_array(strtolower($name), ['', 'visitor', 'visitor driver', 'frequent client', 'uncollected driver', 'unknown'], true) ? '' : mb_substr($name, 0, 100);
    }
    public static function lookup(string $plate): ?array {
        $plate = PlateHelper::clean($plate);
        if (!PlateHelper::validate($plate)) return null;
        $db = get_db();
        $stmt = $db->prepare('SELECT * FROM registered_vehicles WHERE plate_number = ? LIMIT 1');
        $stmt->execute([$plate]);
        $reg = $stmt->fetch() ?: [];
        $stats = $db->prepare('SELECT COUNT(*) AS visits, MAX(entry_time) AS last_seen FROM parking_sessions WHERE plate_number = ? AND ' . self::validVisitsSql());
        $stats->execute([$plate]);
        $counts = $stats->fetch();
        $latest = $db->prepare('SELECT id, driver_name, driver_phone, destination, entry_time FROM parking_sessions WHERE plate_number = ? AND ' . self::validVisitsSql() . ' AND entry_time > COALESCE(?, "1000-01-01") ORDER BY entry_time DESC, id DESC LIMIT 1');
        $latest->execute([$plate, $reg['profile_reset_at'] ?? null]);
        $last = $latest->fetch() ?: [];
        $archived = !empty($reg['profile_archived']) || (isset($reg['is_active']) && !(int)$reg['is_active']);
        // A saved driver is explicit; owner_name/phone are never assumed to identify today's driver.
        $name = self::usefulName((string)($reg['driver_name'] ?? $last['driver_name'] ?? ''));
        $phone = PhoneHelper::normalize((string)($reg['driver_phone'] ?? $last['driver_phone'] ?? ''));
        if (!PhoneHelper::validate($phone)) $phone = '';
        $destination = trim((string)($reg['default_destination'] ?? $last['destination'] ?? ''));
        $visits = (int)($counts['visits'] ?? 0);
        return [
            'plate_number' => $plate, 'formatted_plate' => PlateHelper::format($plate),
            'driver_name' => $archived ? '' : $name, 'driver_phone' => $archived ? '' : $phone,
            'driver_phone_display' => $archived ? '' : PhoneHelper::formatDisplay($phone),
            'masked_phone' => $archived || !$phone ? '' : '••••••' . substr($phone, -4),
            'vehicle_type' => $reg['vehicle_type'] ?? PlateHelper::detectType($plate),
            'visit_count' => $visits, 'is_frequent' => $visits >= 3,
            'default_destination' => $archived ? '' : $destination,
            'last_seen' => $counts['last_seen'] ?? null,
            'category' => !empty($reg['is_active']) ? ($reg['category'] ?? 'regular') : 'regular',
            'notes' => !empty($reg['is_active']) ? ($reg['notes'] ?? '') : '',
            'has_bank_profile' => !$archived && ($name !== '' || $phone !== ''),
            'profile_archived' => $archived, 'needs_confirmation' => true, 'skip_details' => false,
            'profile_source' => isset($reg['driver_name']) ? 'confirmed_profile' : 'previous_visit',
        ];
    }
    /** Counts come from sessions, so retrying this method never adds an extra visit. */
    public static function recordVisit(string $plate, string $driverName = '', string $driverPhone = '', string $destination = '', int $sessionId = 0, bool $confirmed = false, ?int $userId = null): array {
        $db = get_db();
        $plate = PlateHelper::clean($plate);
        $session = $db->prepare('SELECT * FROM parking_sessions WHERE id = ? AND plate_number = ? AND ' . self::validVisitsSql());
        $session->execute([$sessionId, $plate]);
        $visit = $session->fetch();
        if (!$visit) return ['ok' => false, 'error' => 'A valid saved visit is required.'];
        $stats = $db->prepare('SELECT COUNT(*) AS visits, MAX(entry_time) AS last_seen FROM parking_sessions WHERE plate_number = ? AND ' . self::validVisitsSql());
        $stats->execute([$plate]); $counts = $stats->fetch();
        $count = (int)$counts['visits'];
        // Creating a reusable profile never changes an existing vehicle's ownership or access category.
        $db->prepare('INSERT INTO registered_vehicles (plate_number, vehicle_type, category, is_active, driver_name, driver_phone, default_destination) VALUES (?, "car", "regular", 1, "", "", "") ON DUPLICATE KEY UPDATE plate_number = VALUES(plate_number)')->execute([$plate]);
        $db->prepare('UPDATE registered_vehicles r LEFT JOIN (SELECT plate_number, COUNT(*) visits, MAX(entry_time) last_seen FROM parking_sessions WHERE ' . self::validVisitsSql() . ' GROUP BY plate_number) s ON s.plate_number=r.plate_number SET r.visit_count=COALESCE(s.visits,0), r.is_frequent=(COALESCE(s.visits,0)>=3), r.last_visit_at=s.last_seen WHERE r.plate_number=?')->execute([$plate]);
        $saved = false;
        if ($confirmed) {
            $name = self::usefulName((string)$visit['driver_name']);
            $phone = PhoneHelper::normalize((string)$visit['driver_phone']);
            if ($phone !== '' && !PhoneHelper::validate($phone)) $phone = '';
            if ($name === '' && $phone === '') return ['ok'=>true, 'visit_count'=>$count, 'is_frequent'=>$count >= 3, 'profile_saved'=>false];
            // Ignore retries and out-of-order callbacks. Historical sessions are never edited here.
            $save = $db->prepare('UPDATE registered_vehicles SET driver_name = ?, driver_phone = ?, default_destination = ?, last_confirmed_session_id = ?, profile_confirmed_by = ?, profile_confirmed_at = NOW() WHERE plate_number = ? AND profile_archived = 0 AND is_active = 1 AND (last_confirmed_session_id IS NULL OR last_confirmed_session_id < ?) AND (profile_reset_at IS NULL OR ? > profile_reset_at)');
            $save->execute([$name, $phone, $visit['destination'], $sessionId, $userId, $plate, $sessionId, $visit['entry_time']]);
            $saved = $save->rowCount() > 0;
        }
        return ['ok' => true, 'visit_count' => $count, 'is_frequent' => $count >= 3, 'profile_saved' => $saved];
    }
}
