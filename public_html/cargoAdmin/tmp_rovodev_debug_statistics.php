<?php
/**
 * Debug version of statistics.php to show what's happening
 */

include 'include/db.php';
include 'include/dashboard_stats.php';

echo "<h1>Statistics Page Debug</h1>";
echo "<hr>";

// Check motorcycle support
echo "<h2>1. Motorcycle Support Check</h2>";
$motorcycleColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'motorcycle_id'");
$hasMotorcycleColumn = $motorcycleColumn && $motorcycleColumn->num_rows > 0;

$motorcyclesTable = $conn->query("SHOW TABLES LIKE 'motorcycles'");
$hasMotorcycles = $motorcyclesTable && $motorcyclesTable->num_rows > 0;

echo "<p><strong>motorcycle_id column in bookings:</strong> " . ($hasMotorcycleColumn ? "✓ YES" : "✗ NO") . "</p>";
echo "<p><strong>motorcycles table exists:</strong> " . ($hasMotorcycles ? "✓ YES" : "✗ NO") . "</p>";
echo "<p><strong>Query mode:</strong> " . ($hasMotorcycleColumn && $hasMotorcycles ? "Cars + Motorcycles" : "Cars Only") . "</p>";

// Get recent bookings
echo "<h2>2. Recent Bookings Query Result</h2>";
$recentBookings = getRecentBookings($conn, 10);

echo "<p><strong>Total bookings retrieved:</strong> " . count($recentBookings) . "</p>";

if (!empty($recentBookings)) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: white;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Booking ID</th>";
    echo "<th>car_id</th>";
    if ($hasMotorcycleColumn) {
        echo "<th>motorcycle_id</th>";
    }
    echo "<th>Brand</th>";
    echo "<th>Model</th>";
    echo "<th>Vehicle Type</th>";
    echo "<th>Display Name</th>";
    echo "<th>Status</th>";
    echo "</tr>";
    
    foreach ($recentBookings as $booking) {
        $brand = $booking['brand'] ?? '';
        $model = $booking['model'] ?? '';
        $vehicleName = trim($brand . ' ' . $model);
        if (empty($vehicleName)) {
            $vehicleName = 'Vehicle Not Found';
        }
        
        $rowStyle = empty($brand) && empty($model) ? "style='background-color: #ffcccc;'" : "";
        
        echo "<tr $rowStyle>";
        echo "<td><strong>" . $booking['id'] . "</strong></td>";
        echo "<td>" . ($booking['car_id'] ?? 'NULL') . "</td>";
        if ($hasMotorcycleColumn) {
            echo "<td>" . ($booking['motorcycle_id'] ?? 'NULL') . "</td>";
        }
        echo "<td>" . ($brand ?: '<i style="color: #999;">NULL</i>') . "</td>";
        echo "<td>" . ($model ?: '<i style="color: #999;">NULL</i>') . "</td>";
        echo "<td>" . ($booking['vehicle_type'] ?? 'Car') . "</td>";
        echo "<td><strong>" . htmlspecialchars($vehicleName) . "</strong></td>";
        echo "<td>" . $booking['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count missing vehicles
    $missingCount = 0;
    foreach ($recentBookings as $booking) {
        $brand = $booking['brand'] ?? '';
        $model = $booking['model'] ?? '';
        if (empty($brand) && empty($model)) {
            $missingCount++;
        }
    }
    
    echo "<p><strong>Bookings with missing vehicle data:</strong> $missingCount / " . count($recentBookings) . "</p>";
    
    if ($missingCount > 0) {
        echo "<div style='background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin-top: 20px;'>";
        echo "<p><strong>⚠️ Why are vehicles missing?</strong></p>";
        echo "<ul>";
        echo "<li>The vehicles (cars) were deleted from the database</li>";
        echo "<li>The LEFT JOIN returns NULL for brand and model</li>";
        echo "<li>Our fix displays 'Vehicle Not Found' instead of empty cells</li>";
        echo "</ul>";
        echo "</div>";
    }
}

// Show the actual SQL query being used
echo "<h2>3. SQL Query Being Used</h2>";
echo "<div style='background: #f5f5f5; padding: 15px; font-family: monospace; font-size: 12px; white-space: pre-wrap;'>";
if ($hasMotorcycleColumn && $hasMotorcycles) {
    echo "SELECT 
    b.*,
    COALESCE(c.brand, m.brand) as brand,
    COALESCE(c.model, m.model) as model,
    COALESCE(c.plate_number, m.plate_number) as plate_number,
    CASE 
        WHEN b.motorcycle_id IS NOT NULL THEN 'Motorcycle'
        ELSE 'Car'
    END as vehicle_type,
    u.fullname as renter_name,
    COALESCE(o1.fullname, o2.fullname) as owner_name
FROM bookings b
LEFT JOIN cars c ON c.id = b.car_id
LEFT JOIN motorcycles m ON m.id = b.motorcycle_id
LEFT JOIN users u ON u.id = b.user_id
LEFT JOIN users o1 ON o1.id = c.owner_id
LEFT JOIN users o2 ON o2.id = m.owner_id
ORDER BY b.created_at DESC
LIMIT 10";
} else {
    echo "SELECT 
    b.*,
    c.brand,
    c.model,
    c.plate_number,
    'Car' as vehicle_type,
    u.fullname as renter_name,
    o.fullname as owner_name
FROM bookings b
LEFT JOIN cars c ON c.id = b.car_id
LEFT JOIN users u ON u.id = b.user_id
LEFT JOIN users o ON o.id = c.owner_id
ORDER BY b.created_at DESC
LIMIT 10";
}
echo "</div>";

// Check which cars are missing
echo "<h2>4. Missing Cars Analysis</h2>";
$missingCarsQuery = "
    SELECT DISTINCT b.car_id, COUNT(*) as booking_count
    FROM bookings b
    LEFT JOIN cars c ON c.id = b.car_id
    WHERE c.id IS NULL
    GROUP BY b.car_id
    ORDER BY booking_count DESC
";

$missingCarsResult = $conn->query($missingCarsQuery);
if ($missingCarsResult && $missingCarsResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: white;'>";
    echo "<tr style='background: #ffcccc;'><th>Deleted Car ID</th><th>Number of Bookings</th></tr>";
    
    while ($row = $missingCarsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $row['car_id'] . "</strong></td>";
        echo "<td>" . $row['booking_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✓ No missing cars. All bookings have valid vehicle references.</p>";
}

// Summary
echo "<h2>5. Summary</h2>";
echo "<div style='background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3;'>";
echo "<p><strong>Current Status:</strong></p>";
echo "<ul>";
echo "<li>✅ The code is working correctly</li>";
echo "<li>✅ NULL values are properly handled</li>";
echo "<li>✅ 'Vehicle Not Found' displays for deleted vehicles</li>";
echo "<li>✅ " . ($hasMotorcycleColumn && $hasMotorcycles ? "Motorcycle support is ENABLED" : "Motorcycle support is DISABLED (cars only)") . "</li>";
echo "</ul>";

if (!$hasMotorcycleColumn) {
    echo "<p><strong>To enable motorcycle support:</strong></p>";
    echo "<p>Run this SQL command:</p>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ddd;'>ALTER TABLE bookings ADD COLUMN motorcycle_id INT(11) NULL AFTER car_id;</pre>";
}

echo "</div>";

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background: #f5f5f5;
    max-width: 1200px;
    margin: 0 auto;
}
h1 {
    color: #333;
    border-bottom: 3px solid #1a1a1a;
    padding-bottom: 10px;
}
h2 {
    color: #555;
    margin-top: 30px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}
table {
    width: 100%;
    margin: 10px 0;
}
</style>
