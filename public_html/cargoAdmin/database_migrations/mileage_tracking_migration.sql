-- =====================================================
-- MILEAGE TRACKING SYSTEM - DATABASE MIGRATION
-- Created: 2026-02-01
-- Purpose: Enable mileage monitoring and excess charges
-- =====================================================

-- =====================================================
-- STEP 1: UPDATE CARS TABLE
-- =====================================================
ALTER TABLE `cars`
ADD COLUMN `daily_mileage_limit` INT DEFAULT NULL COMMENT 'Daily mileage limit in KM (NULL if unlimited)',
ADD COLUMN `excess_mileage_rate` DECIMAL(10,2) DEFAULT 10.00 COMMENT 'Cost per excess KM in PHP';

-- Update existing cars: Set limits for those without unlimited mileage
UPDATE `cars` SET 
    `daily_mileage_limit` = 200,
    `excess_mileage_rate` = 10.00
WHERE `has_unlimited_mileage` = 0;

-- =====================================================
-- STEP 2: UPDATE MOTORCYCLES TABLE
-- =====================================================
ALTER TABLE `motorcycles`
ADD COLUMN `daily_mileage_limit` INT DEFAULT NULL COMMENT 'Daily mileage limit in KM (NULL if unlimited)',
ADD COLUMN `excess_mileage_rate` DECIMAL(10,2) DEFAULT 10.00 COMMENT 'Cost per excess KM in PHP';

-- Update existing motorcycles: Set limits for those without unlimited mileage
UPDATE `motorcycles` SET 
    `daily_mileage_limit` = 150,
    `excess_mileage_rate` = 10.00
WHERE `has_unlimited_mileage` = 0;

-- =====================================================
-- STEP 3: UPDATE BOOKINGS TABLE - ODOMETER TRACKING
-- =====================================================
ALTER TABLE `bookings`
ADD COLUMN `odometer_start` INT DEFAULT NULL COMMENT 'Starting odometer reading in KM',
ADD COLUMN `odometer_end` INT DEFAULT NULL COMMENT 'Ending odometer reading in KM',
ADD COLUMN `odometer_start_photo` VARCHAR(255) DEFAULT NULL COMMENT 'Photo of starting odometer',
ADD COLUMN `odometer_end_photo` VARCHAR(255) DEFAULT NULL COMMENT 'Photo of ending odometer',
ADD COLUMN `odometer_start_timestamp` DATETIME DEFAULT NULL COMMENT 'When start odometer was recorded',
ADD COLUMN `odometer_end_timestamp` DATETIME DEFAULT NULL COMMENT 'When end odometer was recorded',
ADD COLUMN `actual_mileage` INT DEFAULT NULL COMMENT 'Calculated distance driven (end - start)',
ADD COLUMN `allowed_mileage` INT DEFAULT NULL COMMENT 'Total allowed mileage for this booking',
ADD COLUMN `excess_mileage` INT DEFAULT 0 COMMENT 'Mileage over limit (0 if within)',
ADD COLUMN `excess_mileage_fee` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Excess mileage charge in PHP',
ADD COLUMN `excess_mileage_paid` TINYINT(1) DEFAULT 0 COMMENT '1 if excess fee paid',
ADD COLUMN `mileage_verified_by` INT DEFAULT NULL COMMENT 'Admin ID who verified mileage',
ADD COLUMN `mileage_verified_at` DATETIME DEFAULT NULL COMMENT 'When mileage was verified',
ADD COLUMN `mileage_notes` TEXT DEFAULT NULL COMMENT 'Notes about mileage (disputes, adjustments, etc.)',
ADD COLUMN `gps_distance` DECIMAL(10,2) DEFAULT NULL COMMENT 'Distance calculated from GPS tracking (KM)';

-- Add foreign key for mileage verifier
ALTER TABLE `bookings`
ADD CONSTRAINT `fk_mileage_verifier` 
FOREIGN KEY (`mileage_verified_by`) REFERENCES `admin`(`id`) 
ON DELETE SET NULL;

-- =====================================================
-- STEP 4: CREATE MILEAGE DISPUTES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `mileage_disputes` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL,
  `user_id` INT NOT NULL COMMENT 'Renter who filed dispute',
  `owner_id` INT NOT NULL COMMENT 'Vehicle owner',
  `dispute_type` ENUM('incorrect_reading', 'odometer_tampered', 'calculation_error', 'photo_unclear', 'other') NOT NULL,
  `reported_odometer_start` INT DEFAULT NULL COMMENT 'Owner/system reported start',
  `reported_odometer_end` INT DEFAULT NULL COMMENT 'Owner/system reported end',
  `claimed_odometer_start` INT DEFAULT NULL COMMENT 'Renter claimed start',
  `claimed_odometer_end` INT DEFAULT NULL COMMENT 'Renter claimed end',
  `reported_mileage` INT NOT NULL COMMENT 'Mileage calculated by system',
  `claimed_mileage` INT NOT NULL COMMENT 'Mileage claimed by renter',
  `gps_distance` DECIMAL(10,2) DEFAULT NULL COMMENT 'GPS-tracked distance for reference',
  `evidence_photos` TEXT DEFAULT NULL COMMENT 'JSON array of additional photo paths',
  `description` TEXT NOT NULL COMMENT 'Detailed explanation of dispute',
  `status` ENUM('pending', 'under_review', 'resolved_favor_renter', 'resolved_favor_owner', 'rejected', 'withdrawn') DEFAULT 'pending',
  `resolution` TEXT DEFAULT NULL COMMENT 'Admin decision and explanation',
  `resolved_by` INT DEFAULT NULL COMMENT 'Admin ID who resolved',
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`resolved_by`) REFERENCES `admin`(`id`) ON DELETE SET NULL,
  
  INDEX `idx_booking` (`booking_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- STEP 5: CREATE MILEAGE TRACKING LOG TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `mileage_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL,
  `log_type` ENUM('start_recorded', 'end_recorded', 'excess_calculated', 'excess_paid', 'dispute_filed', 'admin_verified', 'admin_adjusted') NOT NULL,
  `recorded_by` INT NOT NULL COMMENT 'User or admin ID',
  `recorded_by_type` ENUM('renter', 'owner', 'admin') NOT NULL,
  `odometer_value` INT DEFAULT NULL,
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `gps_latitude` DOUBLE DEFAULT NULL,
  `gps_longitude` DOUBLE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `metadata` TEXT DEFAULT NULL COMMENT 'JSON data: device info, timestamp, etc.',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  INDEX `idx_booking` (`booking_id`),
  INDEX `idx_log_type` (`log_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- STEP 6: CREATE GPS DISTANCE TRACKING TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `gps_distance_tracking` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL,
  `total_distance_km` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cumulative distance in KM',
  `last_latitude` DOUBLE DEFAULT NULL,
  `last_longitude` DOUBLE DEFAULT NULL,
  `last_updated` DATETIME DEFAULT NULL,
  `waypoints_count` INT DEFAULT 0 COMMENT 'Number of GPS points recorded',
  `calculation_method` VARCHAR(50) DEFAULT 'haversine' COMMENT 'Distance calculation algorithm used',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- STEP 7: CREATE MILEAGE STATISTICS VIEW
-- =====================================================
CREATE OR REPLACE VIEW `v_mileage_statistics` AS
SELECT 
    b.id AS booking_id,
    b.car_id,
    b.vehicle_type,
    b.user_id,
    b.owner_id,
    b.pickup_date,
    b.return_date,
    DATEDIFF(b.return_date, b.pickup_date) + 1 AS rental_days,
    b.odometer_start,
    b.odometer_end,
    b.actual_mileage,
    b.allowed_mileage,
    b.excess_mileage,
    b.excess_mileage_fee,
    b.gps_distance,
    CASE 
        WHEN b.actual_mileage IS NOT NULL AND b.gps_distance IS NOT NULL 
        THEN ABS(b.actual_mileage - b.gps_distance)
        ELSE NULL 
    END AS odometer_gps_discrepancy,
    CASE 
        WHEN b.actual_mileage IS NOT NULL AND b.gps_distance IS NOT NULL 
        THEN ROUND((ABS(b.actual_mileage - b.gps_distance) / b.actual_mileage) * 100, 2)
        ELSE NULL 
    END AS discrepancy_percentage,
    b.mileage_verified_by,
    b.mileage_verified_at,
    CASE 
        WHEN b.excess_mileage > 0 AND b.excess_mileage_paid = 1 THEN 'paid'
        WHEN b.excess_mileage > 0 AND b.excess_mileage_paid = 0 THEN 'unpaid'
        ELSE 'no_excess'
    END AS excess_status,
    u.fullname AS renter_name,
    o.fullname AS owner_name
FROM bookings b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN users o ON b.owner_id = o.id
WHERE b.status IN ('completed', 'active');

-- =====================================================
-- STEP 8: ADD INDEXES FOR PERFORMANCE
-- =====================================================
ALTER TABLE `bookings` 
ADD INDEX `idx_odometer_tracking` (`odometer_start`, `odometer_end`, `actual_mileage`),
ADD INDEX `idx_excess_mileage` (`excess_mileage`, `excess_mileage_paid`);

-- =====================================================
-- STEP 9: CREATE TRIGGERS FOR AUTOMATIC CALCULATIONS
-- =====================================================
DELIMITER $$

-- Trigger: Auto-calculate actual mileage when end odometer is set
CREATE TRIGGER `trg_calculate_actual_mileage` 
BEFORE UPDATE ON `bookings`
FOR EACH ROW
BEGIN
    -- Only calculate if both start and end are set, and actual hasn't been manually set
    IF NEW.odometer_start IS NOT NULL 
       AND NEW.odometer_end IS NOT NULL 
       AND NEW.odometer_end > NEW.odometer_start
       AND NEW.actual_mileage IS NULL THEN
        SET NEW.actual_mileage = NEW.odometer_end - NEW.odometer_start;
    END IF;
END$$

-- Trigger: Auto-calculate allowed mileage based on vehicle settings
CREATE TRIGGER `trg_calculate_allowed_mileage`
BEFORE UPDATE ON `bookings`
FOR EACH ROW
BEGIN
    DECLARE v_daily_limit INT;
    DECLARE v_rental_days INT;
    
    -- Calculate rental days
    IF NEW.pickup_date IS NOT NULL AND NEW.return_date IS NOT NULL THEN
        SET v_rental_days = DATEDIFF(NEW.return_date, NEW.pickup_date) + 1;
        
        -- Get daily limit based on vehicle type
        IF NEW.vehicle_type = 'car' THEN
            SELECT daily_mileage_limit INTO v_daily_limit 
            FROM cars 
            WHERE id = NEW.car_id;
        ELSE
            SELECT daily_mileage_limit INTO v_daily_limit 
            FROM motorcycles 
            WHERE id = NEW.car_id;
        END IF;
        
        -- Calculate allowed mileage (NULL if unlimited)
        IF v_daily_limit IS NOT NULL THEN
            SET NEW.allowed_mileage = v_daily_limit * v_rental_days;
        ELSE
            SET NEW.allowed_mileage = NULL;
        END IF;
    END IF;
END$$

-- Trigger: Auto-calculate excess mileage and fee
CREATE TRIGGER `trg_calculate_excess_mileage`
BEFORE UPDATE ON `bookings`
FOR EACH ROW
BEGIN
    DECLARE v_excess_rate DECIMAL(10,2);
    
    -- Only calculate if actual mileage is set and allowed is set
    IF NEW.actual_mileage IS NOT NULL AND NEW.allowed_mileage IS NOT NULL THEN
        -- Calculate excess mileage
        IF NEW.actual_mileage > NEW.allowed_mileage THEN
            SET NEW.excess_mileage = NEW.actual_mileage - NEW.allowed_mileage;
            
            -- Get excess rate
            IF NEW.vehicle_type = 'car' THEN
                SELECT excess_mileage_rate INTO v_excess_rate 
                FROM cars 
                WHERE id = NEW.car_id;
            ELSE
                SELECT excess_mileage_rate INTO v_excess_rate 
                FROM motorcycles 
                WHERE id = NEW.car_id;
            END IF;
            
            -- Calculate fee
            IF v_excess_rate IS NOT NULL THEN
                SET NEW.excess_mileage_fee = NEW.excess_mileage * v_excess_rate;
            END IF;
        ELSE
            SET NEW.excess_mileage = 0;
            SET NEW.excess_mileage_fee = 0.00;
        END IF;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- STEP 10: INSERT DEFAULT PLATFORM SETTINGS
-- =====================================================
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) 
VALUES 
    ('mileage_tracking_enabled', '1', 'boolean', 'Enable mileage tracking system'),
    ('default_daily_mileage_limit', '200', 'integer', 'Default daily mileage limit in KM'),
    ('default_excess_rate', '10.00', 'decimal', 'Default excess mileage rate per KM'),
    ('gps_distance_tracking_enabled', '1', 'boolean', 'Enable GPS-based distance calculation'),
    ('odometer_photo_required', '1', 'boolean', 'Require photos of odometer readings'),
    ('mileage_discrepancy_threshold', '20', 'integer', 'Max % difference between GPS and odometer before flagging'),
    ('auto_verify_mileage', '0', 'boolean', 'Auto-verify if GPS and odometer match within threshold')
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`);

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
-- Run this script to add mileage tracking to your system
-- After running, verify:
-- 1. Check tables: SHOW TABLES LIKE 'mileage%';
-- 2. Check columns: DESCRIBE bookings;
-- 3. Check views: SELECT * FROM v_mileage_statistics LIMIT 1;
-- 4. Check triggers: SHOW TRIGGERS;
-- =====================================================
