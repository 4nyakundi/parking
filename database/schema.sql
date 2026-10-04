-- ====================================================================
-- Mombasa Mall Basement Parking Management System (60 Slots)
-- Complete MariaDB / MySQL Schema with Permanent Data Preservation Rules
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `mombasa_parking` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mombasa_parking`;

-- Disable foreign key checks during creation
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------
-- 1. USERS & ROLES
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('guard','supervisor','admin') NOT NULL DEFAULT 'guard',
    `pin_hash` VARCHAR(255) NULL COMMENT 'Bcrypt hash of 4-digit PIN for guards',
    `password_hash` VARCHAR(255) NULL COMMENT 'Bcrypt hash for supervisor/admin login',
    `phone` VARCHAR(20) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 2. REGISTERED VEHICLES (VIP, Staff, Tenants, Blacklisted)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registered_vehicles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plate_number` VARCHAR(20) NOT NULL UNIQUE,
    `owner_name` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `vehicle_type` ENUM('car','motorcycle','van','truck','government','diplomatic','other') NOT NULL DEFAULT 'car',
    `category` ENUM('regular','vip','staff','tenant','blacklisted') NOT NULL DEFAULT 'regular',
    `notes` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reg_plate` (`plate_number`),
    INDEX `idx_reg_cat` (`category`),
    INDEX `idx_reg_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 3. VISITORS / DRIVER SELF SIGN-IN REQUESTS
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `visitors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plate_number` VARCHAR(20) NOT NULL,
    `driver_name` VARCHAR(100) NOT NULL,
    `driver_phone` VARCHAR(20) NOT NULL,
    `destination` VARCHAR(100) NOT NULL,
    `source` ENUM('self_signin','manual_guard','alpr_only') NOT NULL DEFAULT 'self_signin',
    `status` ENUM('PENDING','APPROVED','REJECTED','EXPIRED') NOT NULL DEFAULT 'PENDING',
    `reject_reason` VARCHAR(255) NULL,
    `alpr_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `handled_by` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_vis_status` (`status`),
    INDEX `idx_vis_plate` (`plate_number`),
    INDEX `idx_vis_created` (`created_at`),
    CONSTRAINT `fk_visitors_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 4. PARKING SESSIONS (Primary Business Entity - Permanent Data)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parking_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Formatted MM-YYYYMMDD-XXXX',
    `plate_number` VARCHAR(20) NOT NULL,
    `driver_name` VARCHAR(100) NOT NULL,
    `driver_phone` VARCHAR(20) NOT NULL,
    `destination` VARCHAR(100) NOT NULL,
    `entry_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `exit_time` DATETIME NULL,
    `status` ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
    `duration_minutes` INT NULL,
    `entry_snapshot` VARCHAR(255) NULL,
    `exit_snapshot` VARCHAR(255) NULL,
    `approved_by` INT NULL,
    `cleared_by` INT NULL,
    `entry_method` ENUM('self_signin','manual','alpr') NOT NULL DEFAULT 'self_signin',
    `exit_method` VARCHAR(50) NULL DEFAULT 'guard_scan',
    `whatsapp_status` ENUM('not_sent','queued','sent','failed') NOT NULL DEFAULT 'not_sent',
    `print_status` ENUM('pending','printed','failed') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `synced_to_cloud` TINYINT(1) NOT NULL DEFAULT 0,
    `synced_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sess_status` (`status`),
    INDEX `idx_sess_plate` (`plate_number`),
    INDEX `idx_sess_entry` (`entry_time`),
    INDEX `idx_sess_sync` (`synced_to_cloud`),
    CONSTRAINT `fk_sess_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sess_cleared_by` FOREIGN KEY (`cleared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 5. DESTINATIONS
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `destinations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'store',
    INDEX `idx_dest_sort` (`sort_order`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 6. ALPR DETECTIONS
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alpr_detections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `camera` ENUM('entrance','exit') NOT NULL,
    `plate_raw` VARCHAR(30) NOT NULL,
    `plate_clean` VARCHAR(20) NOT NULL,
    `confidence` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `snapshot_path` VARCHAR(255) NULL,
    `matched_request_id` INT NULL,
    `matched_session_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_alpr_clean` (`plate_clean`),
    INDEX `idx_alpr_cam_time` (`camera`, `created_at`),
    CONSTRAINT `fk_alpr_req` FOREIGN KEY (`matched_request_id`) REFERENCES `visitors` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_alpr_sess` FOREIGN KEY (`matched_session_id`) REFERENCES `parking_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 7. REALTIME EVENTS FEED (Feeds 2-second Short Polling)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(50) NOT NULL,
    `payload` JSON NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_events_id` (`id`),
    INDEX `idx_events_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 8. AUDIT LOG (Permanent Immutable Audit Trail)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity` VARCHAR(50) NOT NULL,
    `entity_id` VARCHAR(50) NULL,
    `old_value` JSON NULL,
    `new_value` JSON NULL,
    `ip` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_created` (`created_at`),
    INDEX `idx_audit_entity` (`entity`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 9. SYSTEM SETTINGS
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    `value` TEXT NOT NULL,
    `description` VARCHAR(255) NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 10. DEVICE HEALTH
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `device_health` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device` ENUM('entrance_cam','exit_cam','printer','alpr_worker','internet','cloud_sync') NOT NULL UNIQUE,
    `status` ENUM('OK','WARNING','ERROR','OFFLINE') NOT NULL DEFAULT 'OK',
    `last_checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `message` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 11. BACKUPS LOG
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL,
    `size` BIGINT NOT NULL DEFAULT 0,
    `file_path` VARCHAR(255) NOT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 12. WHATSAPP OUTBOX QUEUE
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `template_name` VARCHAR(100) NOT NULL,
    `params` JSON NOT NULL,
    `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    `attempts` INT NOT NULL DEFAULT 0,
    `error_message` TEXT NULL,
    `last_attempt_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wa_status` (`status`),
    CONSTRAINT `fk_wa_sess` FOREIGN KEY (`session_id`) REFERENCES `parking_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- PERMANENT DATA RETENTION: MYSQL TRIGGERS BLOCKING HARD DELETES
-- ====================================================================
DROP TRIGGER IF EXISTS `trg_block_delete_sessions`;
DELIMITER //
CREATE TRIGGER `trg_block_delete_sessions`
BEFORE DELETE ON `parking_sessions`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'PERMANENT RETENTION ERROR: DELETE on parking_sessions is strictly forbidden by mall audit policy.';
END;
//
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_block_delete_audit`;
DELIMITER //
CREATE TRIGGER `trg_block_delete_audit`
BEFORE DELETE ON `audit_log`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'PERMANENT RETENTION ERROR: DELETE on audit_log is strictly forbidden by security compliance.';
END;
//
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_block_delete_visitors`;
DELIMITER //
CREATE TRIGGER `trg_block_delete_visitors`
BEFORE DELETE ON `visitors`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'PERMANENT RETENTION ERROR: DELETE on visitors is strictly forbidden.';
END;
//
DELIMITER ;

-- ====================================================================
-- SEED INITIAL DATA
-- ====================================================================

-- 1. System Users (Default admin, supervisor, guards)
-- Default admin password: "admin123" (forced change at first login)
-- Supervisor PIN: 9999, password: "super123"
-- Guard 1 PIN: 1234
-- Guard 2 PIN: 5678
INSERT INTO `users` (`id`, `username`, `full_name`, `role`, `pin_hash`, `password_hash`, `phone`, `is_active`, `must_change_password`) VALUES
(1, 'admin', 'System Administrator', 'admin', NULL, '$2y$10$VnIj8aawPkUFawEf8SRy0u3qgoStfz0I4vWX3sAd4q8LuDgyX0Kq2', '254711000001', 1, 0),
(2, 'supervisor', 'Security Supervisor', 'supervisor', '$2y$10$HpQFaoaPOWJa8YMkFweTa.ZRA0k5zLWoewJ5S..npuDsNUqBM/G02', '$2y$10$3k/NO1R3isRgR/B47AjuFedd7bAuAN0d.2eIYAtD7iJQDpmbsDFMi', '254711000002', 1, 0),
(3, 'guard1', 'John Omondi (Gate 1)', 'guard', '$2y$10$oU0fG8MM.hUsKTdzyfL2rOVJpRzC8UCps8MCqlhvtRkMMCwMxnhc6', NULL, '254711000003', 1, 0),
(4, 'guard2', 'Ali Hassan (Gate 2)', 'guard', '$2y$10$.jnnnHKhVwnVMeYgpkbeE.gnrkbvmof1w93Z7A.Df3dK6aFNe6k0a', NULL, '254711000004', 1, 0)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `password_hash` = VALUES(`password_hash`), `pin_hash` = VALUES(`pin_hash`);

-- 2. Destinations (Mombasa Mall tenants — comprehensive list)
INSERT INTO `destinations` (`id`, `name`, `sort_order`, `is_active`, `icon`) VALUES
(1,  'Naivas Supermarket',     1,  1, 'store'),
(2,  'NCBA Bank',              2,  1, 'bank'),
(3,  'Food Court',             3,  1, 'restaurant'),
(4,  'Chicken Inn',            4,  1, 'restaurant'),
(5,  'Gym & Fitness Centre',   5,  1, 'fitness'),
(6,  'Gamers Vault',           6,  1, 'gaming'),
(7,  'Pharmacy / Chemist',     7,  1, 'medical'),
(8,  'Mobile & Electronics',   8,  1, 'electronics'),
(9,  'Fashion & Clothing',     9,  1, 'clothing'),
(10, 'Salon & Beauty',         10, 1, 'beauty'),
(11, 'Optician / Eyewear',     11, 1, 'eyewear'),
(12, 'Kids Play Zone',         12, 1, 'kids'),
(13, 'ATM / Cash Point',       13, 1, 'atm'),
(14, 'Real Estate Office',     14, 1, 'office'),
(15, 'Mall Administration',    15, 1, 'admin'),
(16, 'Security / Management',  16, 1, 'security'),
(17, 'Other Retail Stores',    17, 1, 'store'),
(18, 'Loading / Deliveries',   18, 1, 'truck')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`), `icon` = VALUES(`icon`);

-- 3. System Settings
INSERT INTO `system_settings` (`key`, `value`, `description`) VALUES
('capacity', '60', 'Total basement parking slot capacity'),
('overstay_hours', '8', 'Duration in hours before parking session is flagged as overstay'),
('mall_name', 'Mombasa Mall Basement Parking', 'Display name on receipts, banners and reports'),
('whatsapp_enabled', '0', 'Enable Meta WhatsApp Cloud API messaging (1=on, 0=off)'),
('whatsapp_template_entry', 'mombasa_parking_ticket', 'Pre-approved WhatsApp template name for entry'),
('whatsapp_template_exit', 'mombasa_parking_exit', 'Pre-approved WhatsApp template name for exit'),
('printer_type', 'windows', 'Printer connector type: windows, network, or file'),
('printer_name', 'POS80', 'Windows shared printer share name'),
('printer_paper_width', '80', 'Receipt paper width in mm (58 or 80)'),
('alpr_confidence_threshold', '0.70', 'Minimum OCR confidence score to accept detection'),
('alpr_debounce_seconds', '15', 'Time window in seconds to ignore duplicate detections'),
('cloud_sync_enabled', '0', 'Enable syncing to remote cloud management server'),
('cloud_sync_url', 'https://cloud.mombasamall.co.ke/remote/api/ingest.php', 'Cloud ingestion endpoint'),
('language_default', 'en', 'Default interface language (en or sw)'),
('dpa_notice', 'Data collected is strictly for security and parking management under Kenya Data Protection Act 2019.', 'Data protection statement')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- 4. Initial Device Health Records
INSERT INTO `device_health` (`device`, `status`, `last_checked_at`, `message`) VALUES
('entrance_cam', 'OK', NOW(), 'Camera stream active (RTSP 192.168.1.1:554/101)'),
('exit_cam', 'OK', NOW(), 'Camera stream active (RTSP 192.168.1.1:554/201)'),
('printer', 'OK', NOW(), 'Windows printer spooler ready'),
('alpr_worker', 'OK', NOW(), 'ALPR inference worker running on Edge PC'),
('internet', 'OK', NOW(), 'Mall LAN/Internet interface reachable'),
('cloud_sync', 'OK', NOW(), 'Cloud mirror standby')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 5. Seed Vehicles (Sample VIP, Staff, Blacklisted for immediate testing)
INSERT INTO `registered_vehicles` (`plate_number`, `owner_name`, `phone`, `vehicle_type`, `category`, `notes`, `is_active`) VALUES
('KDA123A', 'Hon. Hassan Joho', '254722111222', 'car', 'vip', 'VIP Parking - Mall Director priority reserved slot', 1),
('KDB456B', 'Naivas Manager - Dennis', '254733444555', 'car', 'tenant', 'Naivas Store Manager Basement bay', 1),
('KDC789C', 'Security Officer Maina', '254700999888', 'motorcycle', 'staff', 'Mall Security Team Lead', 1),
('KBZ999X', 'Suspicious Subject', '254712000000', 'car', 'blacklisted', 'DO NOT ADMIT - Suspect in vehicle theft incident 12/2025. Alert Mall Police immediately.', 1)
ON DUPLICATE KEY UPDATE `owner_name` = VALUES(`owner_name`);

SET FOREIGN_KEY_CHECKS = 1;
