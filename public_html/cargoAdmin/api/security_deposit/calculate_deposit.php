<?php
/**
 * Calculate Security Deposit API
 * Returns the security deposit amount for a booking
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../../include/db.php";

// Security Deposit Configuration
define('SECURITY_DEPOSIT_RATE', 0.20); // 20% of total rental amount
define('MINIMUM_DEPOSIT', 500.00); // Minimum ₱500
define('MAXIMUM_DEPOSIT', 10000.00); // Maximum ₱10,000

/**
 * Calculate security deposit based on total rental amount
 */
function calculateSecurityDeposit($totalAmount) {
    $deposit = $totalAmount * SECURITY_DEPOSIT_RATE;
    
    // Apply minimum and maximum limits
    if ($deposit < MINIMUM_DEPOSIT) {
        $deposit = MINIMUM_DEPOSIT;
    } elseif ($deposit > MAXIMUM_DEPOSIT) {
        $deposit = MAXIMUM_DEPOSIT;
    }
    
    return round($deposit, 2);
}

// Handle GET request - calculate deposit for given amount
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['total_amount'])) {
        echo json_encode([
            'success' => false,
            'message' => 'total_amount parameter is required'
        ]);
        exit;
    }
    
    $totalAmount = floatval($_GET['total_amount']);
    $depositAmount = calculateSecurityDeposit($totalAmount);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_amount' => $totalAmount,
            'security_deposit' => $depositAmount,
            'deposit_rate' => SECURITY_DEPOSIT_RATE * 100, // Convert to percentage
            'minimum_deposit' => MINIMUM_DEPOSIT,
            'maximum_deposit' => MAXIMUM_DEPOSIT,
            'grand_total' => $totalAmount + $depositAmount
        ]
    ]);
    exit;
}

// Handle POST request - get deposit for specific booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['booking_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'booking_id is required'
        ]);
        exit;
    }
    
    $bookingId = intval($_POST['booking_id']);
    
    // Fetch booking details
    $stmt = $conn->prepare("
        SELECT 
            id,
            total_amount,
            security_deposit_amount,
            security_deposit_status,
            payment_status
        FROM bookings
        WHERE id = ?
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
    
    // Calculate deposit if not already set
    $depositAmount = $booking['security_deposit_amount'];
    if ($depositAmount == 0) {
        $depositAmount = calculateSecurityDeposit($booking['total_amount']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'booking_id' => $booking['id'],
            'total_amount' => floatval($booking['total_amount']),
            'security_deposit' => floatval($depositAmount),
            'security_deposit_status' => $booking['security_deposit_status'],
            'payment_status' => $booking['payment_status'],
            'grand_total' => floatval($booking['total_amount']) + floatval($depositAmount)
        ]
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
]);
