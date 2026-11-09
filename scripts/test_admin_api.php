<?php
/** One isolated endpoint check per invocation. No live table writes or external commands. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../config/database.php';
$db=get_db();
foreach(['parking_shifts','users','system_settings','audit_log','parking_sessions','visitors','destinations','registered_vehicles','device_health','backups','whatsapp_queue'] as $table){
    $db->exec("CREATE TEMPORARY TABLE admin_api_template_{$table} LIKE {$table}");
    $db->exec("CREATE TEMPORARY TABLE {$table} LIKE admin_api_template_{$table}");
}
$db->exec("INSERT INTO users(id,username,full_name,role,is_active) VALUES(1,'test-admin','Test Admin','admin',1),(2,'test-supervisor','Test Supervisor','supervisor',1)");
$db->exec("INSERT INTO system_settings(`key`,value) VALUES('capacity','60'),('overstay_hours','8')");
$db->exec("INSERT INTO parking_sessions(id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,exit_time,status) VALUES
    (1,'TEST-1','KDA123A','Test Driver','254700000000','Shop','2026-10-19 22:00:00',NULL,'ACTIVE'),
    (2,'TEST-2','KDA124A','Test Driver','254700000000','Shop','2026-10-20 10:00:00','2026-10-20 11:00:00','COMPLETED')");
session_save_path(sys_get_temp_dir());session_id('admin-api-test-'.bin2hex(random_bytes(8)));session_start();
$sessionFile=session_save_path().DIRECTORY_SEPARATOR.'sess_'.session_id();
$_SESSION=['user_id'=>1,'role'=>'admin','full_name'=>'Test Admin','csrf_token'=>'test-token','last_activity'=>time()];
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_X_CSRF_TOKEN']='test-token';
$_GET=[];$_POST=[];$case=$argv[1]??'settings';$endpoint='settings.php';
switch($case){
    case 'login_reuses_shift':
    case 'login_creates_shift':
    case 'login_wrong_pin':
        $db->prepare("INSERT INTO users(id,full_name,role,pin_hash) VALUES(3,'Shift Guard','guard',?)")->execute([password_hash('123456',PASSWORD_BCRYPT)]);
        if($case==='login_reuses_shift')$db->exec("INSERT INTO parking_shifts(id,guard_id,started_at,opening_count) VALUES(9,3,DATE_SUB(NOW(),INTERVAL 1 HOUR),1)");
        $endpoint='../auth/login.php';$_POST=['type'=>'pin','pin'=>$case==='login_wrong_pin'?'888888':'123456'];break;
    case 'operations_adopt_method':$endpoint='../guard/operations.php';$_GET=['action'=>'use_current_shift'];$_SERVER['REQUEST_METHOD']='GET';break;
    case 'operations_adopt_csrf':$endpoint='../guard/operations.php';$_GET=['action'=>'use_current_shift'];$_SERVER['HTTP_X_CSRF_TOKEN']='wrong';break;
    case 'operations_method':$endpoint='../guard/operations.php';$_GET=['action'=>'start_shift'];$_SERVER['REQUEST_METHOD']='GET';break;
    case 'operations_csrf':$endpoint='../guard/operations.php';$_GET=['action'=>'start_shift'];$_SERVER['HTTP_X_CSRF_TOKEN']='wrong';break;
    case 'supervisor_guard_activation':$db->exec("INSERT INTO users(id,full_name,role) VALUES(3,'Guard','guard')");$endpoint='users.php';$_SESSION['user_id']=2;$_GET=['action'=>'toggle_active'];$_POST=['id'=>3];break;
    case 'duplicate_guard_pin':$db->prepare("INSERT INTO users(id,full_name,role,pin_hash) VALUES(3,'Existing Guard','guard',?)")->execute([password_hash('834672',PASSWORD_BCRYPT)]);$endpoint='users.php';$_SESSION['user_id']=2;$_GET=['action'=>'save'];$_POST=['full_name'=>'New Guard','role'=>'guard','pin'=>'834672'];break;
    case 'supervisor_guard_create':$endpoint='users.php';$_SESSION['user_id']=2;$_GET=['action'=>'save'];$_POST=['full_name'=>'New Guard','role'=>'guard','pin'=>'834672','phone'=>'0712345678'];break;
    case 'supervisor_guard_edit':$db->exec("INSERT INTO users(id,full_name,role) VALUES(3,'Old Guard','guard')");$endpoint='users.php';$_SESSION['user_id']=2;$_GET=['action'=>'save'];$_POST=['id'=>3,'full_name'=>'Updated Guard','role'=>'guard','pin'=>'834672'];break;
    case 'supervisor_promote':$endpoint='users.php';$_SESSION['user_id']=2;$_GET=['action'=>'save'];$_POST=['full_name'=>'Escalate','role'=>'admin','pin'=>'834672'];break;
    case 'missing_guard_pin':$endpoint='users.php';$_SESSION['user_id']=2;$_GET=['action'=>'save'];$_POST=['full_name'=>'No login','role'=>'guard'];break;
    case 'missing_guard':$endpoint='users.php';$_GET=['action'=>'save'];$_POST=['id'=>999,'full_name'=>'Ghost','role'=>'guard'];break;
    case 'settings':$_GET=['action'=>'update'];$_POST=['settings'=>['capacity'=>'75','printer_name'=>'TestPrinter','printer_paper_width'=>'80','overstay_hours'=>'8']];break;
    case 'invalid_settings':$_GET=['action'=>'update'];$_POST=['settings'=>['capacity'=>'0','printer_name'=>'Bad']];break;
    case 'csrf':$_GET=['action'=>'update'];$_POST=['settings'=>['capacity'=>'75']];$_SERVER['HTTP_X_CSRF_TOKEN']='wrong';break;
    case 'get_mutation':$endpoint='backups.php';$_GET=['action'=>'create'];$_SERVER['REQUEST_METHOD']='GET';break;
    case 'supervisor_activation':$endpoint='users.php';$_SESSION['user_id']=2;$_SESSION['role']='supervisor';$_GET=['action'=>'toggle_active'];$_POST=['id'=>1];break;
    case 'role_refresh':$db->exec("UPDATE users SET role='supervisor' WHERE id=1");$_GET=['action'=>'update'];$_POST=['settings'=>['capacity'=>'75']];break;
    case 'revoked':$endpoint='users.php';$db->exec('UPDATE users SET is_active=0 WHERE id=1');$_GET=['action'=>'list'];$_SERVER['REQUEST_METHOD']='GET';break;
    case 'signoff':$endpoint='reconcile.php';$_GET=['action'=>'shift_signoff'];$_POST=['physical_count'=>0,'system_count'=>999,'notes'=>'Test signoff'];break;
    case 'invalid_signoff':$endpoint='reconcile.php';$_GET=['action'=>'shift_signoff'];$_POST=['physical_count'=>-1];break;
    case 'destination':$endpoint='destinations.php';$_GET=['action'=>'save'];$_POST=['name'=>'Test Shop','unit_code'=>'T1','floor_level'=>'Ground Floor','category'=>'Retail','sort_order'=>2,'is_active'=>true];break;
    case 'privacy_plate':$endpoint='dpa.php';$_SERVER['REQUEST_METHOD']='GET';$_GET=['q'=>'ABCD'];$db->exec("INSERT INTO visitors(plate_number,driver_name,driver_phone,destination) VALUES('UNRELATED','Somebody','','Shop')");break;
    case 'privacy_phone':$endpoint='dpa.php';$_SERVER['REQUEST_METHOD']='GET';$_GET=['q'=>'0712345678'];$db->exec("INSERT INTO registered_vehicles(plate_number,driver_name,driver_phone) VALUES('KDA999A','Saved Driver','254712345678')");break;
    case 'reports':$endpoint='reports.php';$_SERVER['REQUEST_METHOD']='GET';$_GET=['date_from'=>'2026-10-20','date_to'=>'2026-10-20'];break;
    case 'overview':$endpoint='overview.php';$_SERVER['REQUEST_METHOD']='GET';break;
    case 'hourly':$endpoint='../stats/hourly.php';$_SERVER['REQUEST_METHOD']='GET';$db->exec("INSERT INTO parking_sessions(id,ticket_id,plate_number,driver_name,driver_phone,destination,entry_time,status) VALUES(3,'TEST-3','KDA125A','Night Driver','','Shop',CONCAT(CURDATE(),' 01:00:00'),'ACTIVE')");break;
    default:throw new RuntimeException('Unknown test case');
}
ob_start();
register_shutdown_function(function()use($db,$case,$sessionFile){
    $output=ob_get_clean();$result=json_decode($output,true);$status=http_response_code()?:200;$good=is_array($result);
    $capacity=(string)$db->query("SELECT value FROM system_settings WHERE `key`='capacity'")->fetchColumn();
    if(in_array($case,['login_reuses_shift','login_creates_shift','supervisor_guard_activation','supervisor_guard_create','supervisor_guard_edit','settings','signoff','destination','reports','overview','hourly','privacy_plate','privacy_phone'],true))$good=$good&&!empty($result['ok']);
    else $good=$good&&empty($result['ok']);
    if($case==='login_reuses_shift')$good=$good&&$_SESSION['user_id']===3&&$_SESSION['parking_shift_id']===9&&(int)$db->query('SELECT COUNT(*) FROM parking_shifts')->fetchColumn()===1;
    if($case==='login_creates_shift')$good=$good&&$_SESSION['user_id']===3&&!empty($_SESSION['signed_in_at'])&&(int)$db->query('SELECT COUNT(*) FROM parking_shifts')->fetchColumn()===1;
    if($case==='login_wrong_pin')$good=$good&&(int)$db->query('SELECT COUNT(*) FROM parking_shifts')->fetchColumn()===0;
    if($case==='operations_adopt_method')$good=$good&&$status===405;
    if($case==='operations_adopt_csrf')$good=$good&&$status===403;
    if($case==='operations_method')$good=$good&&$status===405;
    if($case==='operations_csrf')$good=$good&&$status===403;
    if($case==='supervisor_guard_activation')$good=$good&&(int)$db->query('SELECT is_active FROM users WHERE id=3')->fetchColumn()===0;
    if($case==='duplicate_guard_pin')$good=$good&&$status===422&&(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===3;
    if($case==='supervisor_guard_create'){$row=$db->query("SELECT * FROM users WHERE full_name='New Guard'")->fetch();$good=$good&&$row&&$row['phone']==='254712345678'&&password_verify('834672',$row['pin_hash']);}
    if($case==='supervisor_guard_edit')$good=$good&&$db->query('SELECT full_name FROM users WHERE id=3')->fetchColumn()==='Updated Guard';
    if($case==='supervisor_promote')$good=$good&&$status===403&&(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===2;
    if($case==='missing_guard_pin'||$case==='missing_guard')$good=$good&&$status===422&&(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===2;
    if($case==='settings')$good=$good&&$capacity==='75'&&(int)$db->query("SELECT COUNT(*) FROM audit_log WHERE action='UPDATE_SYSTEM_SETTINGS'")->fetchColumn()===1;
    if(in_array($case,['invalid_settings','csrf','role_refresh'],true))$good=$good&&$capacity==='60';
    if(in_array($case,['csrf','supervisor_activation','role_refresh'],true))$good=$good&&$status===403;
    if($case==='get_mutation')$good=$good&&$status===405;
    if($case==='revoked')$good=$good&&$status===401;
    if($case==='supervisor_activation')$good=$good&&(int)$db->query('SELECT is_active FROM users WHERE id=1')->fetchColumn()===1;
    if($case==='signoff'){$audit=json_decode((string)$db->query("SELECT new_value FROM audit_log WHERE action='SUPERVISOR_SHIFT_SIGNOFF'")->fetchColumn(),true);$good=$good&&$result['delta']===1&&$audit['system_count']===1;}
    if($case==='invalid_signoff')$good=$good&&(int)$db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn()===0;
    if($case==='destination')$good=$good&&(int)$db->query('SELECT COUNT(*) FROM destinations')->fetchColumn()===1;
    if($case==='reports')$good=$good&&$result['data']['summary']['total_entries']===1&&$result['data']['summary']['total_exits']===1;
    if($case==='overview')$good=$good&&$result['data']['occupied']===1&&$result['data']['overstay_hours']===2;
    if($case==='privacy_plate')$good=$good&&count($result['data']['visitor_requests'])===0&&count($result['data']['parking_sessions'])===0;
    if($case==='privacy_phone')$good=$good&&count($result['data']['registered_vehicles'])===1;
    if($case==='hourly')$good=$good&&count($result['data']['labels'])===24&&$result['data']['entries'][1]===1;
    $currentSessionFile=session_save_path().DIRECTORY_SEPARATOR.'sess_'.session_id();if(session_status()===PHP_SESSION_ACTIVE)session_write_close();if(is_file($sessionFile))unlink($sessionFile);if($currentSessionFile!==$sessionFile&&is_file($currentSessionFile))unlink($currentSessionFile);
    echo ($good?'PASS: ':'FAIL: ').'Admin API '.$case.' with temporary tables'.PHP_EOL;
    if(!$good){echo substr($output,0,1000).PHP_EOL;exit(1);}
});
require __DIR__.'/../api/admin/'.$endpoint;
