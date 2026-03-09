-- ============================================================================
-- CarGO Database Migrations
-- Complete SQL migration scripts for missing tables and fixes
-- ============================================================================

-- ============================================================================
-- 1. VEHICLE AVAILABILITY TABLE
-- Stores blocked dates for vehicles (owner-managed availability)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `vehicle_availability` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `owner_id` INT(11) NOT NULL,
    `vehicle_id` INT(11) NOT NULL,
    `vehicle_type` VARCHAR(20) NOT NULL DEFAULT 'car',
    `blocked_date` DATE NOT NULL,
    `reason` VARCHAR(255) DEFAULT 'Blocked by owner',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_block` (`vehicle_id`, `vehicle_type`, `blocked_date`),
    KEY `idx_owner_id` (`owner_id`),
    KEY `idx_vehicle` (`vehicle_id`, `vehicle_type`),
    KEY `idx_blocked_date` (`blocked_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. ARCHIVED NOTIFICATIONS TABLE
-- Stores archived notifications (moved from active notifications)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `archived_notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `original_id` INT(11) NOT NULL COMMENT 'Original notification ID',
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) DEFAULT 'info',
    `read_status` ENUM('read', 'unread') DEFAULT 'unread',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was originally created',
    `archived_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was archived',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_original_id` (`original_id`),
    KEY `idx_archived_at` (`archived_at`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. ADD MISSING COLUMNS TO NOTIFICATIONS TABLE
-- Add type column if it doesn't exist
-- ============================================================================
ALTER TABLE `notifications` 
ADD COLUMN IF NOT EXISTS `type` VARCHAR(50) DEFAULT 'info' AFTER `message`,
ADD INDEX IF NOT EXISTS `idx_type` (`type`),
ADD INDEX IF NOT EXISTS `idx_read_status` (`read_status`),
ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`);

-- ============================================================================
-- 4. ADMIN NOTIFICATIONS TABLE (if not exists)
-- For admin-specific notifications
-- ============================================================================
CREATE TABLE IF NOT EXISTS `admin_notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(50) DEFAULT 'info',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL COMMENT 'Link to related page',
    `icon` VARCHAR(50) DEFAULT 'info' COMMENT 'Icon identifier',
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `read_status` ENUM('read', 'unread') DEFAULT 'unread',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_priority` (`priority`),
    KEY `idx_read_status` (`read_status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. INDEXES FOR PERFORMANCE OPTIMIZATION
-- Add missing indexes to improve query performance
-- ============================================================================

-- Bookings table indexes
ALTER TABLE `bookings`
ADD INDEX IF NOT EXISTS `idx_vehicle_dates` (`car_id`, `vehicle_type`, `pickup_date`, `return_date`),
ADD INDEX IF NOT EXISTS `idx_status_dates` (`status`, `pickup_date`, `return_date`),
ADD INDEX IF NOT EXISTS `idx_owner_status` (`owner_id`, `status`);

-- Cars table indexes
ALTER TABLE `cars`
ADD INDEX IF NOT EXISTS `idx_owner_status` (`owner_id`, `status`);

-- Motorcycles table indexes (if exists)
ALTER TABLE `motorcycles`
ADD INDEX IF NOT EXISTS `idx_owner_status` (`owner_id`, `status`);

-- Reviews table indexes
ALTER TABLE `reviews`
ADD INDEX IF NOT EXISTS `idx_owner_rating` (`owner_id`, `rating`);

-- ============================================================================
-- 6. CLEANUP - Remove old vulnerable files (documentation only)
-- ============================================================================
-- NOTE: Manually delete the following files from server:
-- - cargoAdmin/delete_notification.php (vulnerable to SQL injection)
-- These files should be removed manually for security

-- ============================================================================
-- 7. DATA INTEGRITY CHECKS
-- ============================================================================

-- Ensure all bookings have proper vehicle_type
UPDATE `bookings` SET `vehicle_type` = 'car' 
WHERE `vehicle_type` IS NULL OR `vehicle_type` = '';

-- Set default notification types for existing notifications
UPDATE `notifications` SET `type` = 'info' 
WHERE `type` IS NULL OR `type` = '';

-- ============================================================================
-- 8. STORED PROCEDURE - Get Available Dates for Vehicle
-- Helper procedure to check vehicle availability
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_get_vehicle_availability`$$

CREATE PROCEDURE `sp_get_vehicle_availability`(
    IN p_vehicle_id INT,
    IN p_vehicle_type VARCHAR(20),
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    -- Get blocked dates
    SELECT 
        blocked_date as date,
        'blocked' as status,
        reason
    FROM vehicle_availability
    WHERE vehicle_id = p_vehicle_id
        AND vehicle_type = p_vehicle_type
        AND blocked_date BETWEEN p_start_date AND p_end_date
    
    UNION ALL
    
    -- Get booked dates (expanded to include all dates in booking range)
    SELECT 
        DATE_ADD(b.pickup_date, INTERVAL n.n DAY) as date,
        'booked' as status,
        CONCAT('Booked (Booking #', b.id, ')') as reason
    FROM bookings b
    CROSS JOIN (
        SELECT 0 as n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL 
        SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL 
        SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL 
        SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL 
        SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL 
        SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL 
        SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL 
        SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL 
        SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL SELECT 35 UNION ALL 
        SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL 
        SELECT 40 UNION ALL SELECT 41 UNION ALL SELECT 42 UNION ALL SELECT 43 UNION ALL 
        SELECT 44 UNION ALL SELECT 45 UNION ALL SELECT 46 UNION ALL SELECT 47 UNION ALL 
        SELECT 48 UNION ALL SELECT 49 UNION ALL SELECT 50 UNION ALL SELECT 51 UNION ALL 
        SELECT 52 UNION ALL SELECT 53 UNION ALL SELECT 54 UNION ALL SELECT 55 UNION ALL 
        SELECT 56 UNION ALL SELECT 57 UNION ALL SELECT 58 UNION ALL SELECT 59 UNION ALL 
        SELECT 60 UNION ALL SELECT 61 UNION ALL SELECT 62 UNION ALL SELECT 63 UNION ALL 
        SELECT 64 UNION ALL SELECT 65 UNION ALL SELECT 66 UNION ALL SELECT 67 UNION ALL 
        SELECT 68 UNION ALL SELECT 69 UNION ALL SELECT 70 UNION ALL SELECT 71 UNION ALL 
        SELECT 72 UNION ALL SELECT 73 UNION ALL SELECT 74 UNION ALL SELECT 75 UNION ALL 
        SELECT 76 UNION ALL SELECT 77 UNION ALL SELECT 78 UNION ALL SELECT 79 UNION ALL 
        SELECT 80 UNION ALL SELECT 81 UNION ALL SELECT 82 UNION ALL SELECT 83 UNION ALL 
        SELECT 84 UNION ALL SELECT 85 UNION ALL SELECT 86 UNION ALL SELECT 87 UNION ALL 
        SELECT 88 UNION ALL SELECT 89 UNION ALL SELECT 90
    ) n
    WHERE b.car_id = p_vehicle_id
        AND b.vehicle_type = p_vehicle_type
        AND b.status IN ('pending', 'approved', 'ongoing')
        AND DATE_ADD(b.pickup_date, INTERVAL n.n DAY) <= b.return_date
        AND DATE_ADD(b.pickup_date, INTERVAL n.n DAY) BETWEEN p_start_date AND p_end_date
    
    ORDER BY date;
END$$

DELIMITER ;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Run this script on your database to apply all migrations
-- Command: mysql -u root -p dbcargo < database_migrations.sql
-- ============================================================================
