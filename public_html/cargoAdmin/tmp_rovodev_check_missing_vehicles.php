<?php
/**
 * Debug script to check for bookings with missing vehicle data
 */

include 'include/db.php';

echo "<h2>Checking for bookings with missing vehicle data...</h2>";

// Check if motorcycle_id column exists
$columnsResult = $conn->query("SHOW COLUMNS FROM bookings LIKE 'motorcycle_id'");
$hasMotorcycleColumn = $columnsResult && $columnsResult->num_rows > 0;

echo "<p>Motorcycle column exists: " . ($hasMotorcycleColumn ? "YES" : "NO") . "</p>";

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
    echo "<h3>Recent 20 Bookings:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr>
            <th>Booking ID</th>
            <th>Car ID</th>";
    if ($hasMotorcycleColumn) {
        echo "<th>Motorcycle ID</th>";
    }
    echo "<th>Vehicle Type</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Full Name</th>
            <th>Renter</th>
            <th>Status</th>
            <th>Created</th>
          </tr>";
    
    $missingCount = 0;
    while ($row = $result->fetch_assoc()) {
        $brand = $row['brand'] ?? '';
        $model = $row['model'] ?? '';
        $vehicleName = trim($brand . ' ' . $model);
        $isMissing = empty($vehicleName);
        
        if ($isMissing) {
            $missingCount++;
        }
        
        $rowStyle = $isMissing ? "background-color: #ffcccc;" : "";
        
        echo "<tr style='$rowStyle'>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['car_id'] ?? 'NULL') . "</td>";
        if ($hasMotorcycleColumn) {
            echo "<td>" . ($row['motorcycle_id'] ?? 'NULL') . "</td>";
        }
        echo "<td>" . $row['vehicle_type'] . "</td>";
        echo "<td>" . ($brand ?: '<i>NULL</i>') . "</td>";
        echo "<td>" . ($model ?: '<i>NULL</i>') . "</td>";
        echo "<td><strong>" . ($vehicleName ?: '<span style="color:red;">MISSING</span>') . "</strong></td>";
        echo "<td>" . ($row['renter_name'] ?? 'Unknown') . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . date('M d, Y', strtotime($row['created_at'])) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "<p><strong>Total bookings with missing vehicle data: $missingCount</strong></p>";
    
    if ($missingCount > 0) {
        echo "<h3>Possible Causes:</h3>";
        echo "<ul>";
        echo "<li>Vehicle was deleted from cars/motorcycles table</li>";
        echo "<li>Invalid car_id or motorcycle_id in bookings table</li>";
        echo "<li>Data integrity issue</li>";
        echo "</ul>";
        
        // Check for orphaned bookings
        echo "<h3>Orphaned Bookings Check:</h3>";
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
                LIMIT 10
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
                LIMIT 10
            ";
        }
        
        $orphanedResult = $conn->query($orphanedQuery);
        if ($orphanedResult && $orphanedResult->num_rows > 0) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Booking ID</th><th>Car ID</th>";
            if ($hasMotorcycleColumn) {
                echo "<th>Motorcycle ID</th>";
            }
            echo "<th>Created</th></tr>";
            
            while ($orphan = $orphanedResult->fetch_assoc()) {
                echo "<tr style='background-color: #ff9999;'>";
                echo "<td>" . $orphan['id'] . "</td>";
                echo "<td>" . ($orphan['car_id'] ?? 'NULL') . "</td>";
                if ($hasMotorcycleColumn) {
                    echo "<td>" . ($orphan['motorcycle_id'] ?? 'NULL') . "</td>";
                }
                echo "<td>" . date('M d, Y', strtotime($orphan['created_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<p style='color: red;'><strong>Found " . $orphanedResult->num_rows . " orphaned bookings (references to deleted vehicles)</strong></p>";
        } else {
            echo "<p style='color: green;'>No orphaned bookings found.</p>";
        }
    }
    
} else {
    echo "<p style='color: red;'>Query failed: " . $conn->error . "</p>";
}

$conn->close();
?>
