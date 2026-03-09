<?php
/**
 * Debug tool to see what bookings an owner has and their trip_status
 */
header('Content-Type: text/html; charset=utf-8');
require_once '../../include/db.php';

echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
table { width: 100%; background: white; border-collapse: collapse; margin: 20px 0; }
th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
th { background: #333; color: white; }
.upcoming { background: #fff3cd; }
.in_progress { background: #d4edda; }
.past { background: #f8d7da; }
</style>";

echo "<h1>🔍 Owner Bookings Debug Tool</h1>";

// Get owner_id from URL or show form
$owner_id = $_GET['owner_id'] ?? null;

if (!$owner_id) {
    echo "<form method='get'>";
    echo "Enter Owner ID: <input type='text' name='owner_id' placeholder='e.g., 1' required>";
    echo "<button type='submit'>Check Bookings</button>";
    echo "</form>";
    
    // Show recent owners
    $ownersSql = "SELECT DISTINCT owner_id, (SELECT fullname FROM users WHERE id = b.owner_id) as owner_name 
                  FROM bookings b 
                  WHERE status = 'approved' 
                  LIMIT 10";
    $result = $conn->query($ownersSql);
    
    if ($result && $result->num_rows > 0) {
        echo "<h3>Recent Owners:</h3><ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li><a href='?owner_id={$row['owner_id']}'>{$row['owner_name']} (ID: {$row['owner_id']})</a></li>";
        }
        echo "</ul>";
    }
    exit;
}

echo "<h2>Owner ID: $owner_id</h2>";

// Get bookings with detailed info
$sql = "
SELECT 
    b.id,
    b.status,
    b.pickup_date,
    b.return_date,
    b.trip_started_at,
    COALESCE(c.brand, m.brand) AS brand,
    COALESCE(c.model, m.model) AS model,
    u.fullname AS renter_name,
    
    -- Status calculation
    CASE 
        WHEN b.trip_started_at IS NOT NULL THEN 'in_progress'
        WHEN b.pickup_date > CURDATE() THEN 'upcoming'
        WHEN b.pickup_date <= CURDATE() AND b.return_date >= CURDATE() THEN 'in_progress'
        ELSE 'past'
    END AS trip_status,
    
    DATEDIFF(b.pickup_date, CURDATE()) AS days_until_pickup,
    DATEDIFF(b.return_date, CURDATE()) AS days_remaining
    
FROM bookings b
LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
LEFT JOIN users u ON b.user_id = u.id
WHERE b.owner_id = ?
AND b.status = 'approved'
ORDER BY b.pickup_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color: red;'>❌ No approved bookings found for this owner.</p>";
    echo "<p>Make sure the owner has approved bookings in the system.</p>";
    exit;
}

echo "<p style='color: green;'>✅ Found " . $result->num_rows . " approved booking(s)</p>";

echo "<table>";
echo "<tr>
    <th>ID</th>
    <th>Vehicle</th>
    <th>Renter</th>
    <th>Pickup Date</th>
    <th>Return Date</th>
    <th>Days Until Pickup</th>
    <th>Trip Status</th>
    <th>Started At</th>
    <th>Button Shown</th>
</tr>";

$upcomingCount = 0;
$activeCount = 0;

while ($row = $result->fetch_assoc()) {
    $statusClass = $row['trip_status'];
    $buttonText = '';
    
    if ($row['trip_status'] === 'upcoming') {
        $buttonText = '🟢 Start Rent / Picked Up';
        $upcomingCount++;
    } elseif ($row['trip_status'] === 'in_progress') {
        $buttonText = '🔵 Track Car Location';
        $activeCount++;
    } else {
        $buttonText = '⚫ No button (past)';
    }
    
    echo "<tr class='{$statusClass}'>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['brand']} {$row['model']}</td>";
    echo "<td>{$row['renter_name']}</td>";
    echo "<td>{$row['pickup_date']}</td>";
    echo "<td>{$row['return_date']}</td>";
    echo "<td>" . ($row['days_until_pickup'] > 0 ? "+{$row['days_until_pickup']} days" : "{$row['days_until_pickup']} days") . "</td>";
    echo "<td><strong>{$row['trip_status']}</strong></td>";
    echo "<td>" . ($row['trip_started_at'] ?? '<em>Not started</em>') . "</td>";
    echo "<td>{$buttonText}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<div style='background: white; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
echo "<h3>📊 Summary</h3>";
echo "<ul>";
echo "<li><strong>Upcoming Bookings:</strong> $upcomingCount (will show green 'Start Rent' button)</li>";
echo "<li><strong>Active Bookings:</strong> $activeCount (will show blue 'Track Location' button)</li>";
echo "<li><strong>Total Approved:</strong> " . $result->num_rows . "</li>";
echo "</ul>";

if ($upcomingCount === 0) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h4>⚠️ No Upcoming Bookings!</h4>";
    echo "<p>You won't see the 'Start Rent' button because all bookings have already started or are in the past.</p>";
    echo "<p><strong>To test the feature:</strong></p>";
    echo "<ol>";
    echo "<li>Create a new booking with a future pickup date (tomorrow or later)</li>";
    echo "<li>Approve the booking</li>";
    echo "<li>Check the Active Bookings page in the app</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h4>✅ Great! You have $upcomingCount upcoming booking(s)</h4>";
    echo "<p>These bookings should show the green 'Start Rent / Picked Up' button in your Flutter app.</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Restart your Flutter app: <code>flutter clean && flutter run</code></li>";
    echo "<li>Login as the owner</li>";
    echo "<li>Go to 'Active Bookings'</li>";
    echo "<li>You should see the green 'Start Rent / Picked Up' button on upcoming bookings</li>";
    echo "</ol>";
    echo "</div>";
}
echo "</div>";

echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
echo "<h4>🔗 Quick Links</h4>";
echo "<ul>";
echo "<li><a href='check_trip_started_field.php'>Check Database Field</a></li>";
echo "<li><a href='test_start_trip.php'>Test Start Trip API</a></li>";
echo "<li><a href='get_owner_active_bookings.php?owner_id=$owner_id' target='_blank'>View API Response</a></li>";
echo "</ul>";
echo "</div>";

$stmt->close();
$conn->close();
?>
