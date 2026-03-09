<?php
/**
 * Get Security Deposit Status API
 * Returns security deposit information for a booking
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../../include/db.php";

if (!isset($_GET['booking_id']) && !isset($_POST['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'booking_id is required'
    ]);
    exit;
}

$bookingId = intval($_GET['booking_id'] ?? $_POST['booking_id']);

// Fetch booking security deposit details
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.user_id,
        b.total_amount,
        b.security_deposit_amount,
        b.security_deposit_status,
        b.security_deposit_held_at,
        b.security_deposit_refunded_at,
        b.security_deposit_refund_amount,
        b.security_deposit_deductions,
        b.security_deposit_deduction_reason,
        b.security_deposit_refund_reference,
        b.payment_status,
        b.status
    FROM bookings b
    WHERE b.id = ?
");
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found'
    ]);
    exit;
}

$booking = $result->fetch_assoc();

// Fetch deduction details if any
$deductions = [];
$stmt = $conn->prepare("
    SELECT 
        id,
        deduction_type,
        amount,
        description,
        evidence_image,
        created_at
    FROM security_deposit_deductions
    WHERE booking_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$deductionResult = $stmt->get_result();

while ($row = $deductionResult->fetch_assoc()) {
    $deductions[] = [
        'id' => $row['id'],
        'type' => $row['deduction_type'],
        'amount' => floatval($row['amount']),
        'description' => $row['description'],
        'evidence_image' => $row['evidence_image'],
        'created_at' => $row['created_at']
    ];
}

echo json_encode([
    'success' => true,
    'data' => [
        'booking_id' => $booking['id'],
        'total_amount' => floatval($booking['total_amount']),
        'security_deposit' => floatval($booking['security_deposit_amount']),
        'deposit_status' => $booking['security_deposit_status'],
        'held_at' => $booking['security_deposit_held_at'],
        'refunded_at' => $booking['security_deposit_refunded_at'],
        'refund_amount' => floatval($booking['security_deposit_refund_amount']),
        'total_deductions' => floatval($booking['security_deposit_deductions']),
        'deduction_reason' => $booking['security_deposit_deduction_reason'],
        'refund_reference' => $booking['security_deposit_refund_reference'],
        'payment_status' => $booking['payment_status'],
        'booking_status' => $booking['status'],
        'deductions' => $deductions,
        'remaining_deposit' => floatval($booking['security_deposit_amount']) - floatval($booking['security_deposit_deductions'])
    ]
]);

$conn->close();
