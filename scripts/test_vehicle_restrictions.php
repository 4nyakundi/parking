<?php
/** Restriction workflows use connection-local temporary tables only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/parking_operations.php';
$db=get_db();
foreach(['users','parking_sessions','registered_vehicles','parking_actions','parking_shifts','parking_extensions','visit_destinations','destinations','visitors','alpr_detections','audit_log','events','device_health'] as $table){$db->exec("CREATE TEMPORARY TABLE restriction_template_{$table} LIKE {$table}");$db->exec("CREATE TEMPORARY TABLE {$table} LIKE restriction_template_{$table}");}
$db->exec("INSERT INTO users(id,full_name,role) VALUES(1,'Test Guard','guard'),(2,'Test Supervisor','supervisor')");
foreach([1=>'123456',2=>'834672'] as $id=>$pin)$db->prepare('UPDATE users SET pin_hash=? WHERE id=?')->execute([password_hash($pin,PASSWORD_BCRYPT),$id]);
$db->exec("INSERT INTO parking_sessions(id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,status) VALUES(1,'RESTRICT-1','KDA123A','Visitor','','Shop',DATE_SUB(NOW(),INTERVAL 3 HOUR),'ACTIVE')");
$db->exec("INSERT INTO registered_vehicles(plate_number,owner_name,driver_name,category,visit_count,notes) VALUES('KDA123A','Existing owner','Saved driver','vip',7,'Existing profile note')");
$guard=['id'=>1,'role'=>'guard'];$supervisor=['id'=>2,'role'=>'supervisor'];$approval=['supervisor_pin'=>'834672','session_id'=>1];
parking_operation($db,$guard,'start_shift',[]);
function restriction_check(bool $good,string $label):void{if(!$good)throw new RuntimeException($label);echo "PASS: {$label}\n";}
function restriction_reject(callable $call,string $label):void{try{$call();}catch(LogicException $e){restriction_check(true,$label);return;}throw new RuntimeException($label);}
$case=$argv[1]??'core';
if($case==='core'){
    parking_operation($db,$guard,'log_action',$approval+['type'=>'clamped','notes'=>'Clamp C-12 fitted after obstruction']);
    $policy=vehicle_exit_policy($db,'KDA123A',1);restriction_check($policy['is_clamped'],'Clamp status is derived from unresolved clamp records');
    restriction_reject(fn()=>vehicle_require_departure($policy,$supervisor,'Release allowed'),'A supervisor code alone cannot bypass an active clamp');
    restriction_reject(fn()=>parking_operation($db,$guard,'log_action',$approval+['type'=>'clamped','notes'=>'Duplicate']),'Duplicate active clamps are rejected');
    $id=(int)$db->query("SELECT id FROM parking_actions WHERE action_type='clamped'")->fetchColumn();
    restriction_reject(fn()=>parking_operation($db,$guard,'resolve_action',$approval+['action_id'=>$id]),'Clamp release requires recorded release details');
    parking_operation($db,$guard,'resolve_action',$approval+['action_id'=>$id,'notes'=>'Clamp C-12 physically removed by guard, supervisor verified']);
    restriction_check(!vehicle_exit_policy($db,'KDA123A',1)['is_clamped'],'Approved clamp release removes the exit hold');
    restriction_check((int)$db->query("SELECT COUNT(*) FROM parking_actions WHERE action_type='clamp_released' AND authorized_by=2 AND shift_id IS NOT NULL")->fetchColumn()===1,'Clamp release retains its shift and approving supervisor');
    restriction_reject(fn()=>parking_operation($db,$guard,'blacklist',['session_id'=>1,'notes'=>'No approval']),'Blacklisting requires supervisor approval');
    parking_operation($db,$guard,'blacklist',$approval+['notes'=>'Repeated obstruction and abusive behaviour recorded by guard']);
    $profile=$db->query("SELECT * FROM registered_vehicles WHERE plate_number='KDA123A'")->fetch();
    restriction_check($profile['category']==='blacklisted'&&$profile['driver_name']==='Saved driver'&&(int)$profile['visit_count']===7,'Blacklisting preserves existing driver profile and visit history');
    restriction_check(str_contains($profile['notes'],'Existing profile note'),'Blacklisting preserves prior profile notes');
    $policy=vehicle_exit_policy($db,'KDA123A',1);restriction_reject(fn()=>vehicle_require_departure($policy,null,'Reason'),'Blacklisted departures require an approving supervisor');
    restriction_reject(fn()=>vehicle_require_departure($policy,$supervisor,''),'Blacklisted departures require an authorization reason');
    vehicle_require_departure($policy,$supervisor,'Supervisor directs vehicle to leave');restriction_check(true,'Supervisor can authorize a blacklisted vehicle to leave after clamp release');
    restriction_check(vehicle_exit_policy($db,'KDA123A')['is_blacklisted'],'Blacklist persists independently of the current parking visit');
    $db->beginTransaction();vehicle_complete_departure($db,$guard,1,$supervisor,'Supervisor directed the vehicle to leave',null);$db->commit();
    restriction_check($db->query('SELECT status FROM parking_sessions WHERE id=1')->fetchColumn()==='COMPLETED','Supervisor-approved departure completes the visit after clamp release');
    $audit=json_decode((string)$db->query("SELECT new_value FROM audit_log WHERE action='CLEAR_EXIT' ORDER BY id DESC LIMIT 1")->fetchColumn(),true);
    restriction_check($audit['authorized_by']===2&&$audit['departure_reason']==='Supervisor directed the vehicle to leave','Authorized departure preserves supervisor identity and reason');
    restriction_check(vehicle_exit_policy($db,'KDA123A')['is_blacklisted'],'Authorizing one departure does not remove the blacklist');
    exit;
}
if(str_contains($case,'blacklist')||$case==='batch_restricted')$db->exec("UPDATE registered_vehicles SET category='blacklisted' WHERE plate_number='KDA123A'");
if(str_contains($case,'clamp'))parking_operation($db,$guard,'log_action',$approval+['type'=>'clamped','notes'=>'Clamp applied']);
if(str_contains($case,'incident')||$case==='exit_guard_code')parking_operation($db,$guard,'log_action',$approval+['type'=>'incident','notes'=>'Incident under review']);
if(in_array($case,['camera_blacklist_unknown','camera_blacklist_entrance_pending','exit_blacklist_unknown_authorized'],true))$db->exec("UPDATE parking_sessions SET status='COMPLETED',exit_time=DATE_SUB(NOW(),INTERVAL 1 HOUR) WHERE id=1");
if($case==='camera_blacklist_entrance_pending')$db->exec("INSERT INTO visitors(plate_number,driver_name,driver_phone,destination,status) VALUES('KDA123A','Visitor','','Shop','PENDING')");
session_save_path(sys_get_temp_dir());session_id('vehicle-restriction-'.bin2hex(random_bytes(8)));session_start();
$sessionPath=session_save_path().DIRECTORY_SEPARATOR.'sess_'.session_id();
$_SESSION=['user_id'=>1,'role'=>'guard','full_name'=>'Test Guard','last_activity'=>time(),'csrf_token'=>'test-token'];
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_X_CSRF_TOKEN']='test-token';$_GET=[];
$endpoint='gate/clear-exit.php';$_POST=['session_id'=>1];
if(str_starts_with($case,'camera_')){$endpoint='gate/plate-detected.php';$_POST=['camera'=>$case==='camera_blacklist_entrance_pending'?'entrance':'exit','plate'=>'KDA123A'];}
elseif($case==='exit_blacklist_unknown_authorized')$_POST=['plate_number'=>'KDA123A','supervisor_pin'=>'834672','departure_reason'=>'Supervisor directed this blacklisted vehicle to leave'];
elseif($case==='exit_clamp_blocked_with_code')$_POST=$approval+['departure_reason'=>'Supervisor approved'];
elseif($case==='exit_guard_code')$_POST=['session_id'=>1,'supervisor_pin'=>'123456','departure_reason'=>'Attempt'];
elseif($case==='exit_completed_no_duplicate')$db->exec("UPDATE parking_sessions SET status='COMPLETED',exit_time=NOW() WHERE id=1");
elseif(str_starts_with($case,'reconcile_')||$case==='batch_restricted'){
    $_SESSION['user_id']=2;$_SESSION['role']='supervisor';$endpoint='admin/reconcile.php';$_GET=['action'=>'force_close'];$_POST=['session_id'=>1,'reason'=>'Physical count'];
    if($case==='batch_restricted'){$db->exec("INSERT INTO parking_sessions(id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time) VALUES(2,'RESTRICT-2','KDA124A','Other','','Shop',NOW())");$_GET=['action'=>'batch_reconcile'];$_POST=['session_ids'=>[1,2],'reason'=>'Physical count'];}
}elseif(in_array($case,['admit_blacklist','manual_blacklist'],true)){$endpoint=$case==='admit_blacklist'?'gate/approve-session.php':'gate/manual-checkin.php';$_POST=['plate_number'=>'KDA123A','driver_name'=>'Driver','driver_phone'=>'','destination'=>'Shop'];}
ob_start();register_shutdown_function(function()use($db,$case,$sessionPath){
    $output=ob_get_clean();$json=json_decode($output,true);$status=http_response_code()?:200;
    $visit=$db->query('SELECT status FROM parking_sessions WHERE id=1')->fetchColumn();$count=(int)$db->query('SELECT COUNT(*) FROM parking_sessions')->fetchColumn();$good=is_array($json);
    if(str_starts_with($case,'camera_')){
        $good=$good&&!empty($json['ok']);$alerts=$db->query("SELECT payload FROM events WHERE type='vehicle_restricted'")->fetchAll();
        if($case==='camera_normal_uncollected')$good=$good&&$visit==='COMPLETED'&&!$alerts;
        else{$good=$good&&count($alerts)===1&&$count===1;if(!in_array($case,['camera_blacklist_unknown','camera_blacklist_entrance_pending'],true))$good=$good&&$visit==='ACTIVE';$good=$good&&count(vehicle_recent_alerts($db))===1;}
    }elseif($case==='exit_blacklist_unknown_authorized'){$good=$good&&!empty($json['ok'])&&$count===2;$last=$db->query('SELECT * FROM parking_sessions ORDER BY id DESC LIMIT 1')->fetch();$good=$good&&(int)$last['cleared_by']===1&&(int)$last['approved_by']===2&&$last['status']==='COMPLETED'&&vehicle_exit_policy($db,'KDA123A')['is_blacklisted'];}
    elseif($case==='batch_restricted')$good=$good&&!empty($json['ok'])&&$json['restricted']===1&&$json['cleared']===1&&$visit==='ACTIVE';
    else{$good=$good&&empty($json['ok'])&&in_array($status,[403,422],true)&&$count===1;if($case==='exit_completed_no_duplicate')$good=$good&&$visit==='COMPLETED';else $good=$good&&$visit==='ACTIVE';}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();if(is_file($sessionPath))unlink($sessionPath);
    echo ($good?'PASS: ':'FAIL: ').$case.' with temporary tables'.PHP_EOL;if(!$good){echo substr($output,0,1500).PHP_EOL;exit(1);}
});
require __DIR__.'/../api/'.$endpoint;
