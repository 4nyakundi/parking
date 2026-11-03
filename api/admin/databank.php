<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/databank.php';
require_once __DIR__ . '/../../includes/audit.php';
$user = require_role(['admin', 'supervisor']);
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = (string)($input['action'] ?? $_GET['action'] ?? 'history');
$id = (int)($input['id'] ?? $_GET['id'] ?? 0);
try {
    $db = get_db();
    if ($action === 'review') {
        $groups = [];
        foreach ($db->query('SELECT id, plate_number FROM registered_vehicles')->fetchAll() as $row) $groups[PlateHelper::clean($row['plate_number'])][] = $row;
        $review = [];
        foreach ($groups as $plate => $rows) {
            if (count($rows) > 1 || $rows[0]['plate_number'] !== $plate) $review[] = ['reason'=>'Check profile identity before merging', 'records'=>$rows];
        }
        $plates = $db->query('SELECT DISTINCT plate_number FROM parking_sessions WHERE ' . DataBank::validVisitsSql())->fetchAll(PDO::FETCH_COLUMN);
        foreach ($plates as $plate) {
            if ($plate !== PlateHelper::clean($plate) || !PlateHelper::validate(PlateHelper::clean($plate))) $review[] = ['reason'=>'Review historical plate before linking', 'records'=>[['plate_number'=>$plate]]];
        }
        echo json_encode(['ok' => true, 'data' => $review]); exit;
    }
    $stmt = $db->prepare('SELECT * FROM registered_vehicles WHERE id = ?'); $stmt->execute([$id]); $profile = $stmt->fetch();
    if (!$profile) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Vehicle profile not found.']); exit; }
    if ($action === 'history') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $stmt = $db->prepare('SELECT ticket_id, driver_name, driver_phone, destination, entry_time, exit_time, status FROM parking_sessions WHERE plate_number = ? ORDER BY entry_time DESC, id DESC LIMIT 25 OFFSET ' . (($page - 1) * 25));
        $stmt->execute([$profile['plate_number']]);
        echo json_encode(['ok' => true, 'data' => ['profile' => DataBank::lookup($profile['plate_number']), 'visits' => $stmt->fetchAll(), 'page' => $page]]); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Use POST for profile changes.']); exit; }
    require_csrf();
    if ($action === 'save') {
        $name = DataBank::usefulName((string)($input['driver_name'] ?? ''));
        $phone = PhoneHelper::normalize((string)($input['driver_phone'] ?? ''));
        if ($phone !== '' && !PhoneHelper::validate($phone)) throw new InvalidArgumentException('Enter a valid driver mobile number.');
        $dest = mb_substr(trim((string)($input['default_destination'] ?? '')), 0, 150);
        $db->prepare('UPDATE registered_vehicles SET driver_name=?, driver_phone=?, default_destination=?, profile_confirmed_by=?, profile_confirmed_at=NOW(), last_confirmed_session_id=(SELECT COALESCE(MAX(s.id),0) FROM parking_sessions s WHERE s.plate_number=registered_vehicles.plate_number) WHERE id=?')->execute([$name,$phone,$dest,$user['id'],$id]);
    } elseif ($action === 'archive' || $action === 'restore') {
        $db->prepare('UPDATE registered_vehicles SET profile_archived=? WHERE id=?')->execute([(int)($action === 'archive'),$id]);
    } elseif ($action === 'reset') {
        // New owner/driver: stop reusing old identity, but retain every historical parking record.
        $db->prepare('UPDATE registered_vehicles SET driver_name="", driver_phone="", default_destination="", last_confirmed_session_id=NULL, profile_confirmed_at=NULL, profile_confirmed_by=NULL, profile_reset_at=NOW() WHERE id=?')->execute([$id]);
    } else { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Unknown profile action.']); exit; }
    audit_log($user['id'], 'DATA_BANK_' . strtoupper($action), 'registered_vehicles', (string)$id, null, ['profile_changed'=>true, 'history_preserved'=>true]);
    echo json_encode(['ok'=>true]);
} catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
catch (Throwable $e) { error_log('DATA BANK ADMIN: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Profile operation failed. No parking history was removed.']); }
