<?php
/**
 * Mombasa Mall Basement Parking Management System
 * Master Configuration File
 *
 * NOTE: All system secrets, network addresses, camera streams, printer details,
 * and credentials are centrally defined here.
 */

// Strict error reporting for reliability (log errors, display off in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php_error.log');

// Timezone requirement: Africa/Nairobi
date_default_timezone_set('Africa/Nairobi');

return [
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
    // Database Configuration (XAMPP MySQL / MariaDB)
    // ---------------------------------------------------------
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'mombasa_parking',
        'username' => 'root',
        'password' => '', // Default XAMPP root password is blank
        'charset'  => 'utf8mb4',
    ],

    // ---------------------------------------------------------
    // Network & Hardware Topology
    // Edge PC: NIC 1 (192.168.0.50), NIC 2 (192.168.1.50, no gateway)
    // NVR: 192.168.1.1
    // ---------------------------------------------------------
    'network' => [
        'nic1_lan_ip'        => '192.168.0.50',
        'nic2_cctv_ip'       => '192.168.1.50',
        'nvr_ip'             => '192.168.1.1',
        'nvr_rtsp_port'      => 554,
    ],

    // ---------------------------------------------------------
    // ALPR & Camera Streams
    // ---------------------------------------------------------
    'alpr' => [
        'api_key'            => 'MOMBASA_PARKING_ALPR_SECRET_KEY_2026',
        'min_confidence'     => 0.70,
        'debounce_seconds'   => 15,
        'voting_frames'      => 2,
        'source_mode'        => 'rtsp', // 'rtsp' or 'isapi_snapshot'
        'entrance' => [
            'rtsp_main'      => 'rtsp://admin:password@192.168.1.1:554/Streaming/Channels/101',
            'rtsp_sub'       => 'rtsp://admin:password@192.168.1.1:554/Streaming/Channels/102',
            'isapi_snapshot' => 'http://192.168.1.1/ISAPI/Streaming/channels/101/picture',
        ],
        'exit' => [
            'rtsp_main'      => 'rtsp://admin:password@192.168.1.1:554/Streaming/Channels/201',
            'rtsp_sub'       => 'rtsp://admin:password@192.168.1.1:554/Streaming/Channels/202',
            'isapi_snapshot' => 'http://192.168.1.1/ISAPI/Streaming/channels/201/picture',
        ],
    ],

    // ---------------------------------------------------------
    // Thermal Receipt Printer (ESC/POS)
    // ---------------------------------------------------------
    'printer' => [
        // Mode options: 'windows' (WindowsPrintConnector), 'network' (NetworkPrintConnector), 'file' (Dummy/test)
        'mode'         => 'windows',
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
        'api_key'          => 'MOMBASA_CLOUD_SYNC_KEY_987654321',
        'hmac_secret'      => 'SEC_HMAC_MOMBASA_MALL_CLOUD_SIGNING_SECRET_2026',
        'batch_size'       => 50,
        'timeout_seconds'  => 10,
    ],

    // ---------------------------------------------------------
    // Security & Authentication
    // ---------------------------------------------------------
    'security' => [
        'session_name'        => 'MM_PARKING_SESS',
        'session_lifetime'    => 28800, // 8 hours (typical guard shift)
        'csrf_secret'         => 'MOMBASA_CSRF_TOKEN_KEY_RANDOM_SALT_2026',
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
