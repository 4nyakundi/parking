-- ====================================================================
-- Mombasa Mall Basement Parking - Demo Seed Data
-- Run this to populate realistic sessions, pending visitor requests,
-- detections, and audit log entries for testing.
-- ====================================================================

USE `mombasa_parking`;

-- 1. Pending Driver Requests (Visitors waiting for Guard Approval)
INSERT IGNORE INTO `visitors` (`plate_number`, `driver_name`, `driver_phone`, `destination`, `source`, `status`, `alpr_verified`, `created_at`) VALUES
('KDA456C', 'Samuel Mwangi', '254712345678', 'Naivas Supermarket', 'self_signin', 'PENDING', 1, DATE_SUB(NOW(), INTERVAL 3 MINUTE)),
('KCA789X', 'Amina Mohamed', '254722334455', 'Banking Hall', 'self_signin', 'PENDING', 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
('KDG101Y', 'Kevin Otieno', '254733112233', 'Gym & Fitness', 'self_signin', 'PENDING', 1, DATE_SUB(NOW(), INTERVAL 1 MINUTE));

-- 2. Active Parking Sessions (Vehicles currently in basement)
INSERT IGNORE INTO `parking_sessions` (`ticket_id`, `plate_number`, `driver_name`, `driver_phone`, `destination`, `entry_time`, `status`, `approved_by`, `entry_method`, `whatsapp_status`, `print_status`, `synced_to_cloud`) VALUES
('MM-20261003-0001', 'KDA123A', 'Hon. Hassan Joho', '254722111222', 'Mall Administration', DATE_SUB(NOW(), INTERVAL 9 HOUR), 'ACTIVE', 3, 'self_signin', 'queued', 'printed', 0),
('MM-20261003-0002', 'KBZ345M', 'Fatma Said', '254799887766', 'Naivas Supermarket', DATE_SUB(NOW(), INTERVAL 145 MINUTE), 'ACTIVE', 3, 'self_signin', 'queued', 'printed', 0),
('MM-20261003-0003', 'KCD567P', 'Brian Kiprop', '254788554433', 'Food Court', DATE_SUB(NOW(), INTERVAL 45 MINUTE), 'ACTIVE', 4, 'manual', 'queued', 'printed', 0),
('MM-20261003-0004', 'KMDA234K', 'Rider Peter Kamau', '254777112233', 'Naivas Supermarket', DATE_SUB(NOW(), INTERVAL 20 MINUTE), 'ACTIVE', 3, 'self_signin', 'queued', 'printed', 0);

-- 3. Completed Sessions (Vehicles that already visited and departed today)
INSERT IGNORE INTO `parking_sessions` (`ticket_id`, `plate_number`, `driver_name`, `driver_phone`, `destination`, `entry_time`, `exit_time`, `status`, `duration_minutes`, `approved_by`, `cleared_by`, `entry_method`, `exit_method`, `print_status`, `synced_to_cloud`) VALUES
('MM-20261003-0005', 'KDA888D', 'David Mutua', '254744332211', 'Banking Hall', DATE_SUB(NOW(), INTERVAL 180 MINUTE), DATE_SUB(NOW(), INTERVAL 60 MINUTE), 'COMPLETED', 120, 3, 3, 'self_signin', 'guard_scan', 'printed', 1),
('MM-20261003-0006', 'KCB999L', 'Grace Wanjiku', '254755667788', 'Other Retail Stores', DATE_SUB(NOW(), INTERVAL 240 MINUTE), DATE_SUB(NOW(), INTERVAL 150 MINUTE), 'COMPLETED', 90, 4, 3, 'self_signin', 'guard_scan', 'printed', 1);

-- 4. Sample ALPR Detections
INSERT IGNORE INTO `alpr_detections` (`camera`, `plate_raw`, `plate_clean`, `confidence`, `created_at`) VALUES
('entrance', 'KDA 456C', 'KDA456C', 0.94, DATE_SUB(NOW(), INTERVAL 3 MINUTE)),
('entrance', 'KDG 101Y', 'KDG101Y', 0.91, DATE_SUB(NOW(), INTERVAL 1 MINUTE));

-- 5. Audit Log Seed
INSERT IGNORE INTO `audit_log` (`user_id`, `action`, `entity`, `entity_id`, `old_value`, `new_value`, `ip`, `created_at`) VALUES
(3, 'APPROVE_ENTRY', 'parking_sessions', 'MM-20261003-0001', NULL, JSON_OBJECT('plate', 'KDA123A', 'ticket', 'MM-20261003-0001'), '192.168.0.50', DATE_SUB(NOW(), INTERVAL 9 HOUR)),
(3, 'APPROVE_ENTRY', 'parking_sessions', 'MM-20261003-0002', NULL, JSON_OBJECT('plate', 'KBZ345M', 'ticket', 'MM-20261003-0002'), '192.168.0.50', DATE_SUB(NOW(), INTERVAL 145 MINUTE)),
(3, 'CLEAR_EXIT', 'parking_sessions', 'MM-20261003-0005', JSON_OBJECT('status', 'ACTIVE'), JSON_OBJECT('status', 'COMPLETED', 'duration', 120), '192.168.0.50', DATE_SUB(NOW(), INTERVAL 60 MINUTE));
