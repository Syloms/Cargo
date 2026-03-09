<?php
// ========================================
// 4. DATABASE MIGRATION (Run this once to create table)
// File: create_gps_table.php
// ========================================
require_once '../../include/db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS gps_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        latitude DECIMAL(10, 8) NOT NULL,
        longitude DECIMAL(11, 8) NOT NULL,
        speed DECIMAL(5, 2) DEFAULT 0,
        accuracy DECIMAL(6, 2) DEFAULT 0,
        timestamp DATETIME NOT NULL,
        INDEX idx_booking_id (booking_id),
        INDEX idx_timestamp (timestamp),
        FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    
    echo "GPS locations table created successfully!\n";
    echo "Table structure:\n";
    echo "- id: Auto-incrementing primary key\n";
    echo "- booking_id: Foreign key to bookings table\n";
    echo "- latitude: GPS latitude (decimal)\n";
    echo "- longitude: GPS longitude (decimal)\n";
    echo "- speed: Speed in km/h\n";
    echo "- accuracy: GPS accuracy in meters\n";
    echo "- timestamp: When the location was recorded\n";

} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>