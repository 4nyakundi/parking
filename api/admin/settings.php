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
require_once __DIR__ . '/../../includes/admin_actions.php';
admin_require_mutation($action, ['update']);

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

        $config = get_config();
        $defaults = [
            'printer_name' => $config['printer']['printer_name'] ?? 'POS80',
            'printer_paper_width' => (string)($config['printer']['paper_width'] ?? 80),
            'alpr_confidence_threshold' => (string)($config['alpr']['confidence_threshold'] ?? 0.85),
            'alpr_debounce_seconds' => (string)($config['alpr']['debounce_seconds'] ?? 30),
            'cloud_sync_url' => $config['cloud_sync']['endpoint'] ?? '',
            'nvr_ip'               => '192.168.0.3',
            'nvr_port'             => '80',
            'nvr_rtsp_port'        => '554',
            'nvr_channel_entrance' => '1',
            'nvr_channel_exit'     => '2',
            'camera_entrance_ip'   => '192.168.0.3',
            'camera_exit_ip'       => '192.168.0.3',
            'capacity'             => '60',
            'overstay_hours'       => '8',
            'mall_name'            => 'Mombasa Mall Basement Parking',
        ];
        foreach ($defaults as $k => $v) {
            if (!isset($map[$k])) {
                $map[$k] = $v;
            }
        }

        echo json_encode(['ok' => true, 'data' => $map]);
        exit;
    }

    // 2. UPDATE SETTINGS (Admin only)
    if ($action === 'update') {
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Only administrators can modify system settings.']);
            exit;
        }
        $allowedKeys = [
            'capacity', 'overstay_hours', 'mall_name',
            'printer_type', 'printer_name', 'printer_paper_width',
            'whatsapp_enabled', 'whatsapp_template_entry', 'whatsapp_template_exit',
            'alpr_confidence_threshold', 'alpr_debounce_seconds',
            'cloud_sync_enabled', 'cloud_sync_url', 'language_default', 'dpa_notice',
            'nvr_ip', 'nvr_port', 'nvr_rtsp_port', 'nvr_channel_entrance', 'nvr_channel_exit',
            'camera_entrance_ip', 'camera_exit_ip',
        ];

        $updates = $input['settings'] ?? [];
        if (!is_array($updates) || !$updates) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'No settings supplied.']); exit; }
        $bounds = ['capacity'=>[1,10000],'overstay_hours'=>[2,168],'alpr_confidence_threshold'=>[0,1],'alpr_debounce_seconds'=>[1,3600]];
        foreach ($updates as $key=>$value) {
            if (!in_array($key,$allowedKeys,true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Unknown setting: '.$key]); exit; }
            if (isset($bounds[$key]) && (!is_numeric($value) || $value<$bounds[$key][0] || $value>$bounds[$key][1])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid value for '.$key]); exit; }
            if (in_array($key,['capacity','overstay_hours','alpr_debounce_seconds'],true) && filter_var($value,FILTER_VALIDATE_INT)===false) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Use a whole number for '.$key]); exit; }
            if ($key==='printer_paper_width' && !in_array((string)$value,['58','80'],true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Choose 58 or 80 mm paper.']); exit; }
            if ($key==='cloud_sync_url' && $value!=='' && !preg_match('~^https?://~i',(string)$value)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Use an HTTP or HTTPS cloud address.']); exit; }
        }
        $db->beginTransaction();
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

        $db->commit();
        echo json_encode(['ok' => true, 'message' => 'System settings updated successfully.']);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('SETTINGS API ERROR: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
