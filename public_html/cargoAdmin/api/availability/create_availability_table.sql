-- ============================================================================
-- VEHICLE AVAILABILITY CALENDAR - Database Schema
-- ============================================================================

-- Table to store blocked dates for vehicles
CREATE TABLE IF NOT EXISTS `vehicle_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `vehicle_type` enum('car','motorcycle') NOT NULL DEFAULT 'car',
  `blocked_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL COMMENT 'Reason for blocking (maintenance, personal use, etc.)',
  `is_recurring` tinyint(1) DEFAULT 0 COMMENT '1 if recurring weekly block',
  `recurring_day` tinyint(1) DEFAULT NULL COMMENT 'Day of week (0=Sunday, 6=Saturday)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_owner_vehicle` (`owner_id`, `vehicle_id`, `vehicle_type`),
  KEY `idx_blocked_date` (`blocked_date`),
  KEY `idx_vehicle_date` (`vehicle_id`, `vehicle_type`, `blocked_date`),
  CONSTRAINT `fk_availability_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Index for efficient date range queries
CREATE INDEX idx_date_range ON vehicle_availability(vehicle_id, vehicle_type, blocked_date);
