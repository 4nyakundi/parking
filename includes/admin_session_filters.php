<?php
declare(strict_types=1);
require_once __DIR__ . '/plate_helper.php';
function admin_session_filters(array $input): array
{
    $where = ['1=1']; $params = [];
    foreach (['plate'=>'s.plate_number', 'ticket'=>'s.ticket_id'] as $key=>$column) {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') continue;
        if ($key === 'plate') $value = PlateHelper::clean($value);
        $where[] = "{$column} LIKE :{$key}"; $params[":{$key}"] = "%{$value}%";
    }
    foreach (['destination'=>'s.destination', 'status'=>'s.status'] as $key=>$column) {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') continue;
        if ($key === 'status' && !in_array($value, ['ACTIVE','COMPLETED','CANCELLED'], true)) throw new InvalidArgumentException('Invalid session status.');
        if ($key==='destination') { $where[] = '(s.destination=:destination OR EXISTS (SELECT 1 FROM visit_destinations vd WHERE vd.entity_type="session" AND vd.entity_id=s.id AND vd.destination_name=:store_name))'; $params[':store_name']=$value; } else $where[] = "{$column} = :{$key}"; $params[":{$key}"] = $value;
    }
    foreach (['date_from','date_to'] as $key) {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Africa/Nairobi'));
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Invalid date.');
        $where[] = $key === 'date_from' ? 's.entry_time >= :date_from' : 's.entry_time < :date_to';
        $params[":{$key}"] = $key === 'date_from' ? $date->format('Y-m-d 00:00:00') : $date->modify('+1 day')->format('Y-m-d 00:00:00');
    }
    if (!empty($input['date_from']) && !empty($input['date_to']) && $input['date_from'] > $input['date_to']) throw new InvalidArgumentException('The end date must follow the start date.');
    if ((int)($input['guard_id'] ?? 0) > 0) {
        $where[] = '(s.approved_by = :approver OR s.cleared_by = :clearer)';
        $params[':approver'] = $params[':clearer'] = (int)$input['guard_id'];
    }
    $issue = (string)($input['issue'] ?? '');
    $issues = ['print_failed'=>"s.print_status='failed'",'message_failed'=>"s.whatsapp_status='failed'",
        'overstay'=>"s.status='ACTIVE' AND COALESCE((SELECT approved_until FROM parking_extensions pe WHERE pe.session_id=s.id),DATE_ADD(s.entry_time,INTERVAL 2 HOUR))<=NOW()",
        'missing_details'=>"s.driver_name='Uncollected Driver'",'unsynced'=>'s.synced_to_cloud=0'];
    if ($issue !== '') {
        if (!isset($issues[$issue])) throw new InvalidArgumentException('Invalid issue filter.');
        $where[] = '(' . $issues[$issue] . ')';
    }
    return [implode(' AND ', $where), $params];
}
