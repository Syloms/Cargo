<?php
// Quick check for owner 22's bookings
require_once 'include/db.php';

$owner_id = 22;

echo "<h2>Bookings for Owner #$owner_id</h2>";

$query = "SELECT 
    b.id,
    b.car_id,
    b.vehicle_type,
    b.total_amount,
    b.owner_payout,
    b.late_fee_amount,
    b.late_fee_charged,
    b.status,
    b.escrow_status,
    b.payout_status,
    b.payment_verified_at,
    b.created_at,
    COALESCE(c.brand, m.brand) as brand,
    COALESCE(c.model, m.model) as model
FROM bookings b
LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
WHERE b.owner_id = ?
ORDER BY b.id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $owner_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr style='background: #4CAF50; color: white;'>
    <th>ID</th>
    <th>Vehicle</th>
    <th>Total Amount</th>
    <th>Owner Payout</th>
    <th>Late Fee</th>
    <th>Status</th>
    <th>Escrow</th>
    <th>Payout</th>
    <th>Created</th>
</tr>";

$counted_revenue = 0;
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    
    // Check if this booking would be counted in revenue
    $is_counted = false;
    if (
        in_array($row['escrow_status'], ['held', 'released_to_owner']) ||
        $row['payout_status'] == 'completed' ||
        ($row['status'] == 'completed' && $row['payment_verified_at'] != null)
    ) {
        $is_counted = true;
        $booking_revenue = $row['owner_payout'];
        if ($row['late_fee_charged'] == 1) {
            $booking_revenue += $row['late_fee_amount'];
        }
        $counted_revenue += $booking_revenue;
    }
    
    $rowColor = $is_counted ? '#e8f5e9' : '#ffebee';
    
    echo "<tr style='background: $rowColor;'>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['brand']} {$row['model']}</td>";
    echo "<td>₱{$row['total_amount']}</td>";
    echo "<td>₱{$row['owner_payout']}</td>";
    echo "<td>" . ($row['late_fee_charged'] ? "₱{$row['late_fee_amount']}" : "-") . "</td>";
    echo "<td>{$row['status']}</td>";
    echo "<td>{$row['escrow_status']}</td>";
    echo "<td>{$row['payout_status']}</td>";
    echo "<td>" . date('Y-m-d', strtotime($row['created_at'])) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Total Bookings: $count</strong></p>";
echo "<p><strong>Revenue Counted (matching API logic): ₱$counted_revenue</strong></p>";

echo "<hr>";
echo "<h3>API Query Simulation:</h3>";
$query = "
    SELECT 
        COALESCE(SUM(
            b.owner_payout + 
            CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
        ), 0) as total
    FROM bookings b
    WHERE b.owner_id = ? 
    AND (
        b.escrow_status IN ('held', 'released_to_owner')
        OR b.payout_status = 'completed'
        OR (b.status = 'completed' AND b.payment_verified_at IS NOT NULL)
    )
";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $owner_id);
$stmt->execute();
$api_result = $stmt->get_result()->fetch_assoc()['total'];

echo "<p><strong>API Total Income Query Result: ₱$api_result</strong></p>";

echo "<hr>";
echo "<p style='color: green;'>✅ Green rows = Counted in revenue</p>";
echo "<p style='color: red;'>❌ Red rows = NOT counted in revenue</p>";

$conn->close();
?>