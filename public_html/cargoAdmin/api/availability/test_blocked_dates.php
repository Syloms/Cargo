<?php
/**
 * Test script to check blocked dates functionality
 */

header('Content-Type: text/html; charset=utf-8');

require_once '../../include/db.php';

echo "<h2>🔍 Blocked Dates Diagnostic Tool</h2>";
echo "<hr>";

// Check if table exists
echo "<h3>1. Check if vehicle_availability table exists</h3>";
$checkTable = "SHOW TABLES LIKE 'vehicle_availability'";
$result = mysqli_query($conn, $checkTable);
if (mysqli_num_rows($result) > 0) {
    echo "✅ Table exists<br>";
} else {
    echo "❌ Table does NOT exist<br>";
}

// Count total records
echo "<h3>2. Count records in vehicle_availability</h3>";
$countQuery = "SELECT COUNT(*) as total FROM vehicle_availability";
$countResult = mysqli_query($conn, $countQuery);
$count = mysqli_fetch_assoc($countResult);
echo "📊 Total blocked dates: <strong>{$count['total']}</strong><br>";

// Show sample data
if ($count['total'] > 0) {
    echo "<h3>3. Sample blocked dates (latest 10)</h3>";
    $sampleQuery = "SELECT * FROM vehicle_availability ORDER BY created_at DESC LIMIT 10";
    $sampleResult = mysqli_query($conn, $sampleQuery);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Owner ID</th><th>Vehicle ID</th><th>Type</th><th>Blocked Date</th><th>Reason</th><th>Created</th></tr>";
    
    while ($row = mysqli_fetch_assoc($sampleResult)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['owner_id']}</td>";
        echo "<td>{$row['vehicle_id']}</td>";
        echo "<td>{$row['vehicle_type']}</td>";
        echo "<td>{$row['blocked_date']}</td>";
        echo "<td>{$row['reason']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No blocked dates found in database</p>";
}

// Test API call
echo "<h3>4. Test API call (vehicle_id=17, type=car)</h3>";
$testVehicleId = 17;
$testVehicleType = 'car';
$testStartDate = date('Y-m-d');
$testEndDate = date('Y-m-d', strtotime('+6 months'));

echo "Testing: vehicle_id=$testVehicleId, vehicle_type=$testVehicleType<br>";
echo "Date range: $testStartDate to $testEndDate<br><br>";

// Simulate API call
$query = "SELECT blocked_date, reason, created_at 
          FROM vehicle_availability 
          WHERE vehicle_id = ? AND vehicle_type = ? 
          AND blocked_date BETWEEN ? AND ?
          ORDER BY blocked_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("isss", $testVehicleId, $testVehicleType, $testStartDate, $testEndDate);
$stmt->execute();
$result = $stmt->get_result();

$blocked_dates = [];
while ($row = $result->fetch_assoc()) {
    $blocked_dates[] = $row['blocked_date'];
}

echo "<strong>Blocked dates found for vehicle $testVehicleId:</strong> " . count($blocked_dates) . "<br>";
if (count($blocked_dates) > 0) {
    echo "<pre>" . print_r($blocked_dates, true) . "</pre>";
} else {
    echo "<p style='color: orange;'>⚠️ No blocked dates for this vehicle</p>";
}

// Check bookings
echo "<h3>5. Check bookings for vehicle $testVehicleId</h3>";
$bookingQuery = "SELECT id, pickup_date, return_date, status 
                 FROM bookings 
                 WHERE car_id = ? AND vehicle_type = ?
                 AND status IN ('pending', 'approved', 'ongoing')
                 LIMIT 10";
$bookingStmt = $conn->prepare($bookingQuery);
$bookingStmt->bind_param("is", $testVehicleId, $testVehicleType);
$bookingStmt->execute();
$bookingResult = $bookingStmt->get_result();

$bookingCount = $bookingResult->num_rows;
echo "📅 Active bookings: <strong>$bookingCount</strong><br>";

if ($bookingCount > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Booking ID</th><th>Pickup Date</th><th>Return Date</th><th>Status</th></tr>";
    
    while ($row = $bookingResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['pickup_date']}</td>";
        echo "<td>{$row['return_date']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test the actual API endpoint
echo "<h3>6. Test actual API endpoint</h3>";
$apiUrl = "http://localhost/carGOAdmin/api/availability/get_blocked_dates.php?vehicle_id=$testVehicleId&vehicle_type=$testVehicleType&start_date=$testStartDate&end_date=$testEndDate";
echo "API URL: <a href='$apiUrl' target='_blank'>$apiUrl</a><br>";
echo "<iframe src='$apiUrl' width='100%' height='200' style='border: 1px solid #ccc; margin-top: 10px;'></iframe>";

echo "<hr>";
echo "<h3>💡 Troubleshooting Tips</h3>";
echo "<ul>";
echo "<li>If table doesn't exist, the calendar will create it on first access</li>";
echo "<li>If no blocked dates, try blocking some dates from the owner app calendar</li>";
echo "<li>Check that vehicle_id and vehicle_type match your test vehicle</li>";
echo "<li>Verify API endpoint is accessible from Flutter app</li>";
echo "</ul>";

$conn->close();
?>
