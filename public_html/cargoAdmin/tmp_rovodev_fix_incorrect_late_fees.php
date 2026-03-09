<?php
/**
 * Fix Incorrect Late Fees
 * This script finds and removes late fees that were incorrectly charged
 * on bookings that were returned on time or before the scheduled return.
 */

header("Content-Type: text/html; charset=UTF-8");
require_once __DIR__ . '/include/db.php';

// Check if this is a confirmation request
$confirm = $_GET['confirm'] ?? 'no';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Incorrect Late Fees</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 3px solid #dc3545; padding-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #dc3545; color: white; }
        .danger { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .btn { padding: 12px 24px; font-size: 16px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Fix Incorrect Late Fees</h1>

<?php

// Debug: Show all bookings with late fees first
echo "<div class='warning'>";
echo "<h3>🔍 Debug Information</h3>";

$debugSql = "SELECT COUNT(*) as total FROM bookings WHERE late_fee_amount > 0";
$debugResult = $conn->query($debugSql);
$debugRow = $debugResult->fetch_assoc();
echo "<p>Total bookings with late fees: <strong>{$debugRow['total']}</strong></p>";

$debugSql2 = "SELECT COUNT(*) as total FROM bookings WHERE late_fee_amount > 0 AND status IN ('completed', 'cancelled')";
$debugResult2 = $conn->query($debugSql2);
$debugRow2 = $debugResult2->fetch_assoc();
echo "<p>Completed/Cancelled bookings with late fees: <strong>{$debugRow2['total']}</strong></p>";

$debugSql3 = "SELECT COUNT(*) as total FROM bookings WHERE late_fee_amount > 0 AND (trip_ended_at IS NOT NULL OR actual_return_date IS NOT NULL)";
$debugResult3 = $conn->query($debugSql3);
$debugRow3 = $debugResult3->fetch_assoc();
echo "<p>Bookings with late fees AND return time recorded: <strong>{$debugRow3['total']}</strong></p>";

echo "</div>";

// Find bookings with late fees that were returned on time or early
$sql = "SELECT 
    b.id,
    b.owner_id,
    b.status,
    b.return_date,
    b.return_time,
    b.trip_ended_at,
    b.actual_return_date,
    b.actual_return_time,
    b.late_fee_amount,
    b.late_fee_charged,
    b.total_amount,
    CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
    u.fullname as renter_name,
    o.fullname as owner_name,
    CONCAT(b.return_date, ' ', b.return_time) as scheduled_return,
    COALESCE(b.trip_ended_at, CONCAT(b.actual_return_date, ' ', b.actual_return_time)) as actual_return,
    TIMESTAMPDIFF(HOUR, 
        CONCAT(b.return_date, ' ', b.return_time), 
        COALESCE(b.trip_ended_at, CONCAT(b.actual_return_date, ' ', b.actual_return_time))
    ) as hours_difference
FROM bookings b
LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN users o ON b.owner_id = o.id
WHERE b.late_fee_amount > 0
AND b.status IN ('completed', 'cancelled')
AND (
    b.trip_ended_at IS NOT NULL 
    OR b.actual_return_date IS NOT NULL
)
HAVING hours_difference <= 2";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "<div class='success'>";
    echo "<h3>✅ No Incorrect Late Fees Found!</h3>";
    echo "<p>All late fees appear to be correctly charged.</p>";
    echo "</div>";
} else {
    $incorrectBookings = [];
    $totalIncorrectFees = 0;
    
    while ($row = $result->fetch_assoc()) {
        $incorrectBookings[] = $row;
        $totalIncorrectFees += $row['late_fee_amount'];
    }
    
    echo "<div class='danger'>";
    echo "<h3>⚠️ Found " . count($incorrectBookings) . " Bookings with Incorrect Late Fees</h3>";
    echo "<p>Total incorrect fees: <strong>₱" . number_format($totalIncorrectFees, 2) . "</strong></p>";
    echo "</div>";
    
    echo "<table>";
    echo "<tr>
            <th>Booking ID</th>
            <th>Vehicle</th>
            <th>Renter</th>
            <th>Owner</th>
            <th>Scheduled Return</th>
            <th>Actual Return</th>
            <th>Difference</th>
            <th>Late Fee</th>
          </tr>";
    
    foreach ($incorrectBookings as $booking) {
        $hoursDiff = $booking['hours_difference'];
        $diffText = $hoursDiff > 0 
            ? "<span style='color:red'>+" . $hoursDiff . " hours (LATE)</span>"
            : "<span style='color:green'>" . $hoursDiff . " hours (EARLY/ON TIME)</span>";
        
        echo "<tr>";
        echo "<td>#{$booking['id']}</td>";
        echo "<td>{$booking['vehicle_name']}</td>";
        echo "<td>{$booking['renter_name']}</td>";
        echo "<td>{$booking['owner_name']}</td>";
        echo "<td>{$booking['scheduled_return']}</td>";
        echo "<td>{$booking['actual_return']}</td>";
        echo "<td>{$diffText}</td>";
        echo "<td style='color:red; font-weight:bold'>₱" . number_format($booking['late_fee_amount'], 2) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    if ($confirm === 'yes') {
        // Execute the fix
        echo "<div class='warning'>";
        echo "<h3>🔄 Removing Incorrect Late Fees...</h3>";
        echo "</div>";
        
        $conn->begin_transaction();
        
        try {
            $fixedCount = 0;
            $fixedTotal = 0;
            
            foreach ($incorrectBookings as $booking) {
                // Remove late fee
                $updateSql = "UPDATE bookings 
                             SET late_fee_amount = 0,
                                 late_fee_charged = 0,
                                 overdue_status = NULL,
                                 overdue_days = 0
                             WHERE id = ?";
                
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("i", $booking['id']);
                
                if ($stmt->execute()) {
                    $fixedCount++;
                    $fixedTotal += $booking['late_fee_amount'];
                    echo "<p>✅ Fixed Booking #{$booking['id']}: Removed ₱" . number_format($booking['late_fee_amount'], 2) . "</p>";
                } else {
                    echo "<p style='color:red'>❌ Failed to fix Booking #{$booking['id']}</p>";
                }
            }
            
            $conn->commit();
            
            echo "<div class='success'>";
            echo "<h3>✅ Successfully Fixed {$fixedCount} Bookings!</h3>";
            echo "<p>Total late fees removed: <strong>₱" . number_format($fixedTotal, 2) . "</strong></p>";
            echo "<p><a href='tmp_rovodev_fix_incorrect_late_fees.php' class='btn btn-secondary'>Check Again</a></p>";
            echo "</div>";
            
        } catch (Exception $e) {
            $conn->rollback();
            echo "<div class='danger'>";
            echo "<h3>❌ Error occurred: " . $e->getMessage() . "</h3>";
            echo "</div>";
        }
        
    } else {
        // Show confirmation button
        echo "<div class='warning'>";
        echo "<h3>⚠️ Action Required</h3>";
        echo "<p>Click the button below to remove these incorrect late fees.</p>";
        echo "<p><a href='?confirm=yes' class='btn btn-danger'>Remove All Incorrect Late Fees</a></p>";
        echo "<p><strong>Note:</strong> This will set late_fee_amount = 0 for all bookings listed above.</p>";
        echo "</div>";
    }
}

$conn->close();
?>
</div>
</body>
</html>
