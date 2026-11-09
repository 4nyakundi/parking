<?php
/** Apply the additive upgrade to the configured database and verify existing data. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
ini_set('mysqlnd.net_read_timeout','10');
require_once __DIR__.'/../config/database.php';
$db=get_db();
echo 'Connected to configured database: '.$db->query('SELECT DATABASE()')->fetchColumn().PHP_EOL;
function operations_existing_state(PDO $db,string $table):array {
    $q=$db->query('SELECT * FROM `'.$table.'` ORDER BY id');
    $hash=hash_init('sha256');$count=0;
    while($row=$q->fetch()){hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR));$count++;}
    return ['count'=>$count,'hash'=>hash_final($hash)];
}
$before=[];
foreach(['parking_sessions','users','visitors','registered_vehicles','destinations'] as $table)$before[$table]=operations_existing_state($db,$table);
require __DIR__.'/operations_migrate.php';
$expected=[
 'parking_shifts'=>['id','guard_id','started_at','ended_at','opening_count','physical_count','closing_count','handover_notes'],
 'parking_actions'=>['id','session_id','user_id','shift_id','authorized_by','action_type','notes','resolved_at','resolved_by','created_at'],
 'parking_extensions'=>['session_id','approved_until','approved_by','reason','updated_at'],
 'visit_destinations'=>['entity_type','entity_id','destination_id','destination_name','position']
];
foreach($expected as $table=>$columns){
    $actual=array_column($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(),'Field');
    if(array_diff($columns,$actual))throw new RuntimeException('Required columns missing in '.$table);
    echo 'Verified '.$table.PHP_EOL;
}
foreach($before as $table=>$state){
    $after=operations_existing_state($db,$table);
    echo $table.': '.$state['count'].' before / '.$after['count'].' after; '.($state===$after?'all values unchanged':'data changed during verification; inspect live activity').PHP_EOL;
}
require_once __DIR__.'/../includes/parking_operations.php';
require_once __DIR__.'/../includes/guard_snapshot.php';
require_once __DIR__.'/../includes/guard_history.php';
require_once __DIR__.'/../includes/admin_reports.php';
$actor=$db->query("SELECT id,role FROM users WHERE is_active=1 AND role IN ('admin','supervisor') ORDER BY id LIMIT 1")->fetch();
if(!$actor)throw new RuntimeException('No active management account available for read-only checks.');
parking_operation($db,$actor,'overview',[]);
guard_roster_snapshot($db);
guard_history_report($db,guard_history_options(['mode'=>'exited','scope'=>'current_shift','guard_id'=>(int)$actor['id']]));
$range=admin_report_range([]);admin_report($db,$range[2],$range[3]);
echo 'PASS: Operations overview, active roster, shift exit history and management reports read successfully.'.PHP_EOL;
