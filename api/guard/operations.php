<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/admin_actions.php';
require_once __DIR__.'/../../includes/parking_operations.php';
$user=require_auth();
$user['signed_in_at']=$_SESSION['signed_in_at']??null;
$input=json_decode(file_get_contents('php://input'),true)??$_POST;
$action=(string)($_GET['action']??$input['action']??'overview');
admin_require_mutation($action,['use_current_shift','start_shift','end_shift','log_action','resolve_action','extend','destinations','blacklist']);
try {
    if($action==='use_current_shift'&&!empty($_SESSION['parking_shift_ended']))throw new DomainException('Handover saved. Sign in again for the next shift.');
    $result=parking_operation(get_db(),$user,$action,array_merge($_GET,$input));
    if($action==='end_shift')$_SESSION['parking_shift_ended']=true;
    if($action==='overview')$result['handover_complete']=!empty($_SESSION['parking_shift_ended']);
    echo json_encode(['ok'=>true,'data'=>$result]);
}
catch(Throwable $e) {
    http_response_code($e instanceof DomainException?403:($e instanceof InvalidArgumentException?422:500));
    error_log('OPERATIONS: '.$e->getMessage());
    echo json_encode(['ok'=>false,'error'=>$e instanceof LogicException?$e->getMessage():'Could not load or save operations. Ensure the operations database upgrade has been installed.']);
}
