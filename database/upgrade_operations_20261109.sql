-- Additive operational records. Existing visits and entry times are preserved.
CREATE TABLE IF NOT EXISTS parking_shifts (
 id INT AUTO_INCREMENT PRIMARY KEY,
 guard_id INT NOT NULL,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ended_at DATETIME NULL,
 opening_count INT NOT NULL,
 physical_count INT NULL,
 closing_count INT NULL,
 handover_notes TEXT NULL,
 INDEX idx_shift_guard (guard_id, ended_at),
 INDEX idx_shift_start (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS parking_actions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 session_id INT NOT NULL,
 user_id INT NOT NULL,
 shift_id INT NULL,
 authorized_by INT NULL,
 action_type VARCHAR(30) NOT NULL,
 notes TEXT NOT NULL,
 resolved_at DATETIME NULL,
 resolved_by INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_action_session (session_id, id),
 INDEX idx_action_shift (shift_id, id),
 INDEX idx_action_unresolved (resolved_at, session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS parking_extensions (
 session_id INT PRIMARY KEY,
 approved_until DATETIME NOT NULL,
 approved_by INT NOT NULL,
 reason TEXT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS visit_destinations (
 entity_type ENUM('visitor','session') NOT NULL,
 entity_id INT NOT NULL,
 destination_id INT NOT NULL,
 destination_name VARCHAR(150) NOT NULL,
 position INT NOT NULL,
 PRIMARY KEY(entity_type, entity_id, destination_id),
 INDEX idx_visit_store (destination_id, entity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
