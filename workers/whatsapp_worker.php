<?php
/**
 * Mombasa Mall Basement Parking - WhatsApp Outbox Worker
 * Runs every 1 minute via Windows Task Scheduler.
 * Processes queued WhatsApp tickets and exit notifications with retry logic and offline resilience.
 */

declare(strict_types=1);

// Prevent browser execution if accessed directly via web
if (php_sapi_name() !== 'cli' && !isset($_GET['run_now'])) {
    http_response_code(403);
    echo "This worker is designed for CLI / Windows Task Scheduler.\n";
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/whatsapp_service.php';

$config = require __DIR__ . '/../config/config.php';
$wCfg = $config['whatsapp'] ?? [];

echo "[" . date('Y-m-d H:i:s') . "] Starting WhatsApp Queue Worker...\n";

if (empty($wCfg['enabled'])) {
    echo "WhatsApp messaging is currently DISABLED in config/config.php. Messages will remain queued.\n";
    exit;
}

try {
    $db = get_db();

    // Fetch up to 20 pending or failed messages (with fewer than 5 attempts)
    $stmt = $db->query('
        SELECT id, session_id, phone, template_name, params, attempts
        FROM whatsapp_queue
        WHERE status IN ("pending", "failed") AND attempts < 5
        ORDER BY id ASC
        LIMIT 20
    ');
    $queue = $stmt->fetchAll();

    if (empty($queue)) {
        echo "No pending WhatsApp messages in queue.\n";
        exit;
    }

    echo "Found " . count($queue) . " message(s) to process.\n";

    foreach ($queue as $item) {
        $msgId     = (int)$item['id'];
        $sessionId = !empty($item['session_id']) ? (int)$item['session_id'] : null;
        $phone     = $item['phone'];
        $template  = $item['template_name'];
        $params    = json_decode($item['params'], true) ?? [];
        $attempts  = (int)$item['attempts'] + 1;

        echo "Sending message #{$msgId} to {$phone}... ";

        $res = WhatsAppService::sendViaMeta($phone, $template, $params);

        if ($res['ok']) {
            echo "SUCCESS.\n";
            $upd = $db->prepare('
                UPDATE whatsapp_queue 
                SET status = "sent", attempts = :att, last_attempt_at = NOW(), error_message = NULL 
                WHERE id = :id
            ');
            $upd->execute([':att' => $attempts, ':id' => $msgId]);

            if ($sessionId) {
                $db->prepare('UPDATE parking_sessions SET whatsapp_status = "sent" WHERE id = ?')
                   ->execute([$sessionId]);
            }
        } else {
            echo "FAILED: " . $res['error'] . "\n";
            $upd = $db->prepare('
                UPDATE whatsapp_queue 
                SET status = "failed", attempts = :att, last_attempt_at = NOW(), error_message = :err 
                WHERE id = :id
            ');
            $upd->execute([
                ':att' => $attempts,
                ':err' => substr($res['error'], 0, 500),
                ':id'  => $msgId,
            ]);

            if ($sessionId && $attempts >= 5) {
                $db->prepare('UPDATE parking_sessions SET whatsapp_status = "failed" WHERE id = ?')
                   ->execute([$sessionId]);
            }
        }

        // Small throttle between API calls to respect rate limits
        usleep(150000); // 150ms
    }

    echo "[" . date('Y-m-d H:i:s') . "] WhatsApp Queue Worker finished.\n";
} catch (Throwable $e) {
    echo "FATAL WORKER ERROR: " . $e->getMessage() . "\n";
    error_log("WHATSAPP WORKER CRASH: " . $e->getMessage());
}
