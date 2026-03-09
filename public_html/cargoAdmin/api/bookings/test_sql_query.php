<?php
/**
 * Test the exact SQL query to see what's happening
 */
header('Content-Type: text/html; charset=utf-8');
require_once '../../include/db.php';

echo "<h2>SQL Query Test</h2>";

// First check if field exists
echo "<h3>Step 1: Check if trip_started_at field exists</h3>";
$checkField = "SHOW COLUMNS FROM bookings LIKE 'trip_started_at'";
$result = $conn->query($checkField);

if ($result && $result->num_rows > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>✅ Field EXISTS</div>";
    $field = $result->fetch_assoc();
    echo "<pre>" . print_r($field, true) . "</pre>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>❌ Field DOES NOT EXIST - This is the problem!</div>";
    echo "<p>Run this SQL to add it:</p>";
    echo "<pre>ALTER TABLE bookings ADD COLUMN trip_started_at DATETIME NULL AFTER pickup_time;</pre>";
    exit;
}

// Now test the actual query
echo "<h3>Step 2: Test the CASE statement</h3>";

$owner_id = $_GET['owner_id'] ?? '16';

$testSql = "
SELECT 
    b.id AS booking_id,
    b.pickup_date,
    b.return_date,
    b.trip_started_at,
    CURDATE() as today,
    b.return_date >= CURDATE() as return_future,
    
    -- Test CASE statement step by step
    CASE 
        WHEN b.trip_started_at IS NOT NULL THEN 'has_started'
        ELSE 'not_started'
    END as start_check,
    
    CASE 
        WHEN b.return_date >= CURDATE() THEN 'return_future'
        ELSE 'return_past'
    END as return_check,
    
    -- The actual trip_status CASE
    CASE 
        WHEN b.trip_started_at IS NOT NULL THEN 'in_progress'
        WHEN b.trip_started_at IS NULL AND b.return_date >= CURDATE() THEN 'upcoming'
        ELSE 'past'
    END AS trip_status
    
FROM bookings b
WHERE b.owner_id = ?
AND b.status = 'approved'
LIMIT 5
";

$stmt = $conn->prepare($testSql);
$stmt->bind_param("s", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>
        <th>Booking ID</th>
        <th>Pickup Date</th>
        <th>Return Date</th>
        <th>Today</th>
        <th>trip_started_at</th>
        <th>Start Check</th>
        <th>Return Check</th>
        <th>Final trip_status</th>
    </tr>";
    
    while ($row = $result->fetch_assoc()) {
        $statusColor = $row['trip_status'] === 'upcoming' ? 'orange' : 'green';
        echo "<tr>";
        echo "<td>{$row['booking_id']}</td>";
        echo "<td>{$row['pickup_date']}</td>";
        echo "<td>{$row['return_date']}</td>";
        echo "<td>{$row['today']}</td>";
        echo "<td>" . ($row['trip_started_at'] ?? '<strong>NULL</strong>') . "</td>";
        echo "<td>{$row['start_check']}</td>";
        echo "<td>{$row['return_check']}</td>";
        echo "<td style='background: $statusColor; color: white; font-weight: bold;'>{$row['trip_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Step 3: Analysis</h3>";
    $result->data_seek(0);
    $row = $result->fetch_assoc();
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h4>Booking #{$row['booking_id']} Logic:</h4>";
    echo "<ul>";
    echo "<li><strong>trip_started_at:</strong> " . ($row['trip_started_at'] ?? 'NULL') . "</li>";
    echo "<li><strong>Start Check:</strong> {$row['start_check']}</li>";
    echo "<li><strong>Return Check:</strong> {$row['return_check']}</li>";
    echo "<li><strong>Final Status:</strong> <strong>{$row['trip_status']}</strong></li>";
    echo "</ul>";
    
    if ($row['trip_status'] === 'upcoming') {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
        echo "✅ <strong>CORRECT!</strong> This should show the green 'Start Rent' button";
        echo "</div>";
    } else if ($row['trip_started_at'] === null && $row['return_check'] === 'return_future') {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
        echo "❌ <strong>WRONG!</strong> trip_started_at is NULL and return is in future, but status is '{$row['trip_status']}'";
        echo "<br>Expected: 'upcoming'";
        echo "<br>Got: '{$row['trip_status']}'";
        echo "</div>";
    }
    echo "</div>";
    
} else {
    echo "<p>No bookings found for owner_id = $owner_id</p>";
}

$stmt->close();
$conn->close();
?>
