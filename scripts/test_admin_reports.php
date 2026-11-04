<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/admin_reports.php';
require_once __DIR__.'/../includes/admin_session_filters.php';
$db=get_db();
foreach(['users','parking_sessions','visitors'] as $table){$db->exec("CREATE TEMPORARY TABLE admin_test_template_{$table} LIKE {$table}");$db->exec("CREATE TEMPORARY TABLE {$table} LIKE admin_test_template_{$table}");}
function admin_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: {$label}\n";}
$db->exec("INSERT INTO users(id,full_name,role) VALUES(1,'Test Guard','guard')");
$db->exec("INSERT INTO parking_sessions(id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,exit_time,status,approved_by,cleared_by,print_status) VALUES
(1,'TEST-1','KDA123A','Alice','254700000000','Shop','2026-10-19 22:00:00','2026-10-20 01:00:00','COMPLETED',1,1,'printed'),
(2,'TEST-2','KDA123A','Alice','254700000000','Shop','2026-10-20 02:00:00','2026-10-20 03:00:00','COMPLETED',1,1,'failed'),
(3,'TEST-3','KDA124A','Bob','254700000000','Shop','2026-10-20 23:59:59',NULL,'ACTIVE',1,NULL,'pending'),
(4,'TEST-4','KDA125A','Cancelled','','Shop','2026-10-20 12:00:00',NULL,'CANCELLED',1,NULL,'pending'),
(5,'TEST-5','KDA126A','Tomorrow','','Shop','2026-10-21 00:00:00',NULL,'ACTIVE',1,NULL,'pending')");
$db->exec("INSERT INTO visitors(plate_number,driver_name,driver_phone,destination,status,handled_by,created_at) VALUES('KDA127A','','','Shop','REJECTED',1,'2026-10-20 12:00:00')");
[$from,$to,$start,$end]=admin_report_range(['date_from'=>'2026-10-20','date_to'=>'2026-10-20']);
$report=admin_report($db,$start,$end);
admin_check($report['summary']['total_entries']===2,'Report excludes cancelled arrivals and uses exclusive next-day boundary');
admin_check($report['summary']['total_exits']===2,'Report counts overnight departures by exit day');
admin_check($report['summary']['avg_duration_mins']===120,'Average dwell uses departures in the selected period');
admin_check((int)$report['hourly'][0]['hr']===2,'Hourly report includes early morning traffic');
admin_check($report['shifts'][0]['entries_approved']===2&&$report['shifts'][0]['exits_cleared']===2&&$report['shifts'][0]['rejections']===1,'Handover aggregates match entry, exit and rejection dates');
[$where,$params]=admin_session_filters(['plate'=>'KDA 123A','status'=>'COMPLETED','date_from'=>'2026-10-20','date_to'=>'2026-10-20','guard_id'=>1,'issue'=>'print_failed']);
$q=$db->prepare('SELECT s.id FROM parking_sessions s WHERE '.$where);$q->execute($params);
admin_check($q->fetchAll(PDO::FETCH_COLUMN)===[2],'Shared list/export filter binds independent guard placeholders and all selected filters');
foreach([['date_from'=>'2026-02-30'],['date_from'=>'2026-10-21','date_to'=>'2026-10-20'],['status'=>'BAD'],['issue'=>'BAD']] as $input){$rejected=false;try{admin_session_filters($input);}catch(InvalidArgumentException $e){$rejected=true;}admin_check($rejected,'Invalid list/export filter is rejected');}
admin_check((int)$db->query('SELECT COUNT(*) FROM parking_sessions')->fetchColumn()===5,'Report checks leave source records untouched');
