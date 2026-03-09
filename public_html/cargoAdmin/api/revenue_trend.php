<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
require_once __DIR__ . "/../include/db.php";

$owner_id = $_GET['owner_id'] ?? 0;
$period = $_GET['period'] ?? 'week'; // week, month, year

if ($owner_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing owner_id"]);
    exit;
}

// Validate period
$validPeriods = ['week' => 7, 'month' => 30, 'year' => 365];
$days = $validPeriods[$period] ?? 7;

$query = "
    SELECT 
        DATE(b.created_at) as date, 
        SUM(
            b.owner_payout + 
            CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
        ) as revenue,
        COUNT(*) as booking_count
    FROM bookings b
    WHERE b.owner_id = ? 
    AND (
        b.escrow_status IN ('held', 'released_to_owner')
        OR b.payout_status = 'completed'
        OR (b.status = 'completed' AND b.payment_verified_at IS NOT NULL)
    )
    AND b.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE(b.created_at)
    ORDER BY date ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $owner_id, $days);
$stmt->execute();
$result = $stmt->get_result();

$trend = [];
while ($row = $result->fetch_assoc()) {
    $trend[] = [
        "date" => $row['date'],
        "revenue" => floatval($row['revenue']),
        "bookings" => intval($row['booking_count'])
    ];
}

// Calculate refunds for the period
$refundQuery = "
    SELECT 
        DATE(r.processed_at) as date,
        SUM(r.refund_amount - COALESCE(r.deduction_amount, 0)) as refund_amount
    FROM refunds r
    INNER JOIN bookings b ON r.booking_id = b.id
    WHERE b.owner_id = ?
    AND r.status IN ('completed', 'processing')
    AND r.processed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE(r.processed_at)
";

$refundStmt = $conn->prepare($refundQuery);
$refundStmt->bind_param("ii", $owner_id, $days);
$refundStmt->execute();
$refundResult = $refundStmt->get_result();

$refunds = [];
while ($row = $refundResult->fetch_assoc()) {
    $refunds[$row['date']] = floatval($row['refund_amount']);
}

// Adjust revenue by subtracting refunds per day
$totalRefunds = 0;
foreach ($trend as &$dayData) {
    $date = $dayData['date'];
    $refundAmount = $refunds[$date] ?? 0;
    $dayData['gross_revenue'] = $dayData['revenue'];
    $dayData['refunds'] = $refundAmount;
    $dayData['revenue'] = $dayData['revenue'] - $refundAmount; // Net revenue
    $totalRefunds += $refundAmount;
}

echo json_encode([
    "success" => true,
    "period" => $period,
    "data" => $trend,
    "summary" => [
        "gross_revenue" => array_sum(array_column($trend, 'gross_revenue')),
        "total_refunds" => $totalRefunds,
        "net_revenue" => array_sum(array_column($trend, 'revenue'))
    ]
]);

$refundStmt->close();

$stmt->close();
$conn->close();
?>