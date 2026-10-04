<?php
/**
 * Mombasa Mall Basement Parking - System Settings API
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

$user = require_role(['supervisor', 'admin'], true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'get');

try {
    $db = get_db();

    // 1. GET SETTINGS
    if ($action === 'get') {
        $stmt = $db->query('SELECT `key`, value, description FROM system_settings');
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($settings as $s) {
            $map[$s['key']] = $s['value'];
        }

        echo json_encode(['ok' => true, 'data' => $map]);
        exit;
    }

    // 2. UPDATE SETTINGS (Admin only)
    if ($action === 'update') {
        if ($user['role'] !== 'admin') {
            echo json_encode(['ok' => false, 'error' => 'Only administrators can modify system settings.']);
            exit;
        }
        $allowedKeys = [
            'capacity', 'overstay_hours', 'mall_name',
            'printer_type', 'printer_name', 'printer_paper_width',
            'whatsapp_enabled', 'whatsapp_template_entry', 'whatsapp_template_exit',
            'alpr_confidence_threshold', 'alpr_debounce_seconds',
            'cloud_sync_enabled', 'cloud_sync_url', 'language_default', 'dpa_notice',
        ];

        $updates = $input['settings'] ?? [];
        $saved = [];

        foreach ($updates as $key => $val) {
            if (in_array($key, $allowedKeys, true)) {
                $stmt = $db->prepare('
                    INSERT INTO system_settings (`key`, value, updated_at) 
                    VALUES (:key, :val, NOW())
                    ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
                ');
                $stmt->execute([':key' => $key, ':val' => (string)$val]);
                $saved[$key] = $val;
            }
        }

        audit_log($user['id'], 'UPDATE_SYSTEM_SETTINGS', 'system_settings', 'all', null, $saved);

        echo json_encode(['ok' => true, 'message' => 'System settings updated successfully.']);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('SETTINGS API ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
