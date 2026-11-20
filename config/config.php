<?php
/**
 * Mombasa Mall Basement Parking Management System
 * Master Configuration File
 *
 * Public defaults and hardware settings. Deployment secrets are loaded from
 * protected private configuration or environment variables below.
 */

// Strict error reporting for reliability (log errors, display off in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php_error.log');

// Timezone requirement: Africa/Nairobi
date_default_timezone_set('Africa/Nairobi');

require_once __DIR__ . '/camera_config.php';

$config = [
    // ---------------------------------------------------------
    // Application & Mall Info
    // ---------------------------------------------------------
    'app' => [
        'name'            => 'Mombasa Mall Basement Parking',
        'short_name'      => 'MM Parking',
        'code_prefix'     => 'MM',
        'capacity'        => 60,
        'overstay_hours'  => 8,
        'timezone'        => 'Africa/Nairobi',
        'base_url'        => '/parking', // Auto-adjusted if running in subfolder
        'version'         => '1.0.0',
        'environment'     => 'production', // 'development' or 'production'
    ],

    // ---------------------------------------------------------
    // Database Configuration (cPanel Production)
    // Local dev override is loaded from config.local.php if present
    // ---------------------------------------------------------
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'mombasam_parking',
        'username' => 'mombasam_admin',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // ---------------------------------------------------------
    // Network & Hardware Topology (Direct Camera & NVR Network: 192.168.1.0/24)
    // Edge PC Host: 192.168.1.7 (Ethernet) / 192.168.1.106 (Wi-Fi)
    // Entrance ITC Camera: 192.168.1.230
    // Exit ITC Camera:     192.168.1.210
    // Dahua NVR:           192.168.1.3 (Fallback: 192.168.0.3)
    // ---------------------------------------------------------
    'network' => [
        'nic1_lan_ip'        => '192.168.1.7',
        'nic2_cctv_ip'       => '192.168.1.50',
        'cam_entrance_ip'    => '192.168.1.230',
        'cam_exit_ip'        => '192.168.1.210',
        'nvr_cctv_ip'        => '192.168.1.3',
        'nvr_lan_ip'         => '192.168.1.3',
        'nvr_ip'             => '192.168.1.3',
        'nvr_rtsp_port'      => 554,
    ],

    // ---------------------------------------------------------
    // ALPR & Dahua Camera Streams (Credentials supplied by protected deployment configuration)
    // Direct Access to Entrance (192.168.1.230) & Exit (192.168.1.210)
    // ---------------------------------------------------------
    'alpr' => [
        'api_key' => '',
        'min_confidence'     => 0.70,
        'debounce_seconds'   => 8,
        'voting_frames'      => 2,
        'source_mode'        => 'dahua_anpr', // Native Dahua ANPR HTTP Event Stream
        // NVR Master Credentials (Dahua NVR at 192.168.1.3, channels 1 & 2)
        'nvr' => [
            'ip'               => '192.168.1.3',
            'ip_cctv'          => '192.168.1.3',
            'ip_lan'           => '192.168.1.3',
            'username'         => 'admin',
            'password' => '',
            'rtsp_port'        => 554,
            'channel_entrance' => 1,
            'channel_exit'     => 2,
            'event_url'        => 'http://192.168.1.3/cgi-bin/eventManager.cgi?action=attach&codes=[Traffic,TrafficTollGate,PlateDetection,All]',
        ],
        // Entrance ANPR Camera (Dahua ITC413 Direct: 192.168.1.230 - Channel 1)
        'entrance' => [
            'ip'               => '192.168.1.230',
            'username'         => 'admin',
            'password' => '',
            'nvr_channel'      => 1,
            'rtsp_main'        => 'rtsp://192.168.1.230:554/cam/realmonitor?channel=1&subtype=0',
            'rtsp_sub'         => 'rtsp://192.168.1.230:554/cam/realmonitor?channel=1&subtype=1',
            'snapshot_url'     => 'http://192.168.1.230/cgi-bin/snapshot.cgi?channel=1',
            'nvr_snapshot_url' => 'https://192.168.1.3/cgi-bin/snapshot.cgi?channel=1',
            'event_url'        => 'http://192.168.1.230/cgi-bin/eventManager.cgi?action=attach&codes=[Traffic,TrafficTollGate,PlateDetection,All]',
            'nvr_rtsp'         => 'rtsp://192.168.1.3:554/cam/realmonitor?channel=1&subtype=0',
        ],
        // Exit ANPR Camera (Dahua ITC413 Direct: 192.168.1.210 - Channel 2)
        'exit' => [
            'ip'               => '192.168.1.210',
            'username'         => 'admin',
            'password' => '',
            'nvr_channel'      => 2,
            'rtsp_main'        => 'rtsp://192.168.1.210:554/cam/realmonitor?channel=1&subtype=0',
            'rtsp_sub'         => 'rtsp://192.168.1.210:554/cam/realmonitor?channel=1&subtype=1',
            'snapshot_url'     => 'http://192.168.1.210/cgi-bin/snapshot.cgi?channel=1',
            'nvr_snapshot_url' => 'https://192.168.1.3/cgi-bin/snapshot.cgi?channel=2',
            'event_url'        => 'http://192.168.1.210/cgi-bin/eventManager.cgi?action=attach&codes=[Traffic,TrafficTollGate,PlateDetection,All]',
            'nvr_rtsp'         => 'rtsp://192.168.1.3:554/cam/realmonitor?channel=2&subtype=0',
        ],
    ],

    // ---------------------------------------------------------
    // Thermal Receipt Printer (ESC/POS)
    // ---------------------------------------------------------
    'printer' => [
        // Mode options: 'windows' (WindowsPrintConnector), 'network' (NetworkPrintConnector), 'dummy' (Safe test mode)
        'mode'         => 'dummy',
        // Windows shared printer name e.g. "POS80" or "smb://localhost/POS80"
        'printer_name' => 'POS80',
        'network_ip'   => '192.168.0.200',
        'network_port' => 9100,
        'paper_width'  => 80, // 58 or 80 mm
        'header_line1' => 'MOMBASA MALL',
        'header_line2' => 'BASEMENT PARKING - 60 SLOTS',
        'footer_text'  => 'Keep this ticket. Free parking. Present ticket to guard at exit.',
        'auto_cut'     => true,
    ],

    // ---------------------------------------------------------
    // WhatsApp Business Cloud API (Meta)
    // ---------------------------------------------------------
    'whatsapp' => [
        'enabled'          => false, // Set to true once Meta API credentials are active
        'api_version'      => 'v19.0',
        'phone_number_id'  => 'YOUR_PHONE_NUMBER_ID',
        'token'            => 'YOUR_META_PERMANENT_ACCESS_TOKEN',
        'template_entry'   => 'mombasa_parking_ticket',
        'template_exit'    => 'mombasa_parking_exit',
        'business_name'    => 'Mombasa Mall Security',
    ],

    // ---------------------------------------------------------
    // Cloud Synchronization Mirror
    // ---------------------------------------------------------
    'cloud_sync' => [
        'enabled'          => false, // Set true once remote cPanel server is configured
        'endpoint'         => 'https://cloud.mombasamall.co.ke/remote/api/ingest.php',
        'api_key' => '',
        'hmac_secret' => '',
        'batch_size'       => 50,
        'timeout_seconds'  => 10,
    ],

    // ---------------------------------------------------------
    // Security & Authentication
    // ---------------------------------------------------------
    'security' => [
        'session_name'        => 'MM_PARKING_SESS',
        'session_lifetime'    => 28800, // 8 hours (typical guard shift)
        'csrf_secret' => '',
        'guard_idle_timeout'  => 900,   // 15 minutes auto-lock
        'rate_limit_driver'   => [
            'max_requests'    => 5,
            'window_seconds'  => 600, // 10 minutes
        ],
    ],

    // ---------------------------------------------------------
    // Storage Paths
    // ---------------------------------------------------------
    'paths' => [
        'snapshots' => __DIR__ . '/../storage/snapshots',
        'backups'   => 'D:\\ParkingBackups', // Nightly backup directory
        'logs'      => __DIR__ . '/../storage/logs',
    ],
];

// Load secrets here so direct configuration readers and background workers agree.
$secretsFile = __DIR__ . '/secrets.local.php';
if (is_file($secretsFile)) {
    $secrets = require $secretsFile;
    if (!is_array($secrets)) throw new RuntimeException('Invalid deployment secrets configuration.');
    $config = array_replace_recursive($config, $secrets);
}
foreach ([
    'PARKING_DB_PASSWORD' => ['db', 'password'],
    'PARKING_ALPR_API_KEY' => ['alpr', 'api_key'],
    'PARKING_CLOUD_API_KEY' => ['cloud_sync', 'api_key'],
    'PARKING_CLOUD_HMAC_SECRET' => ['cloud_sync', 'hmac_secret'],
] as $variable => [$section, $key]) {
    $value = getenv($variable);
    if ($value !== false) $config[$section][$key] = $value;
}
foreach (['nvr', 'entrance', 'exit'] as $device) {
    foreach (['username', 'password'] as $key) {
        $value = getenv('PARKING_' . strtoupper($device . '_' . $key));
        if ($value !== false) $config['alpr'][$device][$key] = $value;
    }
}
$config['firebase'] = [
    'service_account_file' => getenv('PARKING_FIREBASE_SERVICE_ACCOUNT') ?: __DIR__ . '/firebase-service-account.local.json',
    'project_id' => 'mombasa-mall-parking',
];
return lock_parking_camera_config($config);
