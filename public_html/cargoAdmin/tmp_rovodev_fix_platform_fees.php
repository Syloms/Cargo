<?php
/**
 * TEMPORARY SCRIPT: Calculate and populate platform_fee and owner_payout for existing bookings
 * This script calculates the 10% platform fee and 90% owner payout for all bookings
 * that have verified payments but missing fee calculations.
 */

require_once 'include/db.php';

echo "<h2>Fixing Platform Fees and Owner Payouts</h2>";
echo "<p>Calculating fees for existing bookings...</p><br>";

// Get all bookings with verified payments but missing platform_fee or owner_payout
$sql = "
SELECT 
    b.id AS booking_id,
    b.total_amount,
    b.platform_fee,
    b.owner_payout,
    p.id AS payment_id,
    p.amount AS payment_amount,
    p.payment_status
FROM bookings b
LEFT JOIN payments p ON b.id = p.booking_id
WHERE p.payment_status = 'verified'
  AND (b.platform_fee IS NULL OR b.platform_fee = 0 OR b.owner_payout IS NULL OR b.owner_payout = 0)
ORDER BY b.id ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

$count = mysqli_num_rows($result);
echo "<p>Found <strong>$count</strong> bookings that need fee calculation.</p>";

if ($count === 0) {
    echo "<p style='color: green;'>✓ All bookings already have fees calculated!</p>";
    exit;
}

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>
        <th>Booking ID</th>
        <th>Payment Amount</th>
        <th>Old Platform Fee</th>
        <th>Old Owner Payout</th>
        <th>NEW Platform Fee (10%)</th>
        <th>NEW Owner Payout (90%)</th>
        <th>Status</th>
      </tr>";

$updated = 0;
$errors = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $bookingId = $row['booking_id'];
    $amount = (float)$row['payment_amount'];
    
    // Calculate 10% platform fee and 90% owner payout
    $platformFee = round($amount * 0.10, 2);
    $ownerPayout = round($amount - $platformFee, 2);
    
    // Display current values
    echo "<tr>";
    echo "<td>#" . $bookingId . "</td>";
    echo "<td>₱" . number_format($amount, 2) . "</td>";
    echo "<td>₱" . number_format($row['platform_fee'] ?? 0, 2) . "</td>";
    echo "<td>₱" . number_format($row['owner_payout'] ?? 0, 2) . "</td>";
    echo "<td><strong>₱" . number_format($platformFee, 2) . "</strong></td>";
    echo "<td><strong>₱" . number_format($ownerPayout, 2) . "</strong></td>";
    
    // Update the booking
    $updateSql = "
    UPDATE bookings 
    SET platform_fee = ?,
        owner_payout = ?
    WHERE id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "ddi", $platformFee, $ownerPayout, $bookingId);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "<td style='color: green;'>✓ Updated</td>";
        $updated++;
    } else {
        echo "<td style='color: red;'>✗ Error: " . mysqli_error($conn) . "</td>";
        $errors++;
    }
    
    echo "</tr>";
    mysqli_stmt_close($stmt);
}

echo "</table>";

echo "<br><hr><br>";
echo "<h3>Summary:</h3>";
echo "<p>✓ Successfully updated: <strong style='color: green;'>$updated</strong> bookings</p>";
if ($errors > 0) {
    echo "<p>✗ Errors: <strong style='color: red;'>$errors</strong> bookings</p>";
}

echo "<br><p style='color: #666; font-size: 14px;'><strong>Note:</strong> You can now delete this script file (tmp_rovodev_fix_platform_fees.php) as it's no longer needed.</p>";

mysqli_close($conn);
?>
