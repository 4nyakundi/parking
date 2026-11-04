<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../config/database.php';
$db=get_db();
foreach(['registered_vehicles','parking_sessions','audit_log','users'] as $table) {
    $db->exec("CREATE TEMPORARY TABLE test_template_{$table} LIKE {$table}");
    $db->exec("CREATE TEMPORARY TABLE {$table} LIKE test_template_{$table}");
}
$db->exec("INSERT INTO users (id,full_name,role,is_active) VALUES (1,'Test Admin','admin',1)");
$db->exec('INSERT INTO registered_vehicles (id,plate_number,owner_name,phone,category,driver_name,driver_phone) VALUES (1,"KDA123A","Owner","254711111111","vip","Alice","254722222222")');
session_save_path(sys_get_temp_dir()); session_id('databank-api-test-'.bin2hex(random_bytes(8))); session_start();
$sessionFile=session_save_path().DIRECTORY_SEPARATOR.'sess_'.session_id();
$_SESSION=['user_id'=>1,'role'=>'admin','csrf_token'=>'test-token','last_activity'=>time()];
$case=$argv[1] ?? 'save';
$_SERVER['REQUEST_METHOD']='POST';
$_SERVER['HTTP_X_CSRF_TOKEN']=$case==='csrf' ? 'wrong-token' : 'test-token';
$_POST=['action'=>$case==='reset' ? 'reset' : 'save','id'=>1,'driver_name'=>'Bob','driver_phone'=>'254733333333','default_destination'=>'Shop A'];
ob_start();
register_shutdown_function(function() use($case,$db,$sessionFile) {
    $result=json_decode(ob_get_clean(),true);
    $row=$db->query('SELECT * FROM registered_vehicles WHERE id=1')->fetch();
    $good=$case==='csrf' ? empty($result['ok']) && $row['driver_name']==='Alice' : !empty($result['ok']);
    if($case==='save') $good=$good && $row['driver_name']==='Bob' && $row['owner_name']==='Owner' && $row['category']==='vip';
    if($case==='reset') $good=$good && $row['driver_name']==='' && !empty($row['profile_reset_at']) && $row['category']==='vip';
    if(session_status()===PHP_SESSION_ACTIVE) session_write_close();
    if(is_file($sessionFile)) unlink($sessionFile);
    echo ($good ? 'PASS: ' : 'FAIL: ').'Data Bank admin '.$case.' with temporary tables'.PHP_EOL;
    if(!$good) exit(1);
});
require __DIR__ . '/../api/admin/databank.php';
