<?php
/** Daily guard overview from saved visits and unresolved local arrivals. Read-only. */
declare(strict_types=1);

function guard_daily_overview(PDO $db, ?DateTimeImmutable $day = null): array
{
    $day = ($day ?? new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))
        ->setTimezone(new DateTimeZone('Africa/Nairobi'))->setTime(0, 0);
    $start = $day->format('Y-m-d H:i:s');
    $end = $day->modify('+1 day')->format('Y-m-d H:i:s');
    $statement = $db->prepare("SELECT
        COALESCE(SUM(driver_name <> 'Uncollected Driver'), 0) registered,
        COALESCE(SUM(driver_name = 'Uncollected Driver'), 0) without_details
        FROM parking_sessions WHERE status IN ('ACTIVE','COMPLETED')
        AND entry_time >= ? AND entry_time < ?");
    $statement->execute([$start, $end]);
    $arrivals = $statement->fetch();
    $statement = $db->prepare("SELECT COUNT(*) FROM parking_sessions
        WHERE status = 'COMPLETED' AND exit_time >= ? AND exit_time < ?");
    $statement->execute([$start, $end]);
    $exits = (int)$statement->fetchColumn();

    // One unresolved plate counts once, even if a camera/request was repeated.
    // Suppress requests already covered by a saved visit or an active session.
    $statement = $db->prepare("SELECT COUNT(DISTINCT v.plate_number)
        FROM visitors v WHERE v.status = 'PENDING' AND v.created_at >= ? AND v.created_at < ?
        AND NOT EXISTS (
            SELECT 1 FROM parking_sessions s WHERE s.plate_number = v.plate_number
            AND s.status IN ('ACTIVE','COMPLETED')
            AND (s.entry_time >= v.created_at OR s.exit_time IS NULL OR s.exit_time >= v.created_at)
        )");
    $statement->execute([$start, $end]);
    $pending = (int)$statement->fetchColumn();
    $registered = (int)$arrivals['registered'];
    $withoutDetails = (int)$arrivals['without_details'];
    return ['date' => $day->format('Y-m-d'), 'registered' => $registered,
        'unregistered' => $withoutDetails + $pending, 'pending_registration' => $pending,
        'exited_without_details' => $withoutDetails, 'total_arrivals' => $registered + $withoutDetails + $pending,
        'exited' => $exits];
}
