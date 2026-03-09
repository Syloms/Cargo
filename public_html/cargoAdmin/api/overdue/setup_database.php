<?php
/**
 * Database Setup Script for Overdue Management System
 * Run this once to add new fields and tables
 */

require_once '../../include/db.php';

header('Content-Type: application/json');

$results = [];
$errors = [];

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Add reminder_count field
    $sql = "ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `reminder_count` INT DEFAULT 0";
    if (mysqli_query($conn, $sql)) {
        $results[] = "✓ Added reminder_count field";
    } else {
        // Check if column already exists
        if (strpos(mysqli_error($conn), 'Duplicate column') === false) {
            throw new Exception("Error adding reminder_count: " . mysqli_error($conn));
        }
        $results[] = "• reminder_count field already exists";
    }
    
    // Add last_reminder_sent field
    $sql = "ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `last_reminder_sent` DATETIME NULL";
    if (mysqli_query($conn, $sql)) {
        $results[] = "✓ Added last_reminder_sent field";
    } else {
        if (strpos(mysqli_error($conn), 'Duplicate column') === false) {
            throw new Exception("Error adding last_reminder_sent: " . mysqli_error($conn));
        }
        $results[] = "• last_reminder_sent field already exists";
    }
    
    // Add late fee confirmation fields
    $fields = [
        'late_fee_confirmed' => 'TINYINT(1) DEFAULT 0',
        'late_fee_confirmed_at' => 'DATETIME NULL',
        'late_fee_confirmed_by' => 'INT NULL',
        'late_fee_waived' => 'TINYINT(1) DEFAULT 0',
        'late_fee_waived_by' => 'INT NULL',
        'late_fee_waived_at' => 'DATETIME NULL',
        'late_fee_waived_reason' => 'TEXT NULL',
        'late_fee_adjusted' => 'TINYINT(1) DEFAULT 0',
        'late_fee_adjusted_by' => 'INT NULL',
        'late_fee_adjusted_at' => 'DATETIME NULL',
        'late_fee_adjustment_reason' => 'TEXT NULL',
        'completed_at' => 'DATETIME NULL'
    ];
    
    foreach ($fields as $fieldName => $fieldDef) {
        $sql = "ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `$fieldName` $fieldDef";
        if (mysqli_query($conn, $sql)) {
            $results[] = "✓ Added $fieldName field";
        } else {
            if (strpos(mysqli_error($conn), 'Duplicate column') === false) {
                throw new Exception("Error adding $fieldName: " . mysqli_error($conn));
            }
            $results[] = "• $fieldName field already exists";
        }
    }
    
    // Create admin_action_logs table
    $sql = "CREATE TABLE IF NOT EXISTS `admin_action_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `admin_id` INT NOT NULL,
        `action_type` VARCHAR(50) NOT NULL,
        `booking_id` INT NULL,
        `notes` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_admin_id` (`admin_id`),
        INDEX `idx_booking_id` (`booking_id`),
        INDEX `idx_action_type` (`action_type`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (mysqli_query($conn, $sql)) {
        $results[] = "✓ Created admin_action_logs table";
    } else {
        if (strpos(mysqli_error($conn), 'already exists') === false) {
            throw new Exception("Error creating admin_action_logs: " . mysqli_error($conn));
        }
        $results[] = "• admin_action_logs table already exists";
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully',
        'results' => $results
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'results' => $results
    ]);
}

mysqli_close($conn);
?>
