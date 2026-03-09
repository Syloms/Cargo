<?php
header("Content-Type: text/html; charset=UTF-8");
require_once __DIR__ . '/include/db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking #84 - Detailed Check</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 3px solid #2196f3; padding-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #333; color: white; }
        .danger { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .btn { padding: 12px 24px; font-size: 16px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; color: white; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Booking #84 - Complete Details</h1>

<?php
$bookingId = 84;

// Get all fields from booking #84
$sql = "SELECT * FROM bookings WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<div class='danger'>❌ Booking #84 not found!</div>";
    exit;
}

echo "<h2>📋 All Booking Fields</h2>";
echo "<table>";
echo "<tr><th>Field Name</th><th>Value</th></tr>";

foreach ($booking as $field => $value) {
    $displayValue = $value ?? '<span style="color: #999;">NULL</span>';
    
    // Highlight important fields
    $highlight = '';
    if (in_array($field, ['status', 'late_fee_amount', 'late_fee_charged', 'trip_started', 'trip_ended', 'trip_ended_at', 'actual_return_date'])) {
        $highlight = ' style="background: #fff3cd; font-weight: bold;"';
    }
    
    echo "<tr$highlight>";
    echo "<td><strong>$field</strong></td>";
    echo "<td>$displayValue</td>";
    echo "</tr>";
}

echo "</table>";

// Analysis
echo "<h2>🎯 Analysis</h2>";

$scheduledReturn = $booking['return_date'] . ' ' . $booking['return_time'];
$actualReturn = $booking['trip_ended_at'] ?? ($booking['actual_return_date'] ? $booking['actual_return_date'] . ' ' . $booking['actual_return_time'] : null);

echo "<div style='background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0;'>";
echo "<p><strong>Scheduled Return:</strong> $scheduledReturn</p>";
echo "<p><strong>Actual Return:</strong> " . ($actualReturn ?? '<span style="color: red;">NOT RECORDED</span>') . "</p>";
echo "<p><strong>Status:</strong> {$booking['status']}</p>";
echo "<p><strong>Trip Started:</strong> " . ($booking['trip_started'] ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Trip Ended:</strong> " . ($booking['trip_ended'] ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Late Fee Amount:</strong> ₱" . number_format($booking['late_fee_amount'], 2) . "</p>";
echo "<p><strong>Late Fee Charged:</strong> " . ($booking['late_fee_charged'] ? 'Yes' : 'No') . "</p>";
echo "</div>";

if ($actualReturn) {
    $scheduledTime = strtotime($scheduledReturn);
    $actualTime = strtotime($actualReturn);
    $diffSeconds = $actualTime - $scheduledTime;
    $diffHours = round($diffSeconds / 3600, 2);
    $diffMinutes = round($diffSeconds / 60);
    
    echo "<h3>⏰ Time Difference</h3>";
    echo "<p style='font-size: 18px;'>";
    if ($diffSeconds > 7200) { // More than 2 hour grace
        echo "<span style='color: red;'>🚨 LATE by " . abs($diffHours) . " hours ($diffMinutes minutes)</span>";
    } elseif ($diffSeconds > 0) {
        echo "<span style='color: orange;'>⚠️ " . abs($diffHours) . " hours late (within grace period)</span>";
    } else {
        echo "<span style='color: green;'>✅ RETURNED EARLY/ON TIME by " . abs($diffHours) . " hours</span>";
    }
    echo "</p>";
}

// Decision
echo "<h2>💡 Recommendation</h2>";

if (!$actualReturn) {
    echo "<div class='danger'>";
    echo "<h3>⚠️ Cannot Determine if Fee is Correct</h3>";
    echo "<p>The booking doesn't have a recorded return time (trip_ended_at or actual_return_date is NULL).</p>";
    echo "<p>This means we cannot automatically verify if the late fee is correct.</p>";
    echo "<p><strong>Options:</strong></p>";
    echo "<ul>";
    echo "<li>Manually verify with the owner/renter when the vehicle was actually returned</li>";
    echo "<li>Remove the late fee if you believe it's incorrect</li>";
    echo "</ul>";
    echo "</div>";
} elseif ($actualReturn && $diffSeconds <= 7200) {
    echo "<div class='danger'>";
    echo "<h3>🚨 INCORRECT LATE FEE - Should Be Removed!</h3>";
    echo "<p>The vehicle was returned within the 2-hour grace period, so no late fee should apply.</p>";
    echo "<p><a href='tmp_rovodev_remove_late_fee_84.php' class='btn btn-danger'>Remove Late Fee from Booking #84</a></p>";
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<h3>✅ Late Fee is Legitimate</h3>";
    echo "<p>The vehicle was returned more than 2 hours late, so the late fee is correct.</p>";
    echo "</div>";
}

$conn->close();
?>
</div>
</body>
</html>
