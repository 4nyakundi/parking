<?php
/**
 * api/admin/backup.php — Trigger on-demand DB backup and list existing backups.
 * GET  → list backups from the backups table
 * POST → run mysqldump now and record in DB
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$user = require_role(['supervisor', 'admin']);
$pdo  = get_db();
$config = get_config();

$method = $_SERVER['REQUEST_METHOD'];

// ---- GET: list backups ---------------------------------------------------
if ($method === 'GET') {
    $rows = $pdo->query(
        "SELECT id, filename, size, created_at, created_by FROM backups ORDER BY created_at DESC LIMIT 100"
    )->fetchAll();
    json_ok($rows);
}

// ---- POST: run backup now -----------------------------------------------
if ($method === 'POST') {
    $backupDir = __DIR__ . '/../../storage/backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0777, true);
    }
    if (!is_dir($backupDir)) {
        json_err("Backup directory does not exist and could not be created: $backupDir");
    }

    $filename = 'parking_' . date('Y-m-d_His') . '.sql';
    $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

    $dbCfg = $config['db'];
    $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (!file_exists($mysqldumpPath)) {
        $mysqldumpPath = 'mysqldump';
    }

    $pwdArg = !empty($dbCfg['password']) ? '-p' . escapeshellarg($dbCfg['password']) : '';

    $cmd = sprintf(
        '"%s" --host=%s --port=%s --user=%s %s %s > "%s" 2>&1',
        $mysqldumpPath,
        escapeshellarg((string)$dbCfg['host']),
        escapeshellarg((string)$dbCfg['port']),
        escapeshellarg((string)$dbCfg['username']),
        $pwdArg,
        escapeshellarg((string)$dbCfg['dbname']),
        $filepath
    );

    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
        json_err('mysqldump failed. Output: ' . implode(' ', $output));
    }

    $size = filesize($filepath);

    // Also copy to secondary backup dir if exists
    $extDir = $config['paths']['backups'] ?? 'D:\\ParkingBackups';
    if (is_dir($extDir)) {
        @copy($filepath, $extDir . DIRECTORY_SEPARATOR . $filename);
    }

    // Record in backups table
    $stmt = $pdo->prepare(
        "INSERT INTO backups (filename, size, file_path, created_at, created_by) VALUES (?, ?, ?, NOW(), ?)"
    );
    $stmt->execute([$filename, $size, $filepath, $user['full_name']]);
    $id = (int)$pdo->lastInsertId();

    // Audit
    if (function_exists('write_audit')) {
        write_audit($pdo, $user['id'], 'BACKUP_CREATED', 'backups', (string)$id, null,
            ['filename' => $filename, 'size' => $size]);
    }

    json_ok([
        'id'         => $id,
        'filename'   => $filename,
        'filepath'   => $filepath,
        'size'       => $size,
        'size_human' => round($size / 1024, 1) . ' KB',
    ]);
}

json_err('Method not allowed', 405);
