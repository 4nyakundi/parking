<?php
declare(strict_types=1);
require_once __DIR__.'/shift_followup.php';
require_once __DIR__.'/vehicle_restrictions.php';

function operation_audit(PDO $db, array $user, string $action, string $entity, string $id, array $data): void
{
    $db->prepare('INSERT INTO audit_log(user_id,action,entity,entity_id,new_value,ip,created_at) VALUES(?,?,?,?,?,?,NOW())')
       ->execute([$user['id'],$action,$entity,$id,json_encode($data,JSON_THROW_ON_ERROR),substr($_SERVER['REMOTE_ADDR']??'CLI',0,45)]);
}
function shift_summary(PDO $db, array $shift): array
{
    $end=$shift['ended_at']??date('Y-m-d H:i:s');
    $q=$db->prepare("SELECT COALESCE(SUM(entry_time>=? AND entry_time<?),0) arrivals,
      COALESCE(SUM(status='COMPLETED' AND exit_time>=? AND exit_time<?),0) departures
      FROM parking_sessions WHERE status IN ('ACTIVE','COMPLETED')");
    $q->execute([$shift['started_at'],$end,$shift['started_at'],$end]);
    $totals=array_map('intval',$q->fetch());
    return $shift+$totals+['expected_closing'=>(int)$shift['opening_count']+$totals['arrivals']-$totals['departures']];
}
function shift_boundary_count(PDO $db,string $boundary): int
{
    // Half-open shift intervals: a movement exactly at the boundary belongs to
    // the incoming shift. Include boundary exits in the opening inventory.
    $q=$db->prepare("SELECT COUNT(*) FROM parking_sessions WHERE status IN ('ACTIVE','COMPLETED') AND entry_time<? AND (exit_time IS NULL OR exit_time>=?)");
    $q->execute([$boundary,$boundary]);return (int)$q->fetchColumn();
}
function parking_operation(PDO $db, array $user, string $action, array $input): array
{
    if($action==='use_current_shift')return ['shift'=>parking_use_current_shift($db,$user,$user['signed_in_at']??null)];
    if ($action==='overview') {
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM parking_shifts WHERE guard_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1');
            $q->execute([$user['id']]); $current=$q->fetch();
            $recent=$db->query('SELECT s.*,u.full_name guard_name FROM parking_shifts s LEFT JOIN users u ON u.id=s.guard_id ORDER BY s.id DESC LIMIT 20')->fetchAll();
            $cases=$db->query("SELECT s.id,s.plate_number,s.driver_name,s.destination,s.entry_time,s.status,s.exit_time,
                COALESCE(e.approved_until,DATE_ADD(s.entry_time,INTERVAL 2 HOUR)) deadline,
                e.reason extension_reason,rv.category vehicle_category,rv.notes blacklist_reason,
                EXISTS(SELECT 1 FROM parking_actions clamp WHERE clamp.session_id=s.id AND clamp.resolved_at IS NULL AND clamp.action_type='clamped') is_clamped,
                EXISTS(SELECT 1 FROM parking_actions incident WHERE incident.session_id=s.id AND incident.resolved_at IS NULL AND incident.action_type='incident') has_incident,
                (SELECT COUNT(*) FROM parking_actions a WHERE a.session_id=s.id AND a.resolved_at IS NULL) open_actions
                FROM parking_sessions s LEFT JOIN parking_extensions e ON e.session_id=s.id
                LEFT JOIN registered_vehicles rv ON rv.plate_number=s.plate_number AND rv.is_active=1
                WHERE s.status='ACTIVE' OR EXISTS (SELECT 1 FROM parking_actions outstanding WHERE outstanding.session_id=s.id AND outstanding.resolved_at IS NULL)
                ORDER BY s.entry_time LIMIT 500")->fetchAll();
            require_once __DIR__.'/visit_destinations.php';
            $cases=visit_store_decorate($db,$cases);
            $result=['operator_name'=>$user['full_name']??'Current guard','current'=>$current?shift_summary($db,$current):null,'recent'=>array_map(fn($r)=>shift_summary($db,$r),$recent),'cases'=>$cases];
            $db->commit(); return $result;
        } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
    }
    if ($action==='history') {
        $q=$db->prepare('SELECT a.*,u.full_name author,r.full_name resolved_by_name,approver.full_name authorized_by_name,sh.started_at shift_started_at FROM parking_actions a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN users r ON r.id=a.resolved_by LEFT JOIN users approver ON approver.id=a.authorized_by LEFT JOIN parking_shifts sh ON sh.id=a.shift_id WHERE session_id=? ORDER BY a.id DESC LIMIT 100');
        $q->execute([(int)($input['session_id']??0)]); return ['actions'=>$q->fetchAll()];
    }
    $approver=null;
    if(in_array($action,['log_action','resolve_action','extend','end_shift','blacklist'],true))$approver=parking_supervisor_code($db,$user,$input);
    $db->beginTransaction();
    try {
        // Serialize starts/ends for the same account, including repeated clicks on other tablets.
        $q=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $q->execute([$user['id']]);
        if($approver){
            $q=$db->prepare("SELECT pin_hash FROM users WHERE id=? AND is_active=1 AND role IN ('supervisor','admin') FOR UPDATE");$q->execute([$approver['id']]);
            $pinHash=$q->fetchColumn();
            if(!$pinHash||!password_verify((string)($input['supervisor_pin']??''),$pinHash))throw new DomainException('Supervisor approval is no longer valid.');
        }
        $q=$db->prepare('SELECT id FROM parking_shifts WHERE guard_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE');$q->execute([$user['id']]);$currentShiftId=$q->fetchColumn();
        if(in_array($action,['log_action','resolve_action','extend','blacklist'],true)&&!$currentShiftId)throw new DomainException('Use your current signed-in shift before saving a follow-up.');
        if (in_array($action,['start_shift','end_shift'],true)) {
            $q=$db->prepare('SELECT * FROM parking_shifts WHERE guard_id=? AND ended_at IS NULL FOR UPDATE');$q->execute([$user['id']]);$shift=$q->fetch();
            $boundary=(string)$db->query('SELECT NOW()')->fetchColumn();
            $count=shift_boundary_count($db,$boundary);
            if($action==='start_shift') {
                if($shift) throw new InvalidArgumentException('You already have an open shift.');
                $db->prepare('INSERT INTO parking_shifts(guard_id,opening_count,started_at) VALUES(?,?,?)')->execute([$user['id'],$count,$boundary]);
                $id=(int)$db->lastInsertId(); $data=['opening_count'=>$count];
            } else {
                if(!$shift) throw new InvalidArgumentException('Start your shift first.');
                $physical=filter_var($input['physical_count']??null,FILTER_VALIDATE_INT);
                $notes=trim((string)($input['notes']??''));
                if($physical===false || $physical<0 || mb_strlen($notes)>4000) throw new InvalidArgumentException('Enter a valid physical count and notes up to 4,000 characters.');
                if($physical!==$count && $notes==='') throw new InvalidArgumentException('Explain the difference between the physical and system counts.');
                $id=(int)$shift['id'];$data=['system_count'=>$count,'physical_count'=>$physical,'notes'=>$notes];
                $db->prepare('UPDATE parking_shifts SET ended_at=?,physical_count=?,closing_count=?,handover_notes=? WHERE id=?')->execute([$boundary,$physical,$count,$notes,$id]);
            }
            $data['authorized_by']=$approver['id']??null;
            operation_audit($db,$user,strtoupper($action),'parking_shifts',(string)$id,$data);
        } else {
            $id=(int)($input['session_id']??0);
            $q=$db->prepare('SELECT * FROM parking_sessions WHERE id=? FOR UPDATE');$q->execute([$id]);$session=$q->fetch();
            if(!$session) throw new InvalidArgumentException('Parking visit not found.');
            $notes=trim((string)($input['notes']??''));
            if(mb_strlen($notes)>4000) throw new InvalidArgumentException('Use notes up to 4,000 characters.');
            if($action==='log_action') {
                $type=(string)($input['type']??'follow_up');
                if(!in_array($type,['follow_up','driver_contacted','supervisor_notified','incident','clamped'],true)||$notes==='') throw new InvalidArgumentException('Choose an action and enter a note.');
                if($type==='clamped'){
                    if($session['status']!=='ACTIVE')throw new InvalidArgumentException('Only a parked vehicle can be marked as clamped.');
                    $policy=vehicle_exit_policy($db,$session['plate_number'],$id);
                    if($policy['is_clamped'])throw new InvalidArgumentException('This visit is already marked as clamped.');
                }
                $db->prepare('INSERT INTO parking_actions(session_id,user_id,action_type,notes,shift_id,authorized_by) VALUES(?,?,?,?,?,?)')->execute([$id,$user['id'],$type,$notes,$currentShiftId,$approver['id']]);
                $data=['type'=>$type,'notes'=>$notes];
            } elseif($action==='resolve_action') {
                $q=$db->prepare('SELECT action_type FROM parking_actions WHERE id=? AND session_id=? AND resolved_at IS NULL FOR UPDATE');$q->execute([(int)($input['action_id']??0),$id]);$type=$q->fetchColumn();
                if($type==='clamped'&&$notes==='')throw new InvalidArgumentException('Enter the clamp release details before authorizing release.');
                $q=$db->prepare('UPDATE parking_actions SET resolved_at=NOW(),resolved_by=? WHERE id=? AND session_id=? AND resolved_at IS NULL');
                $q->execute([$approver['id'],(int)($input['action_id']??0),$id]);
                if(!$q->rowCount()) throw new InvalidArgumentException('Action already resolved or unavailable.');
                $data=['action_id'=>(int)$input['action_id'],'resolution_notes'=>$notes];
                if($type==='clamped')$db->prepare("INSERT INTO parking_actions(session_id,user_id,action_type,notes,shift_id,authorized_by,resolved_at,resolved_by) VALUES(?,?,'clamp_released',?,?,?,NOW(),?)")->execute([$id,$user['id'],$notes,$currentShiftId,$approver['id'],$approver['id']]);
            } elseif($action==='blacklist') {
                vehicle_blacklist($db,$session,$notes);
                $db->prepare("INSERT INTO parking_actions(session_id,user_id,action_type,notes,shift_id,authorized_by,resolved_at,resolved_by) VALUES(?,?,'blacklisted',?,?,?,NOW(),?)")->execute([$id,$user['id'],$notes,$currentShiftId,$approver['id'],$approver['id']]);
                $data=['plate_number'=>$session['plate_number'],'reason'=>$notes];
            } elseif($action==='extend') {
                if($session['status']!=='ACTIVE'||$notes==='') throw new InvalidArgumentException('An active visit and approval reason are required.');
                $mins=filter_var($input['minutes']??null,FILTER_VALIDATE_INT);
                if($mins===false||$mins<1||$mins>1440) throw new InvalidArgumentException('Use an extension of 1 to 1,440 minutes.');
                $q=$db->prepare('SELECT approved_until FROM parking_extensions WHERE session_id=?');$q->execute([$id]);
                $base=max(strtotime($session['entry_time'])+7200,strtotime($q->fetchColumn()?:'1970-01-01'),time());
                $until=date('Y-m-d H:i:s',$base+$mins*60);
                $db->prepare('INSERT INTO parking_extensions(session_id,approved_until,approved_by,reason) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE approved_until=VALUES(approved_until),approved_by=VALUES(approved_by),reason=VALUES(reason),updated_at=NOW()')->execute([$id,$until,$approver['id'],$notes]);
                $data=['approved_until'=>$until,'minutes'=>$mins,'reason'=>$notes];
            } elseif($action==='destinations') {
                if($session['status']!=='ACTIVE') throw new InvalidArgumentException('Destinations can only be changed for an active visit.');
                require_once __DIR__.'/visit_destinations.php';
                $stores=visit_stores($db,$input,$session['destination']);
                if(!$stores) throw new InvalidArgumentException('Select at least one store.');
                visit_store_save($db,'session',$id,$stores);
                $db->prepare('UPDATE parking_sessions SET destination=?,synced_to_cloud=0 WHERE id=?')->execute([$stores[0]['name'],$id]);
                $data=['destinations'=>array_column($stores,'name')];
            } else throw new InvalidArgumentException('Unknown operation.');
            $data['shift_id']=$currentShiftId?(int)$currentShiftId:null;
            $data['recorded_by']=$user['id'];
            $data['authorized_by']=$approver['id']??null;
            operation_audit($db,$user,'PARKING_'.strtoupper($action),'parking_sessions',$session['ticket_id'],$data);
        }
        $db->commit(); return ['message'=>'Saved successfully.'];
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}
