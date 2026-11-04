<?php
declare(strict_types=1);
function admin_record_signoff(PDO $db,array $user,mixed $physical,string $notes): array
{
    if (filter_var($physical,FILTER_VALIDATE_INT)===false || $physical===null || (int)$physical<0) throw new InvalidArgumentException('Enter a valid physical vehicle count.');
    $db->beginTransaction();
    try {
        $system=(int)$db->query("SELECT COUNT(*) FROM parking_sessions WHERE status='ACTIVE'")->fetchColumn();
        $details=['supervisor'=>$user['full_name'],'physical_count'=>(int)$physical,'system_count'=>$system,
            'drift_delta'=>$system-(int)$physical,'notes'=>$notes,'timestamp'=>date('Y-m-d H:i:s')];
        // A sign-off is successful only if its audit record was actually saved.
        $q=$db->prepare("INSERT INTO audit_log(user_id,action,entity,entity_id,old_value,new_value,ip,created_at) VALUES(?,'SUPERVISOR_SHIFT_SIGNOFF','system','DECK_AUDIT',NULL,?,?,NOW())");
        $q->execute([$user['id'],json_encode($details,JSON_THROW_ON_ERROR),substr($_SERVER['REMOTE_ADDR']??'CLI',0,45)]);
        $db->commit(); return $details;
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}
