<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/parking_operations.php';
require_once __DIR__.'/../includes/guard_snapshot.php';
require_once __DIR__.'/../includes/guard_history.php';
require_once __DIR__.'/../includes/admin_reports.php';
require_once __DIR__.'/../includes/admin_session_filters.php';
$db=get_db();
foreach(['parking_sessions','users','registered_vehicles','destinations','parking_shifts','parking_actions','parking_extensions','visit_destinations','audit_log'] as $table) {
    $db->exec("CREATE TEMPORARY TABLE ops_template_{$table} LIKE {$table}");
    $db->exec("CREATE TEMPORARY TABLE {$table} LIKE ops_template_{$table}");
}
function ops_check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);echo "PASS: {$message}\n";}
function ops_reject(callable $fn,string $message):void {try{$fn();}catch(LogicException $e){ops_check(true,$message);return;}throw new RuntimeException($message);}
$db->exec("INSERT INTO users(id,full_name,role) VALUES(1,'Guard','guard'),(2,'Supervisor','supervisor')");
$db->prepare('UPDATE users SET pin_hash=? WHERE id=2')->execute([password_hash('834672',PASSWORD_BCRYPT)]);
$db->prepare('UPDATE users SET pin_hash=? WHERE id=1')->execute([password_hash('123456',PASSWORD_BCRYPT)]);
$db->exec("INSERT INTO destinations(id,name) VALUES(1,'Shop One'),(2,'Shop Two')");
$db->exec("INSERT INTO parking_sessions(id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,status) VALUES(1,'OPS-1','KDA123A','Driver','','Shop One',DATE_SUB(NOW(),INTERVAL 3 HOUR),'ACTIVE')");
$guard=['id'=>1,'role'=>'guard'];$supervisor=['id'=>2,'role'=>'supervisor'];
parking_operation($db,$guard,'start_shift',[]);
ops_reject(fn()=>parking_operation($db,$guard,'start_shift',[]),'Repeated shift start is rejected');
$adopted=parking_use_current_shift($db,$guard);
$adoptedAgain=parking_use_current_shift($db,$guard);
ops_check($adopted['id']===$adoptedAgain['id']&&(int)$db->query('SELECT COUNT(*) FROM parking_shifts')->fetchColumn()===1,'Current shift is reused across sign-in and follow-up access');
$overview=parking_operation($db,$guard,'overview',[]);
ops_check((int)$overview['current']['opening_count']===1,'Shift inherits existing cars');
ops_check(guard_roster_snapshot($db)['overstay_count']===1,'Three-hour car is initially overdue');
$entry=$db->query('SELECT entry_time FROM parking_sessions WHERE id=1')->fetchColumn();
ops_reject(fn()=>parking_operation($db,$guard,'extend',['session_id'=>1,'minutes'=>30,'notes'=>'Unauthorized']),'Guard cannot approve an extension');
parking_operation($db,$guard,'extend',['supervisor_pin'=>'834672','session_id'=>1,'minutes'=>30,'notes'=>'Customer needs extra time']);
ops_check((int)$db->query('SELECT approved_by FROM parking_extensions WHERE session_id=1')->fetchColumn()===2,'Code approval records the supervisor while the guard stays signed in');
ops_check(guard_roster_snapshot($db)['overstay_count']===0,'Approved extension changes the live overstay calculation');
ops_check($db->query('SELECT entry_time FROM parking_sessions WHERE id=1')->fetchColumn()===$entry,'Extension preserves original entry time');
[$where,$params]=admin_session_filters(['issue'=>'overstay']);$q=$db->prepare('SELECT s.id FROM parking_sessions s WHERE '.$where);$q->execute($params);
ops_check(!$q->fetch(),'Supervisor overstay filter agrees with guard roster');
parking_operation($db,$guard,'log_action',['supervisor_pin'=>'834672','session_id'=>1,'type'=>'incident','notes'=>'Vehicle breakdown']);
$actions=parking_operation($db,$guard,'history',['session_id'=>1])['actions'];
ops_check((int)$actions[0]['shift_id']===(int)$adopted['id']&&(int)$actions[0]['authorized_by']===2,'Incident records the present shift and approving supervisor');
ops_check(count($actions)===1&&$actions[0]['author']==='Guard','Incident retains author and timestamp');
parking_operation($db,$guard,'resolve_action',['supervisor_pin'=>'834672','session_id'=>1,'action_id'=>$actions[0]['id']]);
ops_check(parking_operation($db,$guard,'overview',[])['cases'][0]['open_actions']===0,'Resolved actions no longer count as outstanding');
parking_operation($db,$guard,'destinations',['session_id'=>1,'destinations'=>['Shop One','Shop Two','Shop One']]);
$roster=guard_roster_snapshot($db);
ops_check(count($roster['sessions'])===1&&$roster['sessions'][0]['destination']==='Shop One, Shop Two','Multi-store selection never duplicates a parking visit');
ops_reject(fn()=>parking_operation($db,$guard,'destinations',['session_id'=>1,'destinations'=>['Unknown']]),'Unknown stores are rejected');
ops_check(count(visit_store_rows($db,'session',1))===2,'Failed destination edit preserves previous selections');
$report=admin_report($db,date('Y-m-d 00:00:00',strtotime('-1 day')),date('Y-m-d 00:00:00',strtotime('+1 day')));
ops_check($report['summary']['total_entries']===1&&count($report['destinations'])===2,'Store participation counts both stores while arrivals count once');
ops_reject(fn()=>parking_operation($db,$guard,'end_shift',['supervisor_pin'=>'834672','physical_count'=>0]),'Count discrepancy requires a handover explanation');
parking_operation($db,$guard,'end_shift',['supervisor_pin'=>'834672','physical_count'=>1,'notes'=>'One car with approved extension remains']);
ops_check($db->query('SELECT status FROM parking_sessions WHERE id=1')->fetchColumn()==='ACTIVE','Ending shift does not clear parked cars');
parking_operation($db,$guard,'start_shift',[]);
ops_check(parking_operation($db,$guard,'overview',[])['current']['opening_count']===1,'Next shift inherits remaining car');
parking_operation($db,$guard,'log_action',['supervisor_pin'=>'834672','session_id'=>1,'type'=>'incident','notes'=>'Follow up after departure']);
$db->exec("UPDATE parking_sessions SET status='COMPLETED',exit_time=DATE_ADD(NOW(),INTERVAL 1 SECOND),driver_name='Uncollected Driver' WHERE id=1");
$history=guard_history_report($db,guard_history_options(['mode'=>'exited','scope'=>'current_shift','guard_id'=>1]));
ops_check($history['total']===1&&$history['sessions'][0]['destination']==='Shop One, Shop Two','Shift exits include departures without details and all stores');
ops_check(parking_operation($db,$guard,'overview',[])['cases'][0]['status']==='COMPLETED','Unresolved incidents remain available after departure');
ops_check((int)$db->query("SELECT COUNT(*) FROM audit_log WHERE action='PARKING_EXTEND'")->fetchColumn()===1,'Only successful approvals create audit records');
$db->exec("INSERT INTO parking_sessions(id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,exit_time,status) VALUES
(2,'OPS-2','KDA124A','Boundary arrival','','Shop One','2026-12-01 08:00:00',NULL,'ACTIVE'),
(3,'OPS-3','KDA125A','Boundary exit','','Shop One','2026-12-01 07:00:00','2026-12-01 08:00:00','COMPLETED')");
$opening=shift_boundary_count($db,'2026-12-01 08:00:00');
$balanced=shift_summary($db,['started_at'=>'2026-12-01 08:00:00','ended_at'=>'2026-12-01 09:00:00','opening_count'=>$opening]);
ops_check($opening===1&&$balanced['arrivals']===1&&$balanced['departures']===1&&$balanced['expected_closing']===shift_boundary_count($db,'2026-12-01 09:00:00'),'Shift boundary arrivals and departures balance without double counting');
ops_reject(fn()=>parking_operation($db,$guard,'log_action',['session_id'=>1,'type'=>'incident','notes'=>'No code']),'Saving incidents without supervisor approval is rejected');
ops_reject(fn()=>parking_operation($db,$guard,'resolve_action',['session_id'=>1,'action_id'=>$actions[0]['id'],'supervisor_pin'=>'123456']),'A guard PIN cannot authorize incident management');
$db->exec('UPDATE users SET is_active=0 WHERE id=2');
ops_reject(fn()=>parking_supervisor_code($db,$guard,['supervisor_pin'=>'834672']),'Inactive supervisor codes are rejected');
$db->exec('UPDATE users SET is_active=1 WHERE id=2');
$db->exec("INSERT INTO users(id,full_name,role) VALUES(3,'Rate limited guard','guard')");
for($attempt=0;$attempt<5;$attempt++)ops_reject(fn()=>parking_supervisor_code($db,['id'=>3],['supervisor_pin'=>'999999']),'Incorrect supervisor code is rejected');
ops_reject(fn()=>parking_supervisor_code($db,['id'=>3],['supervisor_pin'=>'834672']),'Five failed codes temporarily block further approval attempts');
$logged=(string)$db->query("SELECT GROUP_CONCAT(new_value) FROM audit_log")->fetchColumn();
ops_check(!str_contains($logged,'834672')&&!str_contains($logged,'123456')&&!str_contains($logged,'999999'),'Audit records never contain entered supervisor or guard codes');
$before=(int)$db->query('SELECT COUNT(*) FROM parking_actions')->fetchColumn();
$db->exec('ALTER TABLE audit_log DROP COLUMN new_value');
$rolledBack=false;
try{parking_operation($db,$guard,'log_action',['supervisor_pin'=>'834672','session_id'=>1,'type'=>'follow_up','notes'=>'Must not save without audit']);}catch(PDOException $e){$rolledBack=true;}
ops_check($rolledBack&&(int)$db->query('SELECT COUNT(*) FROM parking_actions')->fetchColumn()===$before,'An audit failure rolls back the associated action');
echo "All operation checks passed using temporary tables.\n";
