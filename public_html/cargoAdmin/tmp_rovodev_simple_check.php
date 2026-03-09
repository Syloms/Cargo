<?php
/**
 * Simple CLI script to check for bookings with missing vehicle data
 */

// Database configuration (direct connection for CLI)
$host = "localhost";
$user = "root";
$pass = "";
$db = "u672913452_dbcargo";

// MySQLi Connection
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully to database: $db\n\n";

// Check if motorcycle_id column exists
$columnsResult = $conn->query("SHOW COLUMNS FROM bookings LIKE 'motorcycle_id'");
$hasMotorcycleColumn = $columnsResult && $columnsResult->num_rows > 0;

echo "Motorcycle column exists: " . ($hasMotorcycleColumn ? "YES" : "NO") . "\n\n";

// Query to find bookings with missing vehicle data
if ($hasMotorcycleColumn) {
    $query = "
        SELECT 
            b.id,
            b.car_id,
            b.motorcycle_id,
            b.status,
            b.created_at,
            COALESCE(c.brand, m.brand) as brand,
            COALESCE(c.model, m.model) as model,
            CASE 
                WHEN b.motorcycle_id IS NOT NULL THEN 'Motorcycle'
                ELSE 'Car'
            END as vehicle_type,
            u.fullname as renter_name
        FROM bookings b
        LEFT JOIN cars c ON c.id = b.car_id
        LEFT JOIN motorcycles m ON m.id = b.motorcycle_id
        LEFT JOIN users u ON u.id = b.user_id
        ORDER BY b.created_at DESC
        LIMIT 20
    ";
} else {
    $query = "
        SELECT 
            b.id,
            b.car_id,
            b.status,
            b.created_at,
            c.brand,
            c.model,
            'Car' as vehicle_type,
            u.fullname as renter_name
        FROM bookings b
        LEFT JOIN cars c ON c.id = b.car_id
        LEFT JOIN users u ON u.id = b.user_id
        ORDER BY b.created_at DESC
        LIMIT 20
    ";
}

$result = $conn->query($query);

if ($result) {
    echo "Recent 20 Bookings:\n";
    echo str_repeat("=", 120) . "\n";
    printf("%-10s %-8s %-12s %-15s %-15s %-30s %-20s %-12s\n", 
           "BookingID", "CarID", "MotorcycleID", "Type", "Brand", "Model", "Renter", "Status");
    echo str_repeat("=", 120) . "\n";
    
    $missingCount = 0;
    while ($row = $result->fetch_assoc()) {
        $brand = $row['brand'] ?? '';
        $model = $row['model'] ?? '';
        $vehicleName = trim($brand . ' ' . $model);
        $isMissing = empty($vehicleName);
        
        if ($isMissing) {
            $missingCount++;
        }
        
        $marker = $isMissing ? ">>> MISSING >>>" : "";
        
        printf("%-10s %-8s %-12s %-15s %-15s %-30s %-20s %-12s %s\n", 
               $row['id'],
               $row['car_id'] ?? 'NULL',
               ($hasMotorcycleColumn ? ($row['motorcycle_id'] ?? 'NULL') : 'N/A'),
               $row['vehicle_type'],
               $brand ?: 'NULL',
               $model ?: 'NULL',
               substr($row['renter_name'] ?? 'Unknown', 0, 20),
               $row['status'],
               $marker
        );
    }
    
    echo str_repeat("=", 120) . "\n";
    echo "\nTotal bookings checked: " . $result->num_rows . "\n";
    echo "Bookings with missing vehicle data: $missingCount\n\n";
    
    if ($missingCount > 0) {
        echo "Checking for orphaned bookings (references to deleted vehicles)...\n";
        
        if ($hasMotorcycleColumn) {
            $orphanedQuery = "
                SELECT 
                    b.id,
                    b.car_id,
                    b.motorcycle_id,
                    b.created_at
                FROM bookings b
                LEFT JOIN cars c ON c.id = b.car_id
                LEFT JOIN motorcycles m ON m.id = b.motorcycle_id
                WHERE (b.car_id IS NOT NULL AND c.id IS NULL)
                   OR (b.motorcycle_id IS NOT NULL AND m.id IS NULL)
                ORDER BY b.created_at DESC
            ";
        } else {
            $orphanedQuery = "
                SELECT 
                    b.id,
                    b.car_id,
                    b.created_at
                FROM bookings b
                LEFT JOIN cars c ON c.id = b.car_id
                WHERE b.car_id IS NOT NULL AND c.id IS NULL
                ORDER BY b.created_at DESC
            ";
        }
        
        $orphanedResult = $conn->query($orphanedQuery);
        if ($orphanedResult && $orphanedResult->num_rows > 0) {
            echo "\nOrphaned Bookings Found:\n";
            echo str_repeat("-", 80) . "\n";
            
            while ($orphan = $orphanedResult->fetch_assoc()) {
                printf("Booking ID: %s | Car ID: %s | Motorcycle ID: %s | Created: %s\n", 
                       $orphan['id'],
                       $orphan['car_id'] ?? 'NULL',
                       ($hasMotorcycleColumn ? ($orphan['motorcycle_id'] ?? 'NULL') : 'N/A'),
                       date('M d, Y', strtotime($orphan['created_at']))
                );
            }
            
            echo str_repeat("-", 80) . "\n";
            echo "Total orphaned bookings: " . $orphanedResult->num_rows . "\n";
        } else {
            echo "No orphaned bookings found.\n";
        }
    }
    
} else {
    echo "Query failed: " . $conn->error . "\n";
}

$conn->close();
?>
