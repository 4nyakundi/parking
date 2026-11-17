<?php
/**
 * Mombasa Mall Cloud Mirror - Auto Schema Initializer
 * Automatically ensures cloud tables exist in the target database.
 */

declare(strict_types=1);

function ensure_cloud_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `cloud_users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) UNIQUE NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `full_name` VARCHAR(100) NOT NULL,
                `role` ENUM('management','executive') NOT NULL DEFAULT 'management',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `cloud_parking_sessions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `edge_session_id` INT NOT NULL,
                `ticket_id` VARCHAR(32) NOT NULL UNIQUE,
                `plate_number` VARCHAR(20) NOT NULL,
                `driver_name` VARCHAR(100) NOT NULL,
                `driver_phone` VARCHAR(20) NOT NULL,
                `destination` VARCHAR(100) NOT NULL,
                `entry_time` DATETIME NOT NULL,
                `exit_time` DATETIME NULL,
                `status` ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
                `duration_minutes` INT NULL,
                `entry_method` VARCHAR(30) NULL,
                `exit_method` VARCHAR(30) NULL,
                `notes` TEXT NULL,
                `synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_cloud_ticket` (`ticket_id`),
                INDEX `idx_cloud_status` (`status`),
                INDEX `idx_cloud_entry` (`entry_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `registered_vehicles` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `plate_number` VARCHAR(20) NOT NULL UNIQUE,
                `owner_name` VARCHAR(100) NULL,
                `phone` VARCHAR(20) NULL,
                `vehicle_type` ENUM('car','motorcycle','van','truck','government','diplomatic','other') NOT NULL DEFAULT 'car',
                `category` ENUM('regular','vip','staff','tenant','blacklisted') NOT NULL DEFAULT 'regular',
                `notes` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `visit_count` INT NOT NULL DEFAULT 0,
                `is_frequent` TINYINT(1) NOT NULL DEFAULT 0,
                `default_destination` VARCHAR(150) NULL,
                `last_visit_at` DATETIME NULL,
                `driver_name` VARCHAR(100) NULL,
                `driver_phone` VARCHAR(20) NULL,
                `profile_archived` TINYINT(1) NOT NULL DEFAULT 0,
                `profile_reset_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_cloud_vehicle_plate` (`plate_number`),
                INDEX `idx_cloud_vehicle_category` (`category`),
                INDEX `idx_cloud_vehicle_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `cloud_edge_heartbeats` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `edge_pc` VARCHAR(100) NOT NULL,
                `records_count` INT NOT NULL DEFAULT 0,
                `ip_address` VARCHAR(45) NOT NULL,
                `last_synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Create the initial management user only when a deployment password is configured.
        $chk = $pdo->query("SELECT COUNT(*) FROM `cloud_users` WHERE `username` = 'management'")->fetchColumn();
        if ((int)$chk === 0) {
            $initialPassword = getenv('CLOUD_INITIAL_ADMIN_PASSWORD');
            if (is_string($initialPassword) && $initialPassword !== '') {
                $passHash = password_hash($initialPassword, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO `cloud_users` (`username`, `password_hash`, `full_name`, `role`, `is_active`)
                    VALUES ('management', :hash, 'Mall Property Executive', 'management', 1)
                ");
                $stmt->execute([':hash' => $passHash]);
            } else {
                error_log('CLOUD SCHEMA INIT: Set CLOUD_INITIAL_ADMIN_PASSWORD to create the initial management account.');
            }
        }

        $checked = true;
    } catch (Throwable $e) {
        error_log('CLOUD SCHEMA INIT ERROR: ' . $e->getMessage());
    }
}
