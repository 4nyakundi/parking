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
    `source` ENUM('self_signin','manual_guard','alpr_only','alpr_camera') NOT NULL DEFAULT 'self_signin',
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
-- 5. DESTINATIONS (Mombasa Mall Shops & Facilities)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `destinations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL UNIQUE,
    `unit_code` VARCHAR(20) NOT NULL DEFAULT '',
    `floor_level` VARCHAR(50) NOT NULL DEFAULT 'Ground Floor',
    `category` VARCHAR(50) NOT NULL DEFAULT 'Retail',
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'store',
    INDEX `idx_dest_sort` (`sort_order`, `is_active`),
    INDEX `idx_dest_floor` (`floor_level`)
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

-- 2. Destinations (Mombasa Mall Full Directory: Ground, 1st, 2nd, 3rd Floor & Basement)
INSERT INTO `destinations` (`id`, `name`, `unit_code`, `floor_level`, `category`, `description`, `sort_order`, `is_active`, `icon`) VALUES
-- Ground Floor (Level G) — Hypermarket & Fast Food
(1,  'G-01 Naivas Supermarket',                         'G-01', 'Ground Floor', 'Hypermarket',        '24/7 Anchor Hypermarket (Fresh bakery, organic produce, butchery, pharmacy & liquor store).', 101, 1, 'store'),
(2,  'G-02 Chicken Inn',                                'G-02', 'Ground Floor', 'Fast Food',          'Fast-casual restaurant serving crispy fried chicken, chips, and family meals.',                102, 1, 'restaurant'),
(3,  'G-03 Creamy Inn',                                 'G-03', 'Ground Floor', 'Fast Food',          'Soft-serve ice creams, decadent sundaes, thick milkshakes, and waffles.',                      103, 1, 'restaurant'),
(4,  'G-04 Pizza Inn',                                  'G-04', 'Ground Floor', 'Fast Food',          'Freshly rolled artisan pizzas, classic pepperoni, peri-peri chicken, and pizza specials.',     104, 1, 'restaurant'),

-- 1st Floor (Level 1) — Fashion, Shoes, Tech, Beauty & Banking (17 Stores)
(5,  'F-01 Naivas First Floor',                         'F-01', '1st Floor',    'Department Store',   'Department store extension (Home appliances, kitchenware, bedding & electronics).',            201, 1, 'store'),
(6,  'F-02 Hallahulah Game Shop',                       'F-02', '1st Floor',    'Gaming',             'Video game consoles (PS5, Xbox, Nintendo), VR equipment, and gaming gear.',                    202, 1, 'gaming'),
(7,  'F-03 Daliah Human Wigs',                          'F-03', '1st Floor',    'Beauty',             'Luxury virgin human hair extensions, lace front wigs, and styling products.',                  203, 1, 'beauty'),
(8,  'F-04 Payless',                                    'F-04', '1st Floor',    'Fashion & Shoes',    'Trendsetting footwear, mobile phones, and family fashion accessories.',                        204, 1, 'clothing'),
(9,  'F-05 Grand Computer',                             'F-05', '1st Floor',    'Tech & PC',          'Laptops, desktop PCs, monitors, hardware upgrades, and IT repairs.',                           205, 1, 'electronics'),
(10, 'F-06 Tickles and Giggles',                        'F-06', '1st Floor',    'Kids Apparel',       'Childrens apparel, baby essentials, toys, and school bags.',                                   206, 1, 'kids'),
(11, 'F-07 Namada Healthcare',                          'F-07', '1st Floor',    'Healthcare',         'Clinical retail pharmacy, diagnostic testing, and health supplements.',                         207, 1, 'medical'),
(12, 'F-08 Online Holidays',                            'F-08', '1st Floor',    'Travel',             'Holiday travel packages, flight ticketing, safaris, and hotel bookings.',                      208, 1, 'office'),
(13, 'F-09 West 11',                                    'F-09', '1st Floor',    'Streetwear',         'Coastal streetwear, designer apparel, and lifestyle fashion.',                                 209, 1, 'clothing'),
(14, 'F-10 7day Mensware',                              'F-10', '1st Floor',    'Menswear',           'Formal suits, blazers, smart-casual shirts, trousers, and mens shoes.',                        210, 1, 'clothing'),
(15, 'F-11 KG Cosmetics',                              'F-11', '1st Floor',    'Beauty & Perfumes',  'Designer perfumes, makeup, organic skincare, and luxury fragrances.',                          211, 1, 'beauty'),
(16, 'F-12 Lovisa',                                     'F-12', '1st Floor',    'Jewellery',          'On-trend fashion jewellery, sterling silver, gold-plated earrings, and hair accessories.',     212, 1, 'store'),
(17, 'F-13 Fakri Timezone',                             'F-13', '1st Floor',    'Watches & Eyewear',  'Wristwatches, chronographs, sunglasses, and optical prescription frames.',                      213, 1, 'eyewear'),
(18, 'F-14 Dendri (Denri Africa)',                      'F-14', '1st Floor',    'Leather Goods',      'Handcrafted leather handbags, backpacks, duffels, and wallets.',                               214, 1, 'clothing'),
(19, 'F-15 NCBA Bank',                                  'F-15', '1st Floor',    'Banking',            'Full-service bank branch, forex exchange, loan desks, and 24/7 ATM lobby.',                    215, 1, 'bank'),
(20, 'F-16 Diamond Tech',                               'F-16', '1st Floor',    'Security Tech',      'CCTV security systems, biometric access control, intercoms, and networking.',                  216, 1, 'electronics'),
(21, 'F-17 Africa Collectives',                         'F-17', '1st Floor',    'Curios & Artefacts', 'Handwoven African baskets, beaded jewellery, coastal artefacts, and souvenirs.',               217, 1, 'store'),

-- 2nd Floor (Level 2) — Entertainment, Boutiques, Salon Spas & Dining (19 Stores)
(22, 'S-01 Play On (Game Area)',                        'S-01', '2nd Floor',    'Entertainment',      'Large family entertainment arena (VR simulators, soft play, arcade games & party rooms).',     301, 1, 'gaming'),
(23, 'S-02 Creamy / Chicken / Pizza Inn Kiosk',         'S-02', '2nd Floor',    'Fast Food',          'Express food counter for fast bites, slices, and ice creams.',                                 302, 1, 'restaurant'),
(24, 'S-03 Jumia / Skyve Studio / Xtigi Service Center', 'S-03', '2nd Floor',   'Hub & Tech',         'E-commerce parcel hub, photo studio, and device repairs.',                                     303, 1, 'electronics'),
(25, 'S-04 World Designers',                            'S-04', '2nd Floor',    'Tailoring & Couture','Bespoke tailoring, evening gowns, African prints, and alterations.',                           304, 1, 'clothing'),
(26, 'S-05 SAS Beauty',                                 'S-05', '2nd Floor',    'Salon & Nails',      'Hair braiding, wig installations, blowouts, manicure, pedicure, and bridal glam.',             305, 1, 'beauty'),
(27, 'S-06 Novum',                                      'S-06', '2nd Floor',    'Spa Sanctuary',      'Luxury spa sanctuary, facials, deep-tissue massage, nail bar, and lash extensions.',            306, 1, 'beauty'),
(28, 'S-07 Ident Smile',                                'S-07', '2nd Floor',    'Dental Clinic',      'Modern dental clinic (teeth whitening, orthodontics, cleaning, fillings & root canals).',      307, 1, 'medical'),
(29, 'S-08 Tawal ICT Solution',                         'S-08', '2nd Floor',    'Enterprise IT',      'Enterprise IT solutions, custom software deployment, and computer accessories.',              308, 1, 'electronics'),
(30, 'S-09 Mintos Salon Spa Barbershop',                'S-09', '2nd Floor',    'Grooming & Barber',  'Executive mens grooming, hot towel shaves, spa therapies, and beard styling.',                  309, 1, 'beauty'),
(31, 'S-10 SSB',                                        'S-10', '2nd Floor',    'Womens Boutique',    'Womens boutique fashion, handbags, cosmetics, and lifestyle accessories.',                      310, 1, 'clothing'),
(32, 'S-11 Rudra',                                      'S-11', '2nd Floor',    'Ethnic Wear',        'Indian ethnic wear, silk sarees, kurtis, embroidered fabrics, and bridal lehengas.',           311, 1, 'clothing'),
(33, 'S-12 West 11 Sports',                             'S-12', '2nd Floor',    'Sportswear',         'Athletic trainers, sportswear, football jerseys, and gym accessories.',                        312, 1, 'fitness'),
(34, 'S-13 Osona Yarns',                                'S-13', '2nd Floor',    'Crafts & Yarns',     'Knitting yarns, sewing threads, haberdashery, and DIY craft materials.',                       313, 1, 'store'),
(35, 'S-14 Afribot Robotics',                           'S-14', '2nd Floor',    'STEM Academy',       'STEM academy offering kids coding, robotics, 3D printing, and AI workshops.',                   314, 1, 'electronics'),
(36, 'S-15 Luxe Kaftan',                                'S-15', '2nd Floor',    'Bridal & Modest',    'Bridal kaftans, silk abayas, evening gowns, and Swahili royal couture.',                      315, 1, 'clothing'),
(37, 'S-16 Malkia',                                     'S-16', '2nd Floor',    'Modest Fashion',     'Modest wear, chic abayas, stylish hijabs, and Arabian luxury accessories.',                     316, 1, 'clothing'),
(38, 'S-17 Michael Boutique',                           'S-17', '2nd Floor',    'Cocktail & Office',  'Sophisticated cocktail dresses, office wear, and designer handbags.',                          317, 1, 'clothing'),
(39, 'S-18 Oraimo',                                     'S-18', '2nd Floor',    'Smart Accessories',  'Smart accessories (power banks, wireless earbuds, smartwatches & fast chargers).',              318, 1, 'electronics'),
(40, 'S-19 Keswick',                                    'S-19', '2nd Floor',    'Books & Gifts',      'Christian books, Bibles, motivational literature, stationery, and gifts.',                      319, 1, 'store'),

-- 3rd Floor (Level 3) — Coworking, Gym, Esports & Medical Clinic (4 Anchors)
(41, 'T-01 Gamers Vault',                               'T-01', '3rd Floor',    'Esports Lounge',     'Flagship esports lounge (PS5 4K stations, high-refresh gaming PCs & tournaments).',             401, 1, 'gaming'),
(42, 'T-02 Legacy Gym',                                 'T-02', '3rd Floor',    'Gym & Fitness',      'Health and fitness club (free weights, cardio deck, sauna, aerobics & personal training).',    402, 1, 'fitness'),
(43, 'T-03 Westerwelle Foundation / Startup Haus',      'T-03', '3rd Floor',    'Tech Incubator',     'Tech incubator, coworking desks, meeting suites & event hall.',                                 403, 1, 'office'),
(44, 'T-04 Equity Afia CBD',                            'T-04', '3rd Floor',    'Medical Center',     'Comprehensive outpatient medical center, doctor consultations, lab & pharmacy.',              404, 1, 'medical'),

-- Basement (Level B1) — Parking & Valet Services (2 Services)
(45, 'B-01 Valet & Parking Services',                   'B-01', 'Basement',     'Valet Concierge',    'Professional valet concierge desk, ticket validation, and drop-off assistance.',               501, 1, 'truck'),
(46, 'B-02 Secure Basement Parking Deck',               'B-02', 'Basement',     'Parking',            '400+ secure, well-lit vehicle bays with 24/7 CCTV surveillance and automated access booms.',   502, 1, 'truck'),

-- General Mall Facilities
(47, 'Mall Administration & Management',                'ADM',  'Ground Floor', 'Management',         'Mombasa Mall central property management, security control, and leasing office.',               601, 1, 'admin'),
(48, 'Loading Bay & Deliveries',                        'LOG',  'Basement',     'Logistics',          'Dedicated commercial delivery bay, tenant goods receiving, and freight access.',               602, 1, 'truck')
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`), 
    `unit_code` = VALUES(`unit_code`),
    `floor_level` = VALUES(`floor_level`),
    `category` = VALUES(`category`),
    `description` = VALUES(`description`),
    `sort_order` = VALUES(`sort_order`), 
    `icon` = VALUES(`icon`);

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
('dpa_notice', 'Data collected is strictly for security and parking management under Kenya Data Protection Act 2019.', 'Data protection statement'),
('nvr_cctv_ip', '192.168.1.3', 'Dahua NVR CCTV internal network IP'),
('nvr_lan_ip', '192.168.0.3', 'Dahua NVR internet/LAN interface IP'),
('cam_entrance_ip', '192.168.1.230', 'Dahua Entrance ANPR camera IP'),
('cam_exit_ip', '192.168.1.210', 'Dahua Exit ANPR camera IP'),
('cam_username', 'admin', 'Master camera username'),
('cam_password', 'Mall@2024', 'Master camera password')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- 4. Initial Device Health Records
INSERT INTO `device_health` (`device`, `status`, `last_checked_at`, `message`) VALUES
('entrance_cam', 'OK', NOW(), 'Dahua Entrance ANPR Camera (192.168.1.230) Online'),
('exit_cam', 'OK', NOW(), 'Dahua Exit ANPR Camera (192.168.1.210) Online'),
('printer', 'OK', NOW(), 'Windows printer spooler ready'),
('alpr_worker', 'OK', NOW(), 'ALPR inference worker running on Edge PC'),
('internet', 'OK', NOW(), 'Mall LAN/Internet interface reachable'),
('cloud_sync', 'OK', NOW(), 'Cloud mirror standby')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `message` = VALUES(`message`);

-- 5. Seed Vehicles (Sample VIP, Staff, Blacklisted for immediate testing)
INSERT INTO `registered_vehicles` (`plate_number`, `owner_name`, `phone`, `vehicle_type`, `category`, `notes`, `is_active`) VALUES
('KDA123A', 'Hon. Hassan Joho', '254722111222', 'car', 'vip', 'VIP Parking - Mall Director priority reserved slot', 1),
('KDB456B', 'Naivas Manager - Dennis', '254733444555', 'car', 'tenant', 'Naivas Store Manager Basement bay', 1),
('KDC789C', 'Security Officer Maina', '254700999888', 'motorcycle', 'staff', 'Mall Security Team Lead', 1),
('KBZ999X', 'Suspicious Subject', '254712000000', 'car', 'blacklisted', 'DO NOT ADMIT - Suspect in vehicle theft incident 12/2025. Alert Mall Police immediately.', 1)
ON DUPLICATE KEY UPDATE `owner_name` = VALUES(`owner_name`);

SET FOREIGN_KEY_CHECKS = 1;
