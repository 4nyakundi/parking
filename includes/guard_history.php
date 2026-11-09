<?php
/** Read-only recorded traffic reporting. All dates use the site's Nairobi timezone. */
declare(strict_types=1);
require_once __DIR__.'/visit_destinations.php';

function guard_history_options(array $input): array
{
    $today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
    $mode = (string)($input['mode'] ?? 'exited');
    if (!in_array($mode, ['exited', 'day'], true)) throw new InvalidArgumentException('Invalid history view.');
    $date = trim((string)($input['date'] ?? ''));
    if ($mode === 'day' && $date === '') $date = $today;
    if ($date !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Africa/Nairobi'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > $today) {
            throw new InvalidArgumentException('Choose a valid date up to today.');
        }
    }
    $page = max(1, (int)($input['page'] ?? 1));
    return ['scope'=>(($input['scope']??'')==='current_shift'?'current_shift':'date'),'guard_id'=>(int)($input['guard_id']??0),'mode' => $mode, 'date' => $date, 'page' => $page,
        'q' => mb_substr(trim((string)($input['q'] ?? '')), 0, 100)];
}

function guard_history_report(PDO $db, array $options): array
{
    ['mode' => $mode, 'date' => $date, 'page' => $page, 'q' => $query] = $options;
    $summary = null;
    $start = $end = null;
    if ($date !== '') {
        $day = new DateTimeImmutable($date, new DateTimeZone('Africa/Nairobi'));
        $start = $day->format('Y-m-d 00:00:00');
        $end = $day->modify('+1 day')->format('Y-m-d 00:00:00');
        // Count visits, not registry profiles. Repeat arrivals are separate movements.
        // Midnight exits belong to the new day's movements and opening inventory,
        // but do not count as present visits unless they also arrived that day.
        $statement = $db->prepare("SELECT
            COALESCE(SUM(entry_time >= ? AND entry_time < ?), 0) arrivals,
            COALESCE(SUM(status = 'COMPLETED' AND exit_time >= ? AND exit_time < ?), 0) exits,
            COALESCE(SUM(entry_time < ? AND (exit_time IS NULL OR exit_time >= ?)), 0) carried_in,
            COALESCE(SUM(entry_time < ? AND (exit_time IS NULL OR exit_time >= ?)), 0) remaining,
            COALESCE(SUM(exit_time IS NULL OR exit_time > ? OR entry_time >= ?), 0) present_visits,
            COUNT(DISTINCT CASE WHEN exit_time IS NULL OR exit_time > ? OR entry_time >= ? THEN plate_number END) unique_cars,
            COALESCE(SUM(driver_name = 'Uncollected Driver' AND (exit_time IS NULL OR exit_time > ? OR entry_time >= ?)), 0) missing_details
            FROM parking_sessions WHERE status IN ('ACTIVE','COMPLETED')
            AND entry_time < ? AND (exit_time IS NULL OR exit_time >= ?)");
        $statement->execute([$start, $end, $start, $end, $start, $start, $end, $end,
            $start, $start, $start, $start, $start, $start, $end, $start]);
        $summary = array_map('intval', $statement->fetch());
        $summary['movements'] = $summary['arrivals'] + $summary['exits'];
    }

    $params = [];
    if ($mode === 'exited') {
        $where = "s.status = 'COMPLETED' AND s.exit_time IS NOT NULL";
        if (($options['scope']??'')==='current_shift') {
            $shift=$db->prepare('SELECT started_at,ended_at FROM parking_shifts WHERE guard_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1');
            $shift->execute([$options['guard_id']]); $current=$shift->fetch();
            $where .= ' AND s.exit_time>=?';
            $params=[$current?$current['started_at']:(new DateTimeImmutable('today',new DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s')];
        } elseif ($date !== '') {
            $where .= ' AND s.exit_time >= ? AND s.exit_time < ?';
            $params = [$start, $end];
        }
        $order = 's.exit_time DESC, s.id DESC';
    } else {
        $where = "s.status IN ('ACTIVE','COMPLETED') AND s.entry_time < ? AND (s.exit_time IS NULL OR s.exit_time > ? OR s.entry_time >= ?)";
        $params = [$end, $start, $start];
        $order = 's.entry_time DESC, s.id DESC';
    }
    if ($query !== '') {
        $where .= ' AND (s.plate_number LIKE ? OR s.driver_name LIKE ? OR s.destination LIKE ? OR s.ticket_id LIKE ? OR s.driver_phone LIKE ? OR s.plate_number LIKE ? OR EXISTS (SELECT 1 FROM visit_destinations vd WHERE vd.entity_type="session" AND vd.entity_id=s.id AND vd.destination_name LIKE ?))';
        $like = '%' . $query . '%';
        $plateQuery = preg_replace('/[^A-Z0-9]/', '', strtoupper($query));
        array_push($params, $like, $like, $like, $like, $like, '%' . ($plateQuery ?: $query) . '%', $like);
    }
    $count = $db->prepare("SELECT COUNT(*) FROM parking_sessions s WHERE {$where}");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pageSize = 50;
    $pages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $pages);
    $offset = ($page - 1) * $pageSize;
    $statement = $db->prepare("SELECT s.id, s.ticket_id, s.plate_number, s.driver_name, s.driver_phone,
        s.destination, s.entry_time, s.exit_time, s.status, s.exit_method,
        TIMESTAMPDIFF(SECOND, s.entry_time, COALESCE(s.exit_time, NOW())) duration_seconds,
        u.full_name cleared_by_name
        FROM parking_sessions s LEFT JOIN users u ON u.id = s.cleared_by
        WHERE {$where} ORDER BY {$order} LIMIT {$pageSize} OFFSET {$offset}");
    $statement->execute($params);
    $today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
    return ['mode' => $mode, 'date' => $date, 'today' => $today, 'summary' => $summary,
        'sessions' => visit_store_decorate($db,$statement->fetchAll()), 'total' => $total, 'page' => $page, 'pages' => $pages,
        'page_size' => $pageSize, 'server_timestamp' => time()];
}
