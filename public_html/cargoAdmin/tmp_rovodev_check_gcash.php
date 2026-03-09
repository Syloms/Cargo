<?php
/**
 * Temporary debug script to check GCash configuration for Owner ID 16
 */

include "include/db.php";

$owner_id = 16;

echo "<h2>GCash Configuration Check for Owner ID: $owner_id</h2>";

// Check user data
$query = "SELECT id, fullname, email, gcash_number, gcash_name, role FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "<h3>User Information:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$row['id']}</td></tr>";
    echo "<tr><td>Full Name</td><td>{$row['fullname']}</td></tr>";
    echo "<tr><td>Email</td><td>{$row['email']}</td></tr>";
    echo "<tr><td>Role</td><td>{$row['role']}</td></tr>";
    echo "<tr><td>GCash Number</td><td>" . ($row['gcash_number'] ?: '<strong style="color: red;">NULL/EMPTY</strong>') . "</td></tr>";
    echo "<tr><td>GCash Name</td><td>" . ($row['gcash_name'] ?: '<strong style="color: red;">NULL/EMPTY</strong>') . "</td></tr>";
    echo "</table>";
    
    // Check if gcash is configured
    if (!empty($row['gcash_number']) && !empty($row['gcash_name'])) {
        echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ GCash IS CONFIGURED</p>";
    } else {
        echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ GCash NOT CONFIGURED</p>";
        echo "<p>The owner needs to edit their profile in the Flutter app and enter:</p>";
        echo "<ul>";
        echo "<li>GCash Number (11 digits, e.g., 09451547348)</li>";
        echo "<li>GCash Account Name</li>";
        echo "</ul>";
    }
    
    // Show raw data types
    echo "<h3>Raw Data Types:</h3>";
    echo "<pre>";
    echo "gcash_number type: " . gettype($row['gcash_number']) . "\n";
    echo "gcash_number value: " . var_export($row['gcash_number'], true) . "\n";
    echo "gcash_name type: " . gettype($row['gcash_name']) . "\n";
    echo "gcash_name value: " . var_export($row['gcash_name'], true) . "\n";
    echo "</pre>";
} else {
    echo "<p style='color: red;'>User with ID $owner_id not found!</p>";
}

// Check bookings for this owner
echo "<h3>Bookings for Owner ID $owner_id:</h3>";
$bookingQuery = "SELECT id, user_id as renter_id, owner_id, escrow_status, payout_status 
                 FROM bookings 
                 WHERE owner_id = ? 
                 ORDER BY id DESC 
                 LIMIT 5";
$stmt2 = $conn->prepare($bookingQuery);
$stmt2->bind_param("i", $owner_id);
$stmt2->execute();
$bookingResult = $stmt2->get_result();

if ($bookingResult->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Booking ID</th><th>Renter ID</th><th>Owner ID</th><th>Escrow Status</th><th>Payout Status</th></tr>";
    while ($booking = $bookingResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$booking['id']}</td>";
        echo "<td>{$booking['renter_id']}</td>";
        echo "<td>{$booking['owner_id']}</td>";
        echo "<td>{$booking['escrow_status']}</td>";
        echo "<td>{$booking['payout_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No bookings found for this owner.</p>";
}

$conn->close();
?>
<br><br>
<a href="payouts.php">← Back to Payouts</a>
