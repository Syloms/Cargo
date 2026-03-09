-- ============================================================================
-- Damage Reports Table
-- Owner-submitted damage claims for mediation by admin
-- ============================================================================

CREATE TABLE IF NOT EXISTS `damage_reports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `booking_id` INT(11) NOT NULL,
    `owner_id` INT(11) NOT NULL,
    `renter_id` INT(11) NOT NULL,
    `damage_types` TEXT NOT NULL COMMENT 'JSON array of damage types',
    `description` TEXT NOT NULL,
    `estimated_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `image_1` VARCHAR(500) DEFAULT NULL,
    `image_2` VARCHAR(500) DEFAULT NULL,
    `image_3` VARCHAR(500) DEFAULT NULL,
    `image_4` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('pending','under_review','approved','rejected','resolved') NOT NULL DEFAULT 'pending',
    `admin_notes` TEXT DEFAULT NULL,
    `approved_amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Amount approved for deduction by admin',
    `reviewed_by` INT(11) DEFAULT NULL COMMENT 'Admin user ID who reviewed',
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_booking_id` (`booking_id`),
    KEY `idx_owner_id` (`owner_id`),
    KEY `idx_renter_id` (`renter_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
