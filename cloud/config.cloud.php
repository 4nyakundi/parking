<?php
/**
 * Mombasa Mall Basement Parking - Remote Cloud Management Configuration
 * Standard PHP + MySQL configuration for cPanel shared hosting.
 */

return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'mombasa_parking_cloud',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
    'security' => [
        'api_key'     => 'MOMBASA_CLOUD_SYNC_KEY_987654321',
        'hmac_secret' => 'SEC_HMAC_MOMBASA_MALL_CLOUD_SIGNING_SECRET_2026',
    ],
    'mall' => [
        'name'     => 'Mombasa Mall Basement Parking',
        'capacity' => 60,
    ],
];
