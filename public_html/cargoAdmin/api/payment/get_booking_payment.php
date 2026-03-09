<?php
/**
 * GET BOOKING PAYMENT INFORMATION
 * Returns payment details, escrow status, and transaction history for a booking
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error_log.txt');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    require_once __DIR__ . '/../../include/db.php';
} catch (Exception $e) {
    error_log("Payment API - DB Connection Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

if (!isset($conn) || !$conn) {
    error_log("Payment API - DB Connection not established");
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit;
}

// Validate input
if (!isset($_GET['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

$bookingId = intval($_GET['booking_id']);

try {
    // Get payment information
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
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No payment found for this booking'
        ]);
        exit;
    }
    
    $payment = $result->fetch_assoc();
    
    // Get escrow details if exists
    $escrowStmt = $conn->prepare("
        SELECT 
            id AS escrow_id,
            amount AS escrow_amount,
            status AS escrow_status,
            held_at,
            released_at,
            release_reason,
            refund_reason
        FROM escrow
        WHERE booking_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    
    $escrowStmt->bind_param("i", $bookingId);
    $escrowStmt->execute();
    $escrowResult = $escrowStmt->get_result();
    
    if ($escrowResult->num_rows > 0) {
        $escrowData = $escrowResult->fetch_assoc();
        $payment['escrow_details'] = $escrowData;
    }
    
    // Get transaction timeline
    $timelineStmt = $conn->prepare("
        SELECT 
            transaction_type,
            amount,
            description,
            created_at,
            metadata
        FROM payment_transactions
        WHERE booking_id = ?
        ORDER BY created_at ASC
    ");
    
    $timelineStmt->bind_param("i", $bookingId);
    $timelineStmt->execute();
    $timelineResult = $timelineStmt->get_result();
    
    $timeline = [];
    while ($row = $timelineResult->fetch_assoc()) {
        if ($row['metadata']) {
            $row['metadata'] = json_decode($row['metadata'], true);
        }
        $timeline[] = $row;
    }
    
    $payment['transaction_timeline'] = $timeline;
    
    echo json_encode([
        'success' => true,
        'payment' => $payment
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>