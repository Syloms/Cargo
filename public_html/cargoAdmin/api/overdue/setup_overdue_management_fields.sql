-- ============================================================================
-- OVERDUE MANAGEMENT SYSTEM - DATABASE SETUP
-- Add new fields to support enhanced overdue management features
-- ============================================================================

-- Add fields to bookings table for late fee management
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `reminder_count` INT DEFAULT 0 COMMENT 'Number of reminders sent',
ADD COLUMN IF NOT EXISTS `last_reminder_sent` DATETIME NULL COMMENT 'Last reminder timestamp',
ADD COLUMN IF NOT EXISTS `late_fee_confirmed` TINYINT(1) DEFAULT 0 COMMENT 'Admin confirmed the late fee',
ADD COLUMN IF NOT EXISTS `late_fee_confirmed_at` DATETIME NULL COMMENT 'When late fee was confirmed',
ADD COLUMN IF NOT EXISTS `late_fee_confirmed_by` INT NULL COMMENT 'Admin ID who confirmed',
ADD COLUMN IF NOT EXISTS `late_fee_waived` TINYINT(1) DEFAULT 0 COMMENT 'Late fee was waived',
ADD COLUMN IF NOT EXISTS `late_fee_waived_by` INT NULL COMMENT 'Admin ID who waived',
ADD COLUMN IF NOT EXISTS `late_fee_waived_at` DATETIME NULL COMMENT 'When late fee was waived',
ADD COLUMN IF NOT EXISTS `late_fee_waived_reason` TEXT NULL COMMENT 'Reason for waiving',
ADD COLUMN IF NOT EXISTS `late_fee_adjusted` TINYINT(1) DEFAULT 0 COMMENT 'Late fee was manually adjusted',
ADD COLUMN IF NOT EXISTS `late_fee_adjusted_by` INT NULL COMMENT 'Admin ID who adjusted',
ADD COLUMN IF NOT EXISTS `late_fee_adjusted_at` DATETIME NULL COMMENT 'When late fee was adjusted',
ADD COLUMN IF NOT EXISTS `late_fee_adjustment_reason` TEXT NULL COMMENT 'Reason for adjustment',
ADD COLUMN IF NOT EXISTS `completed_at` DATETIME NULL COMMENT 'When booking was completed';

-- Create admin action logs table
CREATE TABLE IF NOT EXISTS `admin_action_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL COMMENT 'Admin who performed action',
  `action_type` VARCHAR(50) NOT NULL COMMENT 'Type of action performed',
  `booking_id` INT NULL COMMENT 'Related booking ID',
  `notes` TEXT NULL COMMENT 'Additional notes about the action',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_admin_id` (`admin_id`),
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_action_type` (`action_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Audit log for admin actions';

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_bookings_late_fee_confirmed` ON `bookings` (`late_fee_confirmed`);
CREATE INDEX IF NOT EXISTS `idx_bookings_late_fee_waived` ON `bookings` (`late_fee_waived`);
CREATE INDEX IF NOT EXISTS `idx_bookings_reminder_count` ON `bookings` (`reminder_count`);
CREATE INDEX IF NOT EXISTS `idx_bookings_last_reminder_sent` ON `bookings` (`last_reminder_sent`);

-- ============================================================================
-- ACTION TYPE REFERENCE
-- ============================================================================
-- force_complete_overdue: Admin manually completed an overdue booking
-- confirm_late_fee: Admin confirmed the calculated late fee
-- send_reminder: Admin sent a reminder notification to renter
-- adjust_late_fee: Admin manually adjusted the late fee amount
-- waive_late_fee: Admin waived the late fee completely
-- ============================================================================
