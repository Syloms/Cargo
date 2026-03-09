-- ============================================================
-- INSURANCE SYSTEM MIGRATION - Legal Requirement Integration
-- ============================================================
-- Created: 2026-02-01
-- Purpose: Integrate comprehensive insurance system for vehicle rentals
-- ============================================================

-- 1. Insurance Providers Table
CREATE TABLE IF NOT EXISTS `insurance_providers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_name` VARCHAR(255) NOT NULL,
  `provider_code` VARCHAR(50) NOT NULL UNIQUE,
  `contact_email` VARCHAR(255),
  `contact_phone` VARCHAR(50),
  `license_number` VARCHAR(100) NOT NULL COMMENT 'Insurance Commission License',
  `api_endpoint` VARCHAR(500) COMMENT 'API endpoint for integration',
  `api_key` VARCHAR(255) COMMENT 'Encrypted API key',
  `status` ENUM('active','inactive','suspended') DEFAULT 'active',
  `coverage_types` TEXT COMMENT 'JSON array of coverage types offered',
  `base_rate` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Base rate percentage',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Insurance Policies Table
CREATE TABLE IF NOT EXISTS `insurance_policies` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `policy_number` VARCHAR(100) NOT NULL UNIQUE,
  `provider_id` INT(11) NOT NULL,
  `booking_id` INT(11) NOT NULL,
  `vehicle_type` ENUM('car','motorcycle') NOT NULL,
  `vehicle_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL COMMENT 'Renter',
  `owner_id` INT(11) NOT NULL,
  
  -- Policy Details
  `coverage_type` ENUM('basic','standard','premium','comprehensive') NOT NULL DEFAULT 'basic',
  `policy_start` DATETIME NOT NULL,
  `policy_end` DATETIME NOT NULL,
  `premium_amount` DECIMAL(10,2) NOT NULL,
  `coverage_limit` DECIMAL(12,2) NOT NULL COMMENT 'Maximum coverage amount',
  `deductible` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Amount renter pays before insurance kicks in',
  
  -- Coverage Details (JSON)
  `collision_coverage` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Collision damage coverage',
  `liability_coverage` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Third-party liability',
  `theft_coverage` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Theft protection',
  `personal_injury_coverage` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Personal injury protection',
  `roadside_assistance` TINYINT(1) DEFAULT 0,
  
  -- Status
  `status` ENUM('active','expired','cancelled','claimed') DEFAULT 'active',
  `policy_document` VARCHAR(500) COMMENT 'Path to policy PDF',
  `terms_accepted` TINYINT(1) DEFAULT 0,
  `terms_accepted_at` DATETIME,
  
  -- Metadata
  `issued_at` DATETIME NOT NULL,
  `cancelled_at` DATETIME,
  `cancellation_reason` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_policy_number` (`policy_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_insurance_policy_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insurance_policy_provider` FOREIGN KEY (`provider_id`) REFERENCES `insurance_providers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Insurance Claims Table
CREATE TABLE IF NOT EXISTS `insurance_claims` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `claim_number` VARCHAR(100) NOT NULL UNIQUE,
  `policy_id` INT(11) NOT NULL,
  `booking_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  
  -- Claim Details
  `claim_type` ENUM('collision','theft','liability','personal_injury','property_damage','other') NOT NULL,
  `incident_date` DATETIME NOT NULL,
  `incident_location` VARCHAR(500),
  `incident_description` TEXT NOT NULL,
  `police_report_number` VARCHAR(100),
  `police_report_file` VARCHAR(500),
  
  -- Financial
  `claimed_amount` DECIMAL(10,2) NOT NULL,
  `approved_amount` DECIMAL(10,2) DEFAULT 0.00,
  `deductible_paid` DECIMAL(10,2) DEFAULT 0.00,
  `payout_amount` DECIMAL(10,2) DEFAULT 0.00,
  
  -- Evidence
  `evidence_photos` TEXT COMMENT 'JSON array of photo paths',
  `witness_statements` TEXT COMMENT 'JSON array of witness info',
  `damage_assessment` TEXT COMMENT 'JSON assessment details',
  
  -- Status
  `status` ENUM('submitted','under_review','approved','rejected','paid','closed') DEFAULT 'submitted',
  `priority` ENUM('low','normal','high','urgent') DEFAULT 'normal',
  
  -- Processing
  `reviewed_by` INT(11) COMMENT 'Admin/adjuster ID',
  `reviewed_at` DATETIME,
  `review_notes` TEXT,
  `rejection_reason` TEXT,
  `paid_at` DATETIME,
  `payout_reference` VARCHAR(100),
  
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_policy_id` (`policy_id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_claim_number` (`claim_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_insurance_claim_policy` FOREIGN KEY (`policy_id`) REFERENCES `insurance_policies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insurance_claim_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Insurance Coverage Types Table
CREATE TABLE IF NOT EXISTS `insurance_coverage_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `coverage_name` VARCHAR(100) NOT NULL,
  `coverage_code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `base_premium_rate` DECIMAL(5,4) NOT NULL COMMENT 'Rate as decimal (e.g., 0.12 = 12%)',
  `min_coverage_amount` DECIMAL(10,2) DEFAULT 0.00,
  `max_coverage_amount` DECIMAL(12,2) DEFAULT 0.00,
  `is_mandatory` TINYINT(1) DEFAULT 0 COMMENT 'Required by law',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Booking Insurance Link (Update bookings table)
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `insurance_required` TINYINT(1) DEFAULT 1 COMMENT 'Insurance is mandatory',
ADD COLUMN IF NOT EXISTS `insurance_policy_id` INT(11) DEFAULT NULL COMMENT 'Link to insurance policy',
ADD COLUMN IF NOT EXISTS `insurance_premium` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Insurance premium paid',
ADD COLUMN IF NOT EXISTS `insurance_coverage_type` VARCHAR(50) DEFAULT 'basic' COMMENT 'Type of coverage selected',
ADD COLUMN IF NOT EXISTS `insurance_verified` TINYINT(1) DEFAULT 0 COMMENT 'Policy verified and active',
ADD INDEX IF NOT EXISTS `idx_insurance_policy` (`insurance_policy_id`);

-- 6. Add foreign key constraint for insurance policy
-- ALTER TABLE `bookings` 
-- ADD CONSTRAINT `fk_booking_insurance_policy` 
-- FOREIGN KEY (`insurance_policy_id`) REFERENCES `insurance_policies` (`id`) ON DELETE SET NULL;
-- Note: Commented out to avoid issues if insurance_policies doesn't exist yet

-- 7. Insurance Audit Log
CREATE TABLE IF NOT EXISTS `insurance_audit_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `policy_id` INT(11),
  `claim_id` INT(11),
  `action_type` VARCHAR(50) NOT NULL,
  `action_by` INT(11) COMMENT 'User/Admin ID',
  `action_details` TEXT COMMENT 'JSON details',
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_policy_id` (`policy_id`),
  KEY `idx_claim_id` (`claim_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- INSERT DEFAULT DATA
-- ============================================================

-- Default Insurance Provider (System Default)
INSERT INTO `insurance_providers` 
(`provider_name`, `provider_code`, `contact_email`, `contact_phone`, `license_number`, `status`, `base_rate`) 
VALUES 
('Cargo Platform Insurance', 'CARGO_INS', 'insurance@cargo.ph', '+63-XXX-XXXX', 'IC-2025-XXXXX', 'active', 12.00)
ON DUPLICATE KEY UPDATE `provider_name` = VALUES(`provider_name`);

-- Insert Coverage Types
INSERT INTO `insurance_coverage_types` 
(`coverage_name`, `coverage_code`, `description`, `base_premium_rate`, `min_coverage_amount`, `max_coverage_amount`, `is_mandatory`, `is_active`) 
VALUES 
('Basic Coverage', 'BASIC', 'Covers third-party liability and basic collision damage up to ₱100,000', 0.12, 50000.00, 100000.00, 1, 1),
('Standard Coverage', 'STANDARD', 'Includes comprehensive collision, theft protection up to ₱300,000', 0.18, 100000.00, 300000.00, 0, 1),
('Premium Coverage', 'PREMIUM', 'Full coverage including personal injury protection up to ₱500,000', 0.25, 300000.00, 500000.00, 0, 1),
('Comprehensive Coverage', 'COMPREHENSIVE', 'Maximum protection including roadside assistance up to ₱1,000,000', 0.35, 500000.00, 1000000.00, 0, 1)
ON DUPLICATE KEY UPDATE `coverage_name` = VALUES(`coverage_name`);

-- ============================================================
-- VIEWS FOR REPORTING
-- ============================================================

-- Active Policies View
CREATE OR REPLACE VIEW `v_active_insurance_policies` AS
SELECT 
  ip.id,
  ip.policy_number,
  ip.booking_id,
  b.user_id,
  b.owner_id,
  u.fullname AS renter_name,
  ip.coverage_type,
  ip.premium_amount,
  ip.coverage_limit,
  ip.policy_start,
  ip.policy_end,
  ip.status,
  prov.provider_name,
  DATEDIFF(ip.policy_end, NOW()) AS days_remaining
FROM insurance_policies ip
JOIN bookings b ON ip.booking_id = b.id
JOIN users u ON b.user_id = u.id
JOIN insurance_providers prov ON ip.provider_id = prov.id
WHERE ip.status = 'active' 
  AND ip.policy_end > NOW();

-- Claims Summary View
CREATE OR REPLACE VIEW `v_insurance_claims_summary` AS
SELECT 
  ic.id,
  ic.claim_number,
  ic.claim_type,
  ic.status,
  ic.claimed_amount,
  ic.approved_amount,
  b.id AS booking_id,
  u.fullname AS claimant_name,
  ip.policy_number,
  ic.incident_date,
  ic.created_at AS claim_date
FROM insurance_claims ic
JOIN insurance_policies ip ON ic.policy_id = ip.id
JOIN bookings b ON ic.booking_id = b.id
JOIN users u ON ic.user_id = u.id;

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

CREATE INDEX IF NOT EXISTS `idx_policy_dates` ON `insurance_policies` (`policy_start`, `policy_end`);
CREATE INDEX IF NOT EXISTS `idx_claim_status` ON `insurance_claims` (`status`, `priority`);
CREATE INDEX IF NOT EXISTS `idx_policy_status` ON `insurance_policies` (`status`);

-- ============================================================
-- MIGRATION COMPLETE
-- ============================================================
