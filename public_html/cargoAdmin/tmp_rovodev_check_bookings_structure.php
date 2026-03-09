<?php
/**
 * Check bookings table structure
 */

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$db = "u672913452_dbcargo";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Bookings Table Structure Check</h2>";
echo "<hr>";

// Check bookings table columns
echo "<h3>1. Bookings Table Columns:</h3>";
$result = $conn->query("SHOW COLUMNS FROM bookings");
if ($result) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $highlight = '';
        if ($row['Field'] == 'car_id' || $row['Field'] == 'motorcycle_id') {
            $highlight = "style='background-color: #ffffcc;'";
        }
        echo "<tr $highlight>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
}

// Check if motorcycle_id exists
echo "<h3>2. Motorcycle Support Check:</h3>";
$motorcycleColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'motorcycle_id'");
echo "<p><strong>motorcycle_id column exists:</strong> " . ($motorcycleColumn && $motorcycleColumn->num_rows > 0 ? "✓ YES" : "✗ NO") . "</p>";

$motorcyclesTable = $conn->query("SHOW TABLES LIKE 'motorcycles'");
echo "<p><strong>motorcycles table exists:</strong> " . ($motorcyclesTable && $motorcyclesTable->num_rows > 0 ? "✓ YES" : "✗ NO") . "</p>";

// Check sample bookings
echo "<h3>3. Sample Bookings (First 5):</h3>";
$sampleQuery = "
    SELECT 
        b.id,
        b.car_id,
        b.user_id,
        b.status,
        c.brand as car_brand,
        c.model as car_model,
        c.id as actual_car_id
    FROM bookings b
    LEFT JOIN cars c ON c.id = b.car_id
    ORDER BY b.created_at DESC
    LIMIT 5
";

$sampleResult = $conn->query($sampleQuery);
if ($sampleResult) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Booking ID</th><th>car_id</th><th>user_id</th><th>Car Exists?</th><th>Brand</th><th>Model</th><th>Status</th></tr>";
    
    while ($row = $sampleResult->fetch_assoc()) {
        $carExists = $row['actual_car_id'] !== null;
        $rowStyle = $carExists ? "" : "style='background-color: #ffcccc;'";
        
        echo "<tr $rowStyle>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['car_id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . ($carExists ? "✓ Yes" : "✗ Deleted") . "</td>";
        echo "<td>" . ($row['car_brand'] ?? '<i>NULL</i>') . "</td>";
        echo "<td>" . ($row['car_model'] ?? '<i>NULL</i>') . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
}

// Check for bookings referencing non-existent cars
echo "<h3>4. Orphaned Bookings Analysis:</h3>";
$orphanedQuery = "
    SELECT 
        b.car_id,
        COUNT(*) as booking_count,
        GROUP_CONCAT(b.id ORDER BY b.id SEPARATOR ', ') as booking_ids
    FROM bookings b
    LEFT JOIN cars c ON c.id = b.car_id
    WHERE c.id IS NULL
    GROUP BY b.car_id
";

$orphanedResult = $conn->query($orphanedQuery);
if ($orphanedResult && $orphanedResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Deleted Car ID</th><th>Number of Bookings</th><th>Booking IDs</th></tr>";
    
    $totalOrphaned = 0;
    while ($row = $orphanedResult->fetch_assoc()) {
        echo "<tr style='background-color: #ffcccc;'>";
        echo "<td><strong>" . $row['car_id'] . "</strong></td>";
        echo "<td>" . $row['booking_count'] . "</td>";
        echo "<td>" . $row['booking_ids'] . "</td>";
        echo "</tr>";
        $totalOrphaned += $row['booking_count'];
    }
    echo "</table>";
    echo "<p><strong>Total orphaned bookings: $totalOrphaned</strong></p>";
    echo "<p style='color: #ff6600;'>⚠️ These bookings reference deleted vehicles and will show 'Vehicle Not Found'</p>";
} else {
    echo "<p style='color: green;'>✓ No orphaned bookings found. All bookings have valid vehicle references.</p>";
}

// Recommendation
echo "<h3>5. Recommendations:</h3>";
echo "<div style='background: #f0f0f0; padding: 15px; border-left: 4px solid #2196f3;'>";

$motorcycleColumnExists = $conn->query("SHOW COLUMNS FROM bookings LIKE 'motorcycle_id'");
if (!$motorcycleColumnExists || $motorcycleColumnExists->num_rows == 0) {
    echo "<p><strong>To add motorcycle support:</strong></p>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ddd;'>";
    echo "ALTER TABLE bookings ADD COLUMN motorcycle_id INT(11) NULL AFTER car_id;";
    echo "</pre>";
    echo "<p>This will allow bookings to reference either cars OR motorcycles.</p>";
}

$orphanedCheck = $conn->query("
    SELECT COUNT(*) as count 
    FROM bookings b 
    LEFT JOIN cars c ON c.id = b.car_id 
    WHERE c.id IS NULL
");
$orphanedCount = $orphanedCheck ? $orphanedCheck->fetch_assoc()['count'] : 0;

if ($orphanedCount > 0) {
    echo "<p><strong>To clean up orphaned bookings:</strong></p>";
    echo "<p>Option 1 - Mark as cancelled (Recommended):</p>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ddd;'>";
    echo "UPDATE bookings b\n";
    echo "LEFT JOIN cars c ON c.id = b.car_id\n";
    echo "SET b.status = 'cancelled'\n";
    echo "WHERE c.id IS NULL AND b.status NOT IN ('completed', 'cancelled');";
    echo "</pre>";
    
    echo "<p>Option 2 - Delete orphaned bookings (Use with caution):</p>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ddd;'>";
    echo "DELETE b FROM bookings b\n";
    echo "LEFT JOIN cars c ON c.id = b.car_id\n";
    echo "WHERE c.id IS NULL;";
    echo "</pre>";
}

echo "</div>";

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background: #f5f5f5;
}
h2 {
    color: #333;
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 10px;
}
h3 {
    color: #555;
    margin-top: 30px;
}
table {
    background: white;
    margin: 10px 0;
}
</style>
