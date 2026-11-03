<?php
/** Preview first. --apply adds profile fields and rebuilds factual visit summaries, never merges identities. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/databank.php';
require_once __DIR__ . '/../includes/databank_schema.php';
$db = get_db();
$records = $db->query('SELECT id, plate_number FROM registered_vehicles ORDER BY id')->fetchAll();
$groups = [];
foreach ($records as $record) $groups[PlateHelper::clean($record['plate_number'])][] = $record;
$review = array_filter($groups, fn($rows, $plate) => count($rows) > 1 || $rows[0]['plate_number'] !== $plate, ARRAY_FILTER_USE_BOTH);
$candidates = $db->query('SELECT DISTINCT plate_number FROM parking_sessions WHERE ' . DataBank::validVisitsSql())->fetchAll(PDO::FETCH_COLUMN);
foreach ($candidates as $plate) {
    if ($plate !== PlateHelper::clean($plate) || !PlateHelper::validate(PlateHelper::clean($plate))) $review['history_' . $plate] = [['plate_number'=>$plate, 'reason'=>'Review historical plate before linking']];
}
echo json_encode(['registered_records' => count($records), 'groups_needing_review' => count($review), 'mode' => in_array('--apply', $argv, true) ? 'apply' : 'preview']) . PHP_EOL;
if (!in_array('--apply', $argv, true)) exit;
$backup = 'registered_vehicles_backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$db->exec("CREATE TABLE `{$backup}` LIKE registered_vehicles");
$db->exec("INSERT INTO `{$backup}` SELECT * FROM registered_vehicles");
$added = databank_migrate($db);
$created = 0;
$insert = $db->prepare('INSERT IGNORE INTO registered_vehicles (plate_number, category, is_active) VALUES (?, "regular", 1)');
foreach ($candidates as $plate) {
    $clean = PlateHelper::clean($plate);
    if ($clean !== $plate || !PlateHelper::validate($clean)) {
        $review['history_' . $plate] = [['plate_number' => $plate, 'reason' => 'Review historical plate before linking']];
        continue;
    }
    $insert->execute([$clean]); $created += $insert->rowCount();
}

$db->exec('UPDATE registered_vehicles r LEFT JOIN (SELECT plate_number, COUNT(*) visits, MAX(entry_time) last_seen FROM parking_sessions WHERE ' . DataBank::validVisitsSql() . ' GROUP BY plate_number) s ON s.plate_number = r.plate_number SET r.visit_count = COALESCE(s.visits,0), r.is_frequent = (COALESCE(s.visits,0) >= 3), r.last_visit_at = s.last_seen');
$folder = __DIR__ . '/../storage/backups';
if (!is_dir($folder)) mkdir($folder, 0770, true);
file_put_contents($folder . '/databank_review_' . date('Ymd_His') . '.json', json_encode(['groups' => $review], JSON_PRETTY_PRINT));
echo json_encode(['backup_table' => $backup, 'columns_added' => count($added), 'profiles_created' => $created, 'groups_needing_review' => count($review), 'profiles_merged' => 0, 'history_changed' => false]) . PHP_EOL;
