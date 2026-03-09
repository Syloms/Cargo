<?php
/**
 * Get Renter Late Fee Status API
 * Check if a renter has pending or paid late fee for a booking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit();
}

$bookingId = $_GET['booking_id'] ?? null;
$userId = $_GET['user_id'] ?? null;

if (!$bookingId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Get booking late fee payment status
    $bookingQuery = "SELECT b.id, b.late_fee_payment_status, b.late_fee_amount, 
                     b.late_fee_charged, b.hours_overdue, b.payment_status,
                     CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name
                     FROM bookings b
                     LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
                     LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
                     WHERE b.id = ? AND b.user_id = ?";
    
    $stmt = mysqli_prepare($conn, $bookingQuery);
    mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Get latest late fee payment if exists
    $paymentQuery = "SELECT * FROM late_fee_payments 
                     WHERE booking_id = ? AND user_id = ?
                     ORDER BY created_at DESC LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $paymentQuery);
    mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($result);
    
    $response = [
        'success' => true,
        'booking' => [
            'id' => $booking['id'],
            'vehicle_name' => $booking['vehicle_name'],
            'late_fee_amount' => $booking['late_fee_amount'],
            'late_fee_payment_status' => $booking['late_fee_payment_status'],
            'late_fee_charged' => $booking['late_fee_charged'],
            'hours_overdue' => $booking['hours_overdue'],
            'payment_status' => $booking['payment_status']
        ]
    ];
    
    if ($payment) {
        $response['late_fee_payment'] = [
            'id' => $payment['id'],
            'total_amount' => $payment['total_amount'],
            'late_fee_amount' => $payment['late_fee_amount'],
            'rental_amount' => $payment['rental_amount'],
            'payment_status' => $payment['payment_status'],
            'payment_reference' => $payment['payment_reference'],
            'is_rental_paid' => $payment['is_rental_paid'],
            'submitted_at' => $payment['created_at'],
            'verified_at' => $payment['verified_at']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
