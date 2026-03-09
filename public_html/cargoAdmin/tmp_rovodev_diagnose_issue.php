<?php
/**
 * Diagnose why all vehicles show "Vehicle Not Found"
 */

include 'include/db.php';
include 'include/dashboard_stats.php';

echo "<h1>Vehicle Display Diagnostic</h1>";
echo "<hr>";

// Test 1: Get recent bookings directly
echo "<h2>Test 1: Recent Bookings Data</h2>";
$recentBookings = getRecentBookings($conn, 10);

echo "<p><strong>Bookings retrieved:</strong> " . count($recentBookings) . "</p>";

if (!empty($recentBookings)) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%; background: white;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>car_id</th><th>brand (raw)</th><th>model (raw)</th><th>vehicle_type</th><th>renter_name</th></tr>";
    
    foreach ($recentBookings as $booking) {
        echo "<tr>";
        echo "<td>" . $booking['id'] . "</td>";
        echo "<td>" . ($booking['car_id'] ?? 'NULL') . "</td>";
        
        // Show RAW values
        echo "<td>";
        if (isset($booking['brand'])) {
            echo "'" . $booking['brand'] . "' (type: " . gettype($booking['brand']) . ")";
        } else {
            echo "<span style='color: red;'>NOT SET</span>";
        }
        echo "</td>";
        
        echo "<td>";
        if (isset($booking['model'])) {
            echo "'" . $booking['model'] . "' (type: " . gettype($booking['model']) . ")";
        } else {
            echo "<span style='color: red;'>NOT SET</span>";
        }
        echo "</td>";
        
        echo "<td>" . ($booking['vehicle_type'] ?? 'NULL') . "</td>";
        echo "<td>" . ($booking['renter_name'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 2: Check the actual SQL query result
echo "<h2>Test 2: Raw SQL Query Test</h2>";

$testQuery = "
    SELECT 
        b.id,
        b.car_id,
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
    LIMIT 5
";

echo "<pre style='background: #f5f5f5; padding: 10px; font-size: 11px;'>$testQuery</pre>";

$result = $conn->query($testQuery);

if ($result) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%; background: white;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>booking_id</th><th>car_id</th><th>brand</th><th>model</th><th>renter</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $hasData = !empty($row['brand']) || !empty($row['model']);
        $rowStyle = $hasData ? "" : "style='background: #ffcccc;'";
        
        echo "<tr $rowStyle>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['car_id'] . "</td>";
        echo "<td>" . ($row['brand'] ?? '<i style="color: red;">NULL</i>') . "</td>";
        echo "<td>" . ($row['model'] ?? '<i style="color: red;">NULL</i>') . "</td>";
        echo "<td>" . ($row['renter_name'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Query failed: " . $conn->error . "</p>";
}

// Test 3: Check if cars exist
echo "<h2>Test 3: Cars Table Check</h2>";
$carsQuery = "SELECT id, brand, model, status FROM cars ORDER BY id DESC LIMIT 10";
$carsResult = $conn->query($carsQuery);

if ($carsResult) {
    echo "<p><strong>Total cars in database:</strong> ";
    $countResult = $conn->query("SELECT COUNT(*) as total FROM cars");
    echo $countResult->fetch_assoc()['total'] . "</p>";
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: white;'>";
    echo "<tr style='background: #f0f0f0;'><th>Car ID</th><th>Brand</th><th>Model</th><th>Status</th></tr>";
    
    while ($car = $carsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $car['id'] . "</td>";
        echo "<td>" . ($car['brand'] ?? 'NULL') . "</td>";
        echo "<td>" . ($car['model'] ?? 'NULL') . "</td>";
        echo "<td>" . $car['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 4: Test the processing logic
echo "<h2>Test 4: Processing Logic Test</h2>";
echo "<p>Simulating the statistics.php processing:</p>";

if (!empty($recentBookings)) {
    $firstBooking = $recentBookings[0];
    
    echo "<div style='background: #f5f5f5; padding: 15px; font-family: monospace; font-size: 12px;'>";
    echo "First booking data:<br>";
    echo "- booking['brand']: "; var_dump($firstBooking['brand'] ?? 'NOT SET'); echo "<br>";
    echo "- booking['model']: "; var_dump($firstBooking['model'] ?? 'NOT SET'); echo "<br>";
    echo "<br>";
    
    echo "Processing steps:<br>";
    $brand = $firstBooking['brand'] ?? '';
    $model = $firstBooking['model'] ?? '';
    echo "1. \$brand = \$booking['brand'] ?? '' → "; var_dump($brand); echo "<br>";
    echo "2. \$model = \$booking['model'] ?? '' → "; var_dump($model); echo "<br>";
    
    $vehicleName = trim($brand . ' ' . $model);
    echo "3. \$vehicleName = trim(\$brand . ' ' . \$model) → "; var_dump($vehicleName); echo "<br>";
    
    if (empty($vehicleName)) {
        echo "4. empty(\$vehicleName) → <strong style='color: red;'>TRUE</strong> (This is why it shows 'Vehicle Not Found')<br>";
        echo "5. Result: 'Vehicle Not Found'<br>";
    } else {
        echo "4. empty(\$vehicleName) → <strong style='color: green;'>FALSE</strong><br>";
        echo "5. Result: " . htmlspecialchars($vehicleName) . "<br>";
    }
    echo "</div>";
}

// Test 5: Check getRecentBookings function
echo "<h2>Test 5: Function Source Check</h2>";
$dashboardStatsFile = __DIR__ . '/include/dashboard_stats.php';
if (file_exists($dashboardStatsFile)) {
    $content = file_get_contents($dashboardStatsFile);
    
    // Find the getRecentBookings function
    $start = strpos($content, 'function getRecentBookings');
    if ($start !== false) {
        $end = strpos($content, '}', strpos($content, 'return $bookings;', $start));
        $functionCode = substr($content, $start, $end - $start + 1);
        
        echo "<p><strong>getRecentBookings() function is using:</strong></p>";
        
        if (strpos($functionCode, 'motorcycle_id') !== false) {
            echo "<p style='color: blue;'>✓ Cars + Motorcycles query (with COALESCE)</p>";
        } else {
            echo "<p style='color: green;'>✓ Cars only query</p>";
        }
    }
}

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background: #f5f5f5;
    max-width: 1400px;
    margin: 0 auto;
}
h1 { color: #333; border-bottom: 3px solid #1a1a1a; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
table { font-size: 13px; }
</style>
