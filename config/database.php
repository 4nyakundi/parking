<?php
/**
 * Mombasa Mall Basement Parking Management System
 * Database Connection Provider (PDO Singleton)
 */

declare(strict_types=1);

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/config.php';
        $dbCfg = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbCfg['host'],
            $dbCfg['port'],
            $dbCfg['dbname'],
            $dbCfg['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+03:00'",
        ];

        try {
            $pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password'], $options);
        } catch (PDOException $e) {
            // Log full error safely on server
            error_log('Database Connection Failure: ' . $e->getMessage());

            // If it's an API request, return clean JSON
            if (php_sapi_name() !== 'cli') {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Database connection failed. Please ensure MySQL is running in XAMPP.',
                ]);
                exit;
            }

            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    return $pdo;
}
