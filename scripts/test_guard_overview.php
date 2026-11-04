<?php
/** Daily overview regression checks. Temporary tables shadow live tables on this connection. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/guard_overview.php';
$db = get_db();
foreach (['parking_sessions', 'visitors'] as $table) {
    $db->exec("CREATE TEMPORARY TABLE overview_template_{$table} LIKE {$table}");
    $db->exec("CREATE TEMPORARY TABLE {$table} LIKE overview_template_{$table}");
}
function overview_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: {$label}\n";
}
$session = $db->prepare('INSERT INTO parking_sessions (id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,exit_time,status) VALUES (?,?,?,?,?,?,?,?,?)');
$rows = [
    [1,'KDA101A','Alice','2026-10-20 08:00:00','2026-10-20 09:00:00','COMPLETED'],
    [2,'KDA102A','Bob','2026-10-19 22:00:00','2026-10-20 01:00:00','COMPLETED'],
    [3,'KDA103A','Uncollected Driver','2026-10-20 10:00:00','2026-10-20 11:00:00','COMPLETED'],
    [4,'KDA104A','Cancelled','2026-10-20 11:00:00',null,'CANCELLED'],
    [5,'KDA105A','Tomorrow','2026-10-21 00:00:00',null,'ACTIVE'],
    [6,'KDA106A','Late','2026-10-20 23:59:00','2026-10-21 00:00:00','COMPLETED'],
    [7,'KDA107A','Uncollected Driver','2026-10-19 23:00:00','2026-10-20 00:00:00','COMPLETED'],
];
foreach ($rows as [$id,$plate,$driver,$entry,$exit,$status]) {
    $session->execute([$id,'OVERVIEW-'.$id,$plate,$driver,'254700000000','Test Store',$entry,$exit,$status]);
}
$visitor = $db->prepare('INSERT INTO visitors (plate_number,driver_name,driver_phone,destination,status,source,created_at) VALUES (?,"","","","PENDING","alpr_camera",?)');
foreach ([['PEND001','2026-10-20 12:00:00'],['PEND001','2026-10-20 12:01:00'],
    ['KDA101A','2026-10-20 08:00:00'],['KDA102A','2026-10-20 15:00:00'],
    ['KDA103A','2026-10-20 10:00:00'],['OLDER01','2026-10-19 22:00:00'],
    ['NEXT001','2026-10-21 00:00:00'],['KDA106A','2026-10-20 23:59:30']] as $row) $visitor->execute($row);
$db->exec("INSERT INTO visitors (plate_number,driver_name,driver_phone,destination,status,created_at) VALUES
    ('APPROVED','','','','APPROVED','2026-10-20 12:00:00'),
    ('REJECTED','','','','REJECTED','2026-10-20 12:00:00'),
    ('EXPIRED','','','','EXPIRED','2026-10-20 12:00:00')");
$day = new DateTimeImmutable('2026-10-20', new DateTimeZone('Africa/Nairobi'));
$report = guard_daily_overview($db, $day);
overview_check($report['registered']===2, 'Registered arrivals exclude cancelled, overnight and next-day entries');
overview_check($report['pending_registration']===2, 'Pending count deduplicates plates and suppresses already recorded visits');
overview_check($report['exited_without_details']===1 && $report['unregistered']===3, 'Unregistered combines unresolved arrivals and departures without details');
overview_check($report['exited']===4, 'Exits include overnight cars and uncollected departures on the correct day');
overview_check($report['total_arrivals']===5 && $report['total_arrivals']===$report['registered']+$report['unregistered'], 'Overview arrival total balances registered and unregistered');
$session->execute([8,'OVERVIEW-8','PEND001','New Driver','254700000000','Test Store','2026-10-20 12:00:00',null,'ACTIVE']);
$after = guard_daily_overview($db, $day);
overview_check($after['registered']===3 && $after['pending_registration']===1 && $after['total_arrivals']===5, 'Registration moves a pending car into registered without double counting duplicate captures');
$session->execute([9,'OVERVIEW-9','KDA102A','Returning Driver','254700000000','Test Store','2026-10-20 15:00:00',null,'ACTIVE']);
$after = guard_daily_overview($db, $day);
overview_check($after['registered']===4 && $after['pending_registration']===0 && $after['total_arrivals']===5, 'A later repeat visit counts separately from an earlier completed visit');
$empty = guard_daily_overview($db, $day->modify('+2 days'));
overview_check($empty['total_arrivals']===0 && $empty['exited']===0 && $empty['unregistered']===0, 'Empty days return real zero counts');
$timezone = guard_daily_overview($db, new DateTimeImmutable('2026-10-19 22:00:00',new DateTimeZone('UTC')));
overview_check($timezone['date']==='2026-10-20' && $timezone['total_arrivals']===5, 'Reporting date uses Nairobi even when input time is UTC');
overview_check((int)$db->query('SELECT COUNT(*) FROM parking_sessions')->fetchColumn()===9, 'Overview reporting does not change visit records');
