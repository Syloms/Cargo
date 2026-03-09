<?php
header("Content-Type: text/html; charset=UTF-8");
require_once __DIR__ . '/include/db.php';

$confirm = $_GET['confirm'] ?? 'no';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Remove Late Fee - Booking #84</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #dc3545; border-bottom: 3px solid #dc3545; padding-bottom: 10px; }
        .danger { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .btn { padding: 15px 30px; font-size: 16px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; color: white; font-weight: bold; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #333; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Remove Incorrect Late Fee - Booking #84</h1>

<?php

if ($confirm === 'yes') {
    // Execute the removal
    $bookingId = 84;
    
    // Get current values
    $getSql = "SELECT late_fee_amount, owner_payout, total_amount FROM bookings WHERE id = ?";
    $getStmt = $conn->prepare($getSql);
    $getStmt->bind_param("i", $bookingId);
    $getStmt->execute();
    $current = $getStmt->get_result()->fetch_assoc();
    
    if ($current) {
        echo "<div class='warning'>";
        echo "<h3>📋 Current Values:</h3>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Current Value</th></tr>";
        echo "<tr><td>Total Amount</td><td>₱" . number_format($current['total_amount'], 2) . "</td></tr>";
        echo "<tr><td>Owner Payout</td><td>₱" . number_format($current['owner_payout'], 2) . "</td></tr>";
        echo "<tr><td>Late Fee</td><td style='color:red; font-weight:bold'>₱" . number_format($current['late_fee_amount'], 2) . "</td></tr>";
        echo "</table>";
        echo "</div>";
        
        // Remove the late fee
        $updateSql = "UPDATE bookings 
                     SET late_fee_amount = 0,
                         late_fee_charged = 0,
                         overdue_status = 'on_time',
                         overdue_days = 0,
                         late_fee_payment_status = 'none'
                     WHERE id = ?";
        
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $bookingId);
        
        if ($updateStmt->execute()) {
            echo "<div class='success'>";
            echo "<h3>✅ Late Fee Removed Successfully!</h3>";
            echo "<p>Booking #84 has been updated:</p>";
            echo "<ul>";
            echo "<li>Late Fee Amount: ₱7,700.00 → <strong>₱0.00</strong></li>";
            echo "<li>Late Fee Charged: Yes → <strong>No</strong></li>";
            echo "<li>Overdue Status: → <strong>on_time</strong></li>";
            echo "</ul>";
            echo "<p><strong>The owner dashboard will now show the correct revenue of ₱945.00</strong></p>";
            echo "<p><a href='https://cargoph.online/cargoAdmin/tmp_rovodev_check_booking_84_details.php' class='btn btn-secondary'>View Updated Booking</a></p>";
            echo "</div>";
        } else {
            echo "<div class='danger'>";
            echo "<h3>❌ Error: Failed to update booking</h3>";
            echo "<p>" . $conn->error . "</p>";
            echo "</div>";
        }
    } else {
        echo "<div class='danger'>";
        echo "<h3>❌ Booking #84 not found!</h3>";
        echo "</div>";
    }
    
} else {
    // Show confirmation page
    echo "<div class='danger'>";
    echo "<h3>⚠️ About to Remove Late Fee</h3>";
    echo "<p><strong>Booking ID:</strong> 84</p>";
    echo "<p><strong>Current Late Fee:</strong> ₱7,700.00</p>";
    echo "<p><strong>Reason for Removal:</strong> The booking was marked as completed without recording the actual return time. There is no evidence the vehicle was returned late.</p>";
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<h3>📝 What This Will Do:</h3>";
    echo "<ul>";
    echo "<li>Set <code>late_fee_amount</code> to 0</li>";
    echo "<li>Set <code>late_fee_charged</code> to 0</li>";
    echo "<li>Set <code>overdue_status</code> to 'on_time'</li>";
    echo "<li>Set <code>overdue_days</code> to 0</li>";
    echo "</ul>";
    echo "<p><strong>Result:</strong> Owner revenue will change from ₱8,645 to ₱945</p>";
    echo "</div>";
    
    echo "<p style='text-align: center; margin: 30px 0;'>";
    echo "<a href='?confirm=yes' class='btn btn-danger'>✅ Yes, Remove the Late Fee</a>";
    echo "<a href='tmp_rovodev_check_booking_84_details.php' class='btn btn-secondary'>❌ Cancel</a>";
    echo "</p>";
}

$conn->close();
?>

</div>
</body>
</html>
