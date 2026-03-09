<?php
/**
 * Quick Setup Script for Insurance Tables
 * Run this once to create insurance tables and default data
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../include/db.php';

$results = [];
$errors = [];

try {
    // 1. Create insurance_providers table
    $sql = "CREATE TABLE IF NOT EXISTS `insurance_providers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `provider_name` VARCHAR(255) NOT NULL,
        `provider_code` VARCHAR(50) NOT NULL UNIQUE,
        `contact_email` VARCHAR(255),
        `contact_phone` VARCHAR(50),
        `license_number` VARCHAR(100) NOT NULL,
        `status` ENUM('active','inactive','suspended') DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        $results[] = "✓ insurance_providers table created";
    } else {
        $errors[] = "insurance_providers: " . $conn->error;
    }
    
    // 2. Create insurance_coverage_types table
    $sql = "CREATE TABLE IF NOT EXISTS `insurance_coverage_types` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `coverage_name` VARCHAR(100) NOT NULL,
        `coverage_code` VARCHAR(50) NOT NULL UNIQUE,
        `description` TEXT,
        `base_premium_rate` DECIMAL(5,4) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        $results[] = "✓ insurance_coverage_types table created";
    } else {
        $errors[] = "insurance_coverage_types: " . $conn->error;
    }
    
    // 3. Create insurance_policies table
    $sql = "CREATE TABLE IF NOT EXISTS `insurance_policies` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `policy_number` VARCHAR(100) NOT NULL UNIQUE,
        `provider_id` INT(11) NOT NULL,
        `booking_id` INT(11) NOT NULL,
        `vehicle_type` ENUM('car','motorcycle') NOT NULL,
        `vehicle_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `owner_id` INT(11) NOT NULL,
        `coverage_type` ENUM('basic','standard','premium','comprehensive') NOT NULL DEFAULT 'basic',
        `policy_start` DATETIME NOT NULL,
        `policy_end` DATETIME NOT NULL,
        `premium_amount` DECIMAL(10,2) NOT NULL,
        `coverage_limit` DECIMAL(12,2) NOT NULL,
        `deductible` DECIMAL(10,2) DEFAULT 0.00,
        `collision_coverage` DECIMAL(10,2) DEFAULT 0.00,
        `liability_coverage` DECIMAL(10,2) DEFAULT 0.00,
        `theft_coverage` DECIMAL(10,2) DEFAULT 0.00,
        `personal_injury_coverage` DECIMAL(10,2) DEFAULT 0.00,
        `roadside_assistance` TINYINT(1) DEFAULT 0,
        `status` ENUM('active','expired','cancelled','claimed') DEFAULT 'active',
        `terms_accepted` TINYINT(1) DEFAULT 0,
        `issued_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_booking_id` (`booking_id`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        $results[] = "✓ insurance_policies table created";
    } else {
        $errors[] = "insurance_policies: " . $conn->error;
    }
    
    // 4. Insert default provider
    $checkProvider = $conn->query("SELECT COUNT(*) as count FROM insurance_providers");
    $providerCount = $checkProvider->fetch_assoc()['count'];
    
    if ($providerCount == 0) {
        $sql = "INSERT INTO insurance_providers 
            (provider_name, provider_code, contact_email, contact_phone, license_number, status) 
            VALUES 
            ('CargoPH Insurance', 'CARGO_INS', 'insurance@cargoph.online', '+63-917-123-4567', 'IC-2026-CARGO-001', 'active')";
        
        if ($conn->query($sql)) {
            $results[] = "✓ Default insurance provider created";
        } else {
            $errors[] = "Provider insert: " . $conn->error;
        }
    } else {
        $results[] = "ℹ Insurance provider already exists";
    }
    
    // 5. Insert coverage types
    $checkCoverage = $conn->query("SELECT COUNT(*) as count FROM insurance_coverage_types");
    $coverageCount = $checkCoverage->fetch_assoc()['count'];
    
    if ($coverageCount == 0) {
        $sql = "INSERT INTO insurance_coverage_types 
            (coverage_name, coverage_code, description, base_premium_rate, is_active) 
            VALUES 
            ('Basic Coverage', 'BASIC', 'Basic protection with collision and liability coverage', 0.12, 1),
            ('Standard Coverage', 'STANDARD', 'Enhanced coverage with theft protection', 0.18, 1),
            ('Premium Coverage', 'PREMIUM', 'Premium coverage with personal injury protection', 0.25, 1),
            ('Comprehensive Coverage', 'COMPREHENSIVE', 'Full coverage with roadside assistance', 0.35, 1)";
        
        if ($conn->query($sql)) {
            $results[] = "✓ Coverage types created";
        } else {
            $errors[] = "Coverage types: " . $conn->error;
        }
    } else {
        $results[] = "ℹ Coverage types already exist";
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Insurance system setup completed',
        'results' => $results,
        'errors' => $errors
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Setup failed: ' . $e->getMessage(),
        'errors' => $errors
    ], JSON_PRETTY_PRINT);
}

$conn->close();
