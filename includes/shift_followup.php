<?php
declare(strict_types=1);

/** Reuse an open shift; otherwise attach this sign-in to one recorded shift. */
function parking_use_current_shift(PDO $db,array $user,?string $signedInAt=null): array
{
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT id,last_login_at FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);$account=$q->fetch();
        if(!$account)throw new DomainException('Account unavailable.');
        $q=$db->prepare('SELECT * FROM parking_shifts WHERE guard_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE');$q->execute([$user['id']]);$shift=$q->fetch();
        if(!$shift){
            $now=(string)$db->query('SELECT NOW()')->fetchColumn();
            $start=$signedInAt??$account['last_login_at']??$now;
            if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$start)||$start>$now)$start=$now;
            $q=$db->prepare('SELECT MAX(ended_at) FROM parking_shifts WHERE guard_id=?');$q->execute([$user['id']]);$ended=$q->fetchColumn();
            if($ended && $ended>=$start)throw new DomainException('Your handover is complete. Sign in again for the next shift.');
            $count=shift_boundary_count($db,$start);
            $db->prepare('INSERT INTO parking_shifts(guard_id,started_at,opening_count) VALUES(?,?,?)')->execute([$user['id'],$start,$count]);
            $id=(int)$db->lastInsertId();
            operation_audit($db,$user,'START_SHIFT','parking_shifts',(string)$id,['opening_count'=>$count,'source'=>'current_sign_in']);
            $q=$db->prepare('SELECT * FROM parking_shifts WHERE id=?');$q->execute([$id]);$shift=$q->fetch();
        }
        $db->commit();return $shift;
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Authorize one change with an existing active supervisor/admin PIN. */
function parking_supervisor_code(PDO $db,array $operator,array $input): array
{
    $pin=(string)($input['supervisor_pin']??'');
    if(!preg_match('/^[0-9]{4,6}$/',$pin))throw new DomainException('Enter the supervisor’s 4–6 digit code to approve this change.');
    $db->beginTransaction();
    try {
    // Share the retry limit across the guard's sessions and concurrent requests.
    $lock=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$lock->execute([$operator['id']]);
    if(!$lock->fetchColumn())throw new DomainException('Account unavailable.');
    $q=$db->prepare("SELECT COUNT(*) FROM audit_log WHERE user_id=? AND action='PARKING_CODE_FAILED' AND created_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE)");
    $q->execute([$operator['id']]);
    if((int)$q->fetchColumn()>=5)throw new DomainException('Too many incorrect supervisor codes. Wait five minutes before retrying.');
    $q=$db->query("SELECT id,full_name,role,pin_hash FROM users WHERE is_active=1 AND role IN ('supervisor','admin') AND pin_hash IS NOT NULL");
    foreach($q->fetchAll() as $account){
        if(password_verify($pin,$account['pin_hash'])){unset($account['pin_hash']);$db->commit();return $account;}
    }
    operation_audit($db,$operator,'PARKING_CODE_FAILED','parking_operations',(string)$operator['id'],['reason'=>'invalid_supervisor_code']);
    $db->commit();
    throw new DomainException('Supervisor code is incorrect or the supervisor account is inactive.');
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
