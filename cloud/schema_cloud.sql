-- ====================================================================
-- Mombasa Mall Remote Cloud Management Mirror Schema
-- For deployment on cPanel / Shared Hosting
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `mombasa_parking_cloud` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mombasa_parking_cloud`;

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

CREATE TABLE IF NOT EXISTS `cloud_edge_heartbeats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `edge_pc` VARCHAR(100) NOT NULL,
    `records_count` INT NOT NULL DEFAULT 0,
    `ip_address` VARCHAR(45) NOT NULL,
    `last_synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Management User: username 'management', password 'mall2026'
INSERT INTO `cloud_users` (`id`, `username`, `password_hash`, `full_name`, `role`, `is_active`)
VALUES (1, 'management', '$2y$10$WPwioSipT6x1fLePU5q7VuCQ.sEaoaaenlRwPkaizwfWxUg9wK1ZC', 'Mall Property Executive', 'management', 1)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `full_name` = VALUES(`full_name`);
