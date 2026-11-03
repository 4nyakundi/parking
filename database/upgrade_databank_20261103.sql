-- Data Bank upgrade for EXISTING installations - 2026-11-03
-- Select your parking database in phpMyAdmin, then import this file.
-- Requires existing registered_vehicles and parking_sessions tables.
-- Compatible with MySQL / MariaDB, no stored routine permissions needed.
-- Repeatable: existing columns, profiles and index are preserved.
-- No camera configuration, visit history, ownership or access category changes.
-- MySQL schema changes commit immediately. Stop on any import error.

-- Preserve the registry before changing it. Keep this backup until verified.
SET @databank_backup = CONCAT('registered_vehicles_backup_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '_', LEFT(REPLACE(UUID(), '-', ''), 8));
SET @databank_sql = CONCAT('CREATE TABLE `', @databank_backup, '` LIKE registered_vehicles');
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;
SET @databank_sql = CONCAT('INSERT INTO `', @databank_backup, '` SELECT * FROM registered_vehicles');
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add visit_count only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'visit_count'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `visit_count` INT NOT NULL DEFAULT 0'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add is_frequent only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'is_frequent'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `is_frequent` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add default_destination only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'default_destination'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `default_destination` VARCHAR(150) NULL'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add last_visit_at only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'last_visit_at'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `last_visit_at` DATETIME NULL'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add driver_name only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'driver_name'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `driver_name` VARCHAR(100) NULL'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add driver_phone only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'driver_phone'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `driver_phone` VARCHAR(20) NULL'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add last_confirmed_session_id only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'last_confirmed_session_id'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `last_confirmed_session_id` INT NULL'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add profile_confirmed_by only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'profile_confirmed_by'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `profile_confirmed_by` INT NULL'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add profile_confirmed_at only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'profile_confirmed_at'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `profile_confirmed_at` DATETIME NULL'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add profile_archived only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'profile_archived'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `profile_archived` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Add profile_reset_at only if missing.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registered_vehicles' AND COLUMN_NAME = 'profile_reset_at'),
    'SELECT 1',
    'ALTER TABLE registered_vehicles ADD COLUMN `profile_reset_at` DATETIME NULL'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Speed up per-vehicle history queries.
SET @databank_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parking_sessions' AND INDEX_NAME = 'idx_sess_plate_entry'),
    'SELECT 1',
    'CREATE INDEX idx_sess_plate_entry ON parking_sessions (plate_number, entry_time, id)'
);
PREPARE databank_stmt FROM @databank_sql;
EXECUTE databank_stmt;
DEALLOCATE PREPARE databank_stmt;

-- Rebuild factual visit summaries for existing profiles.
-- Never copy historical driver identities into saved profiles or owner fields.
-- Historical plates without registry rows remain available through visit lookup.
-- Optional bulk profile creation and uncertain-plate report: use the PHP CLI migration.
UPDATE registered_vehicles r
LEFT JOIN (
    SELECT plate_number, COUNT(*) AS visits, MAX(entry_time) AS last_seen
    FROM parking_sessions
    WHERE status IN ('ACTIVE', 'COMPLETED') AND driver_name <> 'Uncollected Driver'
    GROUP BY plate_number
) s ON s.plate_number = r.plate_number
SET r.visit_count = COALESCE(s.visits, 0),
    r.is_frequent = (COALESCE(s.visits, 0) >= 3),
    r.last_visit_at = s.last_seen;

SELECT 'Data Bank upgrade complete' AS result, DATABASE() AS upgraded_database, @databank_backup AS registry_backup_table;
