<?php
/** Consistent, read-only management overview. No camera or print commands. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/guard_overview.php';
require_role(['admin','supervisor']); session_write_close();
try {
    $db = get_db();
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->beginTransaction();
    $daily = guard_daily_overview($db);
    $counts = $db->query("SELECT COUNT(*) occupied, COALESCE(SUM(COALESCE((SELECT approved_until FROM parking_extensions pe WHERE pe.session_id=parking_sessions.id),DATE_ADD(entry_time,INTERVAL 2 HOUR))<=NOW()),0) overstays FROM parking_sessions WHERE status='ACTIVE'")->fetch();
    $capacity = $db->query("SELECT value FROM system_settings WHERE `key`='capacity'")->fetchColumn();
    $devices = $db->query('SELECT device,status,message,last_checked_at,TIMESTAMPDIFF(SECOND,last_checked_at,NOW()) age_seconds FROM device_health')->fetchAll();
    $attention = [
        'Print failures'=>(int)$db->query("SELECT COUNT(*) FROM parking_sessions WHERE print_status='failed'")->fetchColumn(),
        'Message failures'=>(int)$db->query("SELECT COUNT(*) FROM whatsapp_queue WHERE status='failed'")->fetchColumn(),
        'Pending cloud uploads'=>(int)$db->query('SELECT COUNT(*) FROM parking_sessions WHERE synced_to_cloud=0')->fetchColumn(),
    ];
    $backup = $db->query('SELECT filename,size,created_at FROM backups ORDER BY id DESC LIMIT 1')->fetch() ?: null;
    $handover = $db->query("SELECT a.created_at,a.new_value,u.full_name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE a.action='SUPERVISOR_SHIFT_SIGNOFF' ORDER BY a.id DESC LIMIT 1")->fetch() ?: null;
    if ($handover) $handover['details'] = json_decode($handover['new_value'],true);
    $avg = (int)$db->query("SELECT COALESCE(ROUND(AVG(duration_minutes)),0) FROM parking_sessions WHERE status='COMPLETED' AND exit_time>=CURDATE() AND exit_time<DATE_ADD(CURDATE(),INTERVAL 1 DAY)")->fetchColumn();
    $db->commit();
    echo json_encode(['ok'=>true,'data'=>['daily'=>$daily,'capacity'=>(int)($capacity?:60),
        'occupied'=>(int)$counts['occupied'],'overstays'=>(int)$counts['overstays'],'overstay_hours'=>2,
        'avg_stay_mins'=>$avg,'devices'=>$devices,'attention'=>$attention,'backup'=>$backup,'handover'=>$handover,'server_timestamp'=>time()]]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('ADMIN OVERVIEW: '.$e->getMessage()); http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Overview unavailable. Please retry.']);
}
