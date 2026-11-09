<?php
// CLI-only isolated database harness. Never connects to the configured parking database.
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
function get_config(): array {
    $cfg=require __DIR__.'/../config/config.php';
    $cfg['db']=['host'=>'127.0.0.1','port'=>3308,'dbname'=>'mombasa_parking','charset'=>'utf8mb4','username'=>'root','password'=>'operations-test-local'];
    return $cfg;
}
require_once __DIR__.'/../config/database.php';
if(($argv[1]??'')==='setup') {
    $db=new PDO('mysql:host=127.0.0.1;port=3308;charset=utf8mb4','root','operations-test-local',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $schema=file_get_contents(__DIR__.'/../database/schema.sql');
    $schema=preg_replace('/DELIMITER \/\/.*?DELIMITER ;/s','',$schema);
    $db->exec($schema);
    $db->exec(file_get_contents(__DIR__.'/../database/upgrade_operations_20261109.sql'));
    echo "Isolated schema ready.\n";exit;
}
$test=$argv[1]??'test_operations.php';
if(!preg_match('/^test_[a-z0-9_]+\.php$/',$test))throw new RuntimeException('Invalid test script');
array_splice($argv,1,1);
require __DIR__.'/'.$test;
