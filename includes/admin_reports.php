<?php
declare(strict_types=1);
function admin_report_range(array $input): array
{
    $today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
    $from = trim((string)($input['date_from'] ?? '')) ?: $today;
    $to = trim((string)($input['date_to'] ?? '')) ?: $today;
    foreach ([$from,$to] as $value) {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Africa/Nairobi'));
        if (!$day || $day->format('Y-m-d') !== $value) throw new InvalidArgumentException('Choose valid report dates.');
    }
    if ($from > $to) throw new InvalidArgumentException('The end date must follow the start date.');
    return [$from, $to, $from.' 00:00:00', (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d 00:00:00')];
}
function admin_report(PDO $db, string $start, string $end): array
{
    $q = $db->prepare("SELECT COUNT(*) total_entries,
        COALESCE(SUM(status='ACTIVE'),0) active_inside,
        COALESCE(SUM(driver_name='Uncollected Driver'),0) without_details
        FROM parking_sessions WHERE status IN ('ACTIVE','COMPLETED') AND entry_time >= ? AND entry_time < ?");
    $q->execute([$start,$end]); $summary = array_map('intval',$q->fetch());
    $q = $db->prepare("SELECT COUNT(*) total_exits, ROUND(COALESCE(AVG(TIMESTAMPDIFF(MINUTE,entry_time,exit_time)),0)) avg_duration_mins
        FROM parking_sessions WHERE status='COMPLETED' AND exit_time >= ? AND exit_time < ?");
    $q->execute([$start,$end]); $summary += array_map('intval',$q->fetch());
    $q = $db->prepare("SELECT HOUR(entry_time) hr, COUNT(*) count FROM parking_sessions WHERE status IN ('ACTIVE','COMPLETED') AND entry_time >= ? AND entry_time < ? GROUP BY HOUR(entry_time) ORDER BY hr");
    $q->execute([$start,$end]); $hourly = $q->fetchAll();
    $peak = null;
    foreach ($hourly as $hour) if (!$peak || $hour['count'] > $peak['count']) $peak = $hour;
    $summary['peak_hour'] = $peak ? sprintf('%02d:00–%02d:00 (%d arrivals)', $peak['hr'], ($peak['hr']+1)%24, $peak['count']) : 'No arrivals';
    $q = $db->prepare("SELECT destination, COUNT(*) count, ROUND(AVG(TIMESTAMPDIFF(MINUTE,entry_time,COALESCE(exit_time,NOW())))) avg_dwell_mins
        FROM parking_sessions WHERE status IN ('ACTIVE','COMPLETED') AND entry_time >= ? AND entry_time < ? GROUP BY destination ORDER BY count DESC");
    $q->execute([$start,$end]); $destinations = $q->fetchAll();
    $maps=[];
    foreach ([['parking_sessions','approved_by','entry_time',"status IN ('ACTIVE','COMPLETED')"],['parking_sessions','cleared_by','exit_time',"status='COMPLETED'"],['visitors','handled_by','created_at',"status='REJECTED'"]] as [$table,$actor,$time,$condition]) {
        $q=$db->prepare("SELECT {$actor} actor,COUNT(*) count FROM {$table} WHERE {$condition} AND {$time}>=? AND {$time}<? GROUP BY {$actor}");
        $q->execute([$start,$end]); $maps[]=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    $shifts=$db->query("SELECT id guard_id,full_name guard_name FROM users WHERE role IN ('guard','supervisor','admin') ORDER BY full_name")->fetchAll();
    foreach ($shifts as &$shift) {
        $id=$shift['guard_id']; $shift['entries_approved']=(int)($maps[0][$id]??0);
        $shift['exits_cleared']=(int)($maps[1][$id]??0); $shift['rejections']=(int)($maps[2][$id]??0);
    }
    unset($shift);
    return ['summary'=>$summary,'hourly'=>$hourly,'destinations'=>$destinations,'shifts'=>$shifts];
}
