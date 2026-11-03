<?php
/** Integration checks use connection-local temporary tables, never live parking writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/guard_history.php';
$db = get_db();
foreach (['parking_sessions', 'users'] as $table) {
    $db->exec("CREATE TEMPORARY TABLE history_template_{$table} LIKE {$table}");
    $db->exec("CREATE TEMPORARY TABLE {$table} LIKE history_template_{$table}");
}
function history_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: {$label}\n";
}
$insert = $db->prepare('INSERT INTO parking_sessions (id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,exit_time,status) VALUES (?,?,?,?,?,?,?,?,?)');
$rows = [
    [1,'KDA101A','Alice','2026-10-19 22:00:00','2026-10-20 03:00:00','COMPLETED'],
    [2,'KDA102A','Bob','2026-10-19 23:00:00',null,'ACTIVE'],
    [3,'KDA103A','Carol','2026-10-20 08:00:00','2026-10-20 09:00:00','COMPLETED'],
    [4,'KDA103A','Carol','2026-10-20 10:00:00','2026-10-20 11:00:00','COMPLETED'],
    [5,'KDA105A','Uncollected Driver','2026-10-20 12:00:00','2026-10-20 13:00:00','COMPLETED'],
    [6,'KDA106A','Cancelled','2026-10-20 14:00:00',null,'CANCELLED'],
    [7,'KDA107A','Earlier','2026-10-19 10:00:00','2026-10-19 11:00:00','COMPLETED'],
    [8,'KDA108A','Next day','2026-10-21 00:00:00',null,'ACTIVE'],
    [9,'KDA109A','Midnight departure','2026-10-19 21:00:00','2026-10-20 00:00:00','COMPLETED'],
    [10,'KDA110A','Late arrival','2026-10-20 23:00:00','2026-10-21 00:00:00','COMPLETED'],
    [11,'KDA111A','Immediate exit','2026-10-20 15:00:00','2026-10-20 15:00:00','COMPLETED'],
];
foreach ($rows as [$id,$plate,$driver,$entry,$exit,$status]) {
    $insert->execute([$id,'TEST-'.$id,$plate,$driver,'254700000000','Test Store',$entry,$exit,$status]);
}
$options = guard_history_options(['mode'=>'day','date'=>'2026-10-20']);
$report = guard_history_report($db, $options);
$summary = $report['summary'];
history_check($summary['arrivals']===5 && $summary['exits']===6 && $summary['movements']===11, 'Arrivals and exits count separately, including midnight departures');
history_check($summary['carried_in']===3 && $summary['remaining']===2, 'Overnight occupancy respects day boundaries');
history_check($summary['carried_in']+$summary['arrivals']-$summary['exits']===$summary['remaining'], 'Opening plus arrivals minus exits balances closing occupancy');
history_check($summary['present_visits']===7 && $summary['unique_cars']===6 && $report['total']===7, 'Repeat visits count separately from unique cars');
history_check($summary['missing_details']===1, 'Uncollected departure remains visible in daily traffic');
$exited = guard_history_report($db, guard_history_options(['mode'=>'exited','date'=>'2026-10-20']));
history_check($exited['total']===5 && $exited['sessions'][0]['id']===11, 'Exited tab excludes uncollected departures and sorts latest first');
$all = guard_history_report($db, guard_history_options(['mode'=>'exited']));
history_check($all['total']===7 && $all['sessions'][0]['id']===10, 'All dates includes historical cleared visits');
$search = guard_history_report($db, guard_history_options(['mode'=>'day','date'=>'2026-10-20','q'=>'Carol']));
history_check($search['total']===2 && $search['summary']===$summary, 'Search filters records without changing full-day tallies');
$formatted = guard_history_report($db, guard_history_options(['mode'=>'day','date'=>'2026-10-20','q'=>'KDA 103A']));
history_check($formatted['total']===2, 'Formatted plates match stored plate numbers');
$injection = guard_history_report($db, guard_history_options(['mode'=>'day','date'=>'2026-10-20','q'=>"' OR 1=1 --"]));
history_check($injection['total']===0, 'Search input stays a bound value');
foreach (['2026-02-30','2026-1-1','2099-01-01'] as $date) {
    $rejected = false;
    try { guard_history_options(['mode'=>'day','date'=>$date]); } catch (InvalidArgumentException $e) { $rejected = true; }
    history_check($rejected, 'Reject invalid or future date '.$date);
}
for ($id=20;$id<75;$id++) $insert->execute([$id,'TEST-'.$id,'KDA200A','Page test','254700000000','Test Store','2026-10-20 16:00:00',null,'ACTIVE']);
$paged = guard_history_report($db, guard_history_options(['mode'=>'day','date'=>'2026-10-20','page'=>2]));
history_check($paged['total']===62 && count($paged['sessions'])===12 && $paged['pages']===2, 'History is bounded to 50 records per page');
$clamped = guard_history_report($db, guard_history_options(['mode'=>'day','date'=>'2026-10-20','page'=>999]));
history_check($clamped['page']===2, 'Out-of-range page is clamped to the last page');
history_check((int)$db->query('SELECT COUNT(*) FROM parking_sessions')->fetchColumn()===66, 'Reporting leaves source records untouched');
