<?php
require_once '../../include/db.php';

// Test with your booking ID
$bookingId = 47;

echo "<h2>Testing get_booking_payment.php for Booking #$bookingId</h2>";

$stmt = $conn->prepare("
    SELECT 
        p.id AS payment_id,
        p.amount,
        p.payment_method,
        p.payment_reference,
        p.payment_status,
        p.created_at,
        p.verified_at,
        p.verified_by,
        
        -- Escrow information
        b.escrow_status,
        b.escrow_held_at,
        b.escrow_released_at,
        b.platform_fee,
        b.owner_payout,
        b.return_date AS expected_release_date,
        
        -- Refund information
        b.refund_status,
        b.refund_requested,
        b.refund_amount,
        
        -- Transaction details
        b.id AS booking_id,
        b.total_amount AS booking_amount,
        b.status AS booking_status
        
    FROM payments p
    INNER JOIN bookings b ON p.booking_id = b.id
    WHERE b.id = ?
    ORDER BY p.created_at DESC
    LIMIT 1
");

$stmt->bind_param("i", $bookingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo "<h3>✅ Payment Data Found</h3>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    
    echo "<h3>Refund Status Details:</h3>";
    echo "<ul>";
    echo "<li><strong>refund_status:</strong> " . ($data['refund_status'] ?? 'NULL') . "</li>";
    echo "<li><strong>refund_requested:</strong> " . ($data['refund_requested'] ?? 'NULL') . "</li>";
    echo "<li><strong>refund_amount:</strong> " . ($data['refund_amount'] ?? 'NULL') . "</li>";
    echo "</ul>";
    
    echo "<h3>JSON Response:</h3>";
    echo "<pre>";
    echo json_encode([
        'success' => true,
        'payment' => $data
    ], JSON_PRETTY_PRINT);
    echo "</pre>";
} else {
    echo "<h3>❌ No Payment Found</h3>";
}

// Also check bookings table directly
echo "<hr><h3>Direct Booking Check:</h3>";
$stmt2 = $conn->prepare("SELECT id, status, refund_status, refund_requested, refund_amount FROM bookings WHERE id = ?");
$stmt2->bind_param("i", $bookingId);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2->num_rows > 0) {
    echo "<pre>";
    print_r($result2->fetch_assoc());
    echo "</pre>";
}
?>
