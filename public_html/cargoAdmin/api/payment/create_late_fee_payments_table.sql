-- Create late_fee_payments table to track late fee payments separately from regular rental payments
CREATE TABLE IF NOT EXISTS `late_fee_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `late_fee_amount` decimal(10,2) NOT NULL,
  `rental_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Rental amount if not yet paid',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'gcash',
  `payment_reference` varchar(100) DEFAULT NULL,
  `gcash_number` varchar(20) DEFAULT NULL,
  `payment_status` enum('pending','verified','paid','rejected','failed') DEFAULT 'pending',
  `verification_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `is_rental_paid` tinyint(1) DEFAULT 0 COMMENT '1 if rental already paid, 0 if paying rental + late fee',
  `hours_overdue` int(11) DEFAULT NULL,
  `days_overdue` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `user_id` (`user_id`),
  KEY `payment_status` (`payment_status`),
  CONSTRAINT `fk_late_fee_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_late_fee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add late_fee_payment_status column to bookings table if it doesn't exist
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `late_fee_payment_status` enum('none','pending','paid','verified') DEFAULT 'none' 
COMMENT 'Status of late fee payment: none=no late fee, pending=submitted, paid=verified' 
AFTER `late_fee_charged`;

-- Add index for better query performance
ALTER TABLE `bookings` ADD INDEX IF NOT EXISTS `idx_late_fee_payment_status` (`late_fee_payment_status`);
