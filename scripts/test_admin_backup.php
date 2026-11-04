<?php
/** Exercise backup + recovery using two randomly named, isolated sample databases. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$fixtureConfig=require __DIR__.'/../config/config.php';
if(file_exists(__DIR__.'/../config/config.local.php'))$fixtureConfig=array_replace_recursive($fixtureConfig,require __DIR__.'/../config/config.local.php');
$dbCfg=$fixtureConfig['db'];
$bootstrap=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$dbCfg['host'],$dbCfg['port'],$dbCfg['dbname']),$dbCfg['username'],$dbCfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$suffix=bin2hex(random_bytes(6));$sample='admin_backup_test_'.$suffix;$restored='admin_restore_test_'.$suffix;
$bootstrap->exec("CREATE DATABASE `{$sample}`");$bootstrap->exec("CREATE DATABASE `{$restored}`");
foreach(['users','backups','audit_log'] as $table)$bootstrap->exec("CREATE TABLE `{$sample}`.`{$table}` LIKE `{$dbCfg['dbname']}`.`{$table}`");
$bootstrap->exec("CREATE TABLE `{$sample}`.backup_probe(id INT PRIMARY KEY,value VARCHAR(100))");
$bootstrap->exec("INSERT INTO `{$sample}`.backup_probe VALUES(1,'isolated recovery sample')");
$bootstrap->exec("INSERT INTO `{$sample}`.users(id,full_name,role,is_active) VALUES(1,'Backup Test Admin','admin',1)");
$fixtureConfig['db']['dbname']=$sample;
$fixtureConfig['paths']['backups']=sys_get_temp_dir().DIRECTORY_SEPARATOR.'unavailable-backup-folder-'.$suffix;
function get_config():array {global $fixtureConfig;return $fixtureConfig;}
session_save_path(sys_get_temp_dir());session_id('admin-backup-test-'.$suffix);session_start();
$sessionFile=session_save_path().DIRECTORY_SEPARATOR.'sess_'.session_id();
$_SESSION=['user_id'=>1,'role'=>'admin','csrf_token'=>'backup-test-token','last_activity'=>time()];
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_X_CSRF_TOKEN']='backup-test-token';$_GET=['action'=>'create'];$_POST=[];
ob_start();
register_shutdown_function(function()use($bootstrap,$sample,$restored,$dbCfg,$sessionFile){
    $result=json_decode(ob_get_clean(),true);$file=$GLOBALS['targetFile']??null;$good=false;
    try {
        if(empty($result['ok'])||!is_string($file)||!is_file($file))throw new RuntimeException('Sample backup API did not complete.');
        $cmd=['C:\\xampp\\mysql\\bin\\mysql.exe','--host='.$dbCfg['host'],'--port='.($dbCfg['port']??3306),'--user='.$dbCfg['username']];
        if($dbCfg['password']!=='')$cmd[]='--password='.$dbCfg['password'];$cmd[]=$restored;
        $process=proc_open($cmd,[0=>['file',$file,'r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Could not start sample recovery.');
        stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);$status=proc_close($process);
        if($status!==0)throw new RuntimeException('Sample recovery failed: '.substr($error,0,200));
        $value=$bootstrap->query("SELECT value FROM `{$restored}`.backup_probe WHERE id=1")->fetchColumn();
        if($value!=='isolated recovery sample')throw new RuntimeException('Recovered sample did not match.');
        if(!str_contains($result['data']['external_copy'],'unavailable'))throw new RuntimeException('Unavailable external copy was not reported.');
        $good=true;echo "PASS: Backup API creates a sample dump and MySQL restores the original sample value\n";
        echo "PASS: Missing external backup folder is reported accurately\n";
    }catch(Throwable $e){echo 'FAIL: '.$e->getMessage().PHP_EOL;}
    finally {
        foreach([$sample,$restored] as $name){
            if(!preg_match('/^admin_(backup|restore)_test_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Invalid isolated database name.');
            $bootstrap->exec("DROP DATABASE `{$name}`");
        }
        if(is_string($file)&&is_file($file)){
            $base=realpath(__DIR__.'/../storage/backups');$resolved=realpath($file);
            if($resolved&&dirname($resolved)===$base&&str_starts_with(basename($resolved),'parking_backup_'))unlink($resolved);
        }
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();if(is_file($sessionFile))unlink($sessionFile);
    }
    if(!$good)exit(1);
});
require __DIR__.'/../api/admin/backups.php';
