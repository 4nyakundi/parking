<?php
/**
 * Mombasa Mall Basement Parking - Database Backups API
 * Performs on-demand mysqldump, lists backup history, and allows secure download.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

$user = require_role(['supervisor', 'admin'], true);

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
require_once __DIR__ . '/../../includes/admin_actions.php';
admin_require_mutation($action, ['create']);

try {
    $db = get_db();
    $config = get_config();

    // -------------------------------------------------------------
    // ACTION: DOWNLOAD BACKUP
    // -------------------------------------------------------------
    if ($action === 'download') {
        $backupId = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM backups WHERE id = ?');
        $stmt->execute([$backupId]);
        $backup = $stmt->fetch();

        if (!$backup || !file_exists($backup['file_path'])) {
            http_response_code(404);
            echo "Backup file not found on disk.";
            exit;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($backup['filename']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup['file_path']));
        readfile($backup['file_path']);
        exit;
    }

    // Default JSON responses
    header('Content-Type: application/json; charset=utf-8');

    // -------------------------------------------------------------
    // ACTION: LIST BACKUPS
    // -------------------------------------------------------------
    if ($action === 'list') {
        $stmt = $db->query('
            SELECT b.*, u.full_name AS created_by_name
            FROM backups b
            LEFT JOIN users u ON u.id = b.created_by
            ORDER BY b.id DESC
        ');
        $backups = $stmt->fetchAll();

        echo json_encode(['ok' => true, 'data' => $backups]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: BACKUP NOW (On-demand mysqldump)
    // -------------------------------------------------------------
    if ($action === 'create') {
        $backupDir = __DIR__ . '/../../storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $filename = sprintf('parking_backup_%s_%s_%s.sql', date('Y-m-d'), date('His'), bin2hex(random_bytes(3)));
        $targetFile = $backupDir . '/' . $filename;

        // Path to mysqldump in XAMPP
        $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (!file_exists($mysqldumpPath)) {
            $mysqldumpPath = 'mysqldump'; // Fallback to system PATH
        }

        $dbCfg = $config['db'];
        $cmd = [$mysqldumpPath, '--host='.$dbCfg['host'], '--port='.($dbCfg['port']??3306),
            '--user='.$dbCfg['username'], '--single-transaction', '--routines', '--triggers', '--result-file='.$targetFile];
        if (!empty($dbCfg['password'])) $cmd[] = '--password='.$dbCfg['password'];
        $cmd[] = $dbCfg['dbname'];

        $output = [];
        $exitCode = 0;
        $process = proc_open($cmd, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to start backup.');
        fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]); $exitCode = proc_close($process);
        if ($exitCode !== 0) { error_log('BACKUP dump failed: '.$error); throw new RuntimeException('Backup did not complete successfully. No valid backup was recorded.'); }

        // Also attempt copy to D:\ParkingBackups if folder exists
        $extDir = $config['paths']['backups'] ?? 'D:\\ParkingBackups';
        $externalCopy = 'Not copied: external backup folder is unavailable';
        if (is_dir($extDir)) {
            $copyPath = $extDir . DIRECTORY_SEPARATOR . $filename;
            $copied = @copy($targetFile, $copyPath);
            $externalCopy = $copied && hash_file('sha256',$targetFile) === hash_file('sha256',$copyPath) ? 'External copy verified' : 'External copy failed';
        }

        $fileSize = file_exists($targetFile) ? filesize($targetFile) : 0;

        if ($fileSize > 0) {
            $ins = $db->prepare('
                INSERT INTO backups (filename, size, file_path, created_by, created_at)
                VALUES (:fname, :size, :path, :uid, NOW())
            ');
            $ins->execute([
                ':fname' => $filename,
                ':size'  => $fileSize,
                ':path'  => $targetFile,
                ':uid'   => $user['id'],
            ]);
            $backupId = (int)$db->lastInsertId();

            audit_log($user['id'], 'CREATE_DATABASE_BACKUP', 'backups', (string)$backupId, null, [
                'filename' => $filename,
                'size' => $fileSize, 'external_copy'=>$externalCopy, 'sha256'=>hash_file('sha256',$targetFile),
            ]);

            echo json_encode([
                'ok'   => true,
                'data' => [
                    'id'       => $backupId,
                    'filename' => $filename,
                    'size' => $fileSize, 'external_copy' => $externalCopy,
                    'message'  => "Database backup created successfully (" . round($fileSize / 1024, 1) . " KB).",
                ],
            ]);
            exit;
        } else {
            echo json_encode(['ok' => false, 'error' => 'Backup file could not be created or is empty.']);
            exit;
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown backup action.']);
} catch (Throwable $e) {
    error_log('BACKUP ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
