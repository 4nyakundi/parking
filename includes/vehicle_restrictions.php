<?php
declare(strict_types=1);

function vehicle_exit_policy(PDO $db,string $plate,int $sessionId=0,bool $lock=false):array
{
    $q=$db->prepare('SELECT category,notes FROM registered_vehicles WHERE plate_number=? AND is_active=1'.($lock?' FOR UPDATE':''));$q->execute([$plate]);$vehicle=$q->fetch();
    $clamped=false;$incident=false;
    if($sessionId>0){
        $q=$db->prepare("SELECT action_type FROM parking_actions WHERE session_id=? AND resolved_at IS NULL AND action_type IN ('clamped','incident')".($lock?' FOR UPDATE':''));$q->execute([$sessionId]);
        foreach($q->fetchAll() as $row){$clamped=$clamped||$row['action_type']==='clamped';$incident=$incident||$row['action_type']==='incident';}
    }
    $blacklisted=($vehicle['category']??'')==='blacklisted';
    return ['is_blacklisted'=>$blacklisted,'is_clamped'=>$clamped,'has_incident'=>$incident,
        'requires_supervisor'=>$blacklisted||$incident||$clamped,'blacklist_reason'=>$blacklisted?($vehicle['notes']??''):''];
}
function vehicle_require_departure(array $policy,?array $approver,string $reason):void
{
    if($policy['is_clamped'])throw new DomainException('Vehicle is clamped. A supervisor must record clamp release before departure can be authorized.');
    if($policy['requires_supervisor']&&!$approver)throw new DomainException('Supervisor code required to authorize this vehicle to leave.');
    if($policy['requires_supervisor']&&trim($reason)==='')throw new InvalidArgumentException('Enter the reason for authorizing this vehicle to leave.');
    if(mb_strlen($reason)>4000)throw new InvalidArgumentException('Use a departure reason up to 4,000 characters.');
}
function vehicle_blacklist(PDO $db,array $session,string $reason):void
{
    if($reason==='')throw new InvalidArgumentException('Enter the reason for blacklisting this vehicle.');
    $q=$db->prepare('SELECT id,notes FROM registered_vehicles WHERE plate_number=? FOR UPDATE');$q->execute([$session['plate_number']]);$existing=$q->fetch();
    $note='[BLACKLIST '.date('Y-m-d H:i:s').'] '.$reason;
    if($existing){$db->prepare("UPDATE registered_vehicles SET category='blacklisted',is_active=1,notes=? WHERE id=?")->execute([trim(($existing['notes']??'')."\n".$note),$existing['id']]);}
    else{$db->prepare("INSERT INTO registered_vehicles(plate_number,owner_name,phone,driver_name,driver_phone,category,notes,is_active) VALUES(?,?,?,?,?,'blacklisted',?,1)")->execute([$session['plate_number'],$session['driver_name'],$session['driver_phone'],$session['driver_name'],$session['driver_phone'],$note]);}
}
/** Record a standard departure and its approval together; caller commits and notifies. */
function vehicle_complete_departure(PDO $db,array $operator,int $sessionId,?array $approver,string $reason,?string $snapshot):array
{
    if(!$db->inTransaction())throw new LogicException('Departure must be recorded in a transaction.');
    $q=$db->prepare('SELECT * FROM parking_sessions WHERE id=? FOR UPDATE');$q->execute([$sessionId]);$visit=$q->fetch();
    if(!$visit||$visit['status']!=='ACTIVE')throw new DomainException('This visit is already closed. Refresh the list.');
    $policy=vehicle_exit_policy($db,$visit['plate_number'],$sessionId,true);
    vehicle_require_departure($policy,$approver,$reason);
    $minutes=max(0,(int)floor((time()-strtotime($visit['entry_time']))/60));
    $text=intdiv($minutes,60)>0?intdiv($minutes,60).'h '.($minutes%60).'m':$minutes.' mins';
    $q=$db->prepare("UPDATE parking_sessions SET status='COMPLETED',exit_time=NOW(),duration_minutes=?,cleared_by=?,exit_snapshot=?,exit_method='guard_clear',synced_to_cloud=0 WHERE id=? AND status='ACTIVE'");
    $q->execute([$minutes,$operator['id'],$snapshot,$sessionId]);
    if(!$q->rowCount())throw new DomainException('This visit was already cleared.');
    operation_audit($db,$operator,'CLEAR_EXIT','parking_sessions',$visit['ticket_id'],['status'=>'COMPLETED','duration_minutes'=>$minutes,'cleared_by'=>$operator['full_name']??'Guard','authorized_by'=>$approver['id']??null,'departure_reason'=>$reason,'restriction'=>$policy]);
    return ['duration_minutes'=>$minutes,'duration_text'=>$text];
}
function vehicle_recent_alerts(PDO $db):array
{
    $rows=$db->query("SELECT d.id detection_id,d.camera,d.plate_clean plate_number,d.created_at detected_at,
        s.id session_id,r.category,r.notes blacklist_reason,
        EXISTS(SELECT 1 FROM parking_actions a WHERE a.session_id=s.id AND a.resolved_at IS NULL AND a.action_type='clamped') is_clamped,
        EXISTS(SELECT 1 FROM parking_actions a WHERE a.session_id=s.id AND a.resolved_at IS NULL AND a.action_type='incident') has_incident
        FROM alpr_detections d
        LEFT JOIN registered_vehicles r ON r.plate_number=d.plate_clean AND r.is_active=1
        LEFT JOIN parking_sessions s ON s.plate_number=d.plate_clean AND s.status='ACTIVE'
        WHERE d.created_at>=DATE_SUB(NOW(),INTERVAL 2 MINUTE)
        AND NOT EXISTS(SELECT 1 FROM alpr_detections newer WHERE newer.plate_clean=d.plate_clean AND newer.camera=d.camera AND newer.id>d.id)
        AND NOT EXISTS(SELECT 1 FROM parking_sessions departed WHERE departed.plate_number=d.plate_clean AND departed.status='COMPLETED' AND departed.exit_time>=d.created_at)
        AND (r.category='blacklisted' OR EXISTS(SELECT 1 FROM parking_actions issue WHERE issue.session_id=s.id AND issue.resolved_at IS NULL AND issue.action_type IN ('incident','clamped')))
        ORDER BY d.id DESC LIMIT 20")->fetchAll();
    foreach($rows as &$row){$row['is_blacklisted']=$row['category']==='blacklisted';$row['is_clamped']=(bool)$row['is_clamped'];$row['has_incident']=(bool)$row['has_incident'];$row['requires_supervisor']=true;}
    return $rows;
}
