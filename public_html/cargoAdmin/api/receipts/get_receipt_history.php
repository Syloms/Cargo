<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../include/db.php';

if (!isset($_GET['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Booking ID required']);
    exit;
}

$bookingId = intval($_GET['booking_id']);

try {
    // Check if receipt already exists
    $stmt = $conn->prepare("
        SELECT * FROM receipts WHERE booking_id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Receipt exists, return it
        $receipt = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'receipt' => getReceiptDetails($conn, $bookingId, $receipt)
        ]);
    } else {
        // Generate new receipt
        require_once __DIR__ . '/generate_receipt.php';
        $generated = generateReceipt($bookingId, $conn);
        
        if (isset($generated['success']) && $generated['success']) {
            // Fetch the newly created receipt
            $stmt = $conn->prepare("SELECT * FROM receipts WHERE booking_id = ? LIMIT 1");
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $receipt = $stmt->get_result()->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'receipt' => getReceiptDetails($conn, $bookingId, $receipt),
                'generated' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $generated['error'] ?? 'Failed to generate receipt'
            ]);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function getReceiptDetails($conn, $bookingId, $receipt) {
    // Get full booking details
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            c.brand, c.model, c.car_year, c.plate_number,
            u1.fullname AS renter_name,
            u1.email AS renter_email,
            u1.phone AS renter_contact,
            p.payment_method,
            p.payment_reference,
            p.payment_status,
            p.verified_at
        FROM bookings b
        LEFT JOIN cars c ON b.car_id = c.id
        LEFT JOIN users u1 ON b.user_id = u1.id
        LEFT JOIN payments p ON p.booking_id = b.id
        WHERE b.id = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    // Calculate rental duration
    $pickup = strtotime($booking['pickup_date']);
    $return = strtotime($booking['return_date']);
    $days = max(1, ceil(($return - $pickup) / 86400));
    
    return [
        'receipt_no' => $receipt['receipt_no'],
        'receipt_url' => $receipt['receipt_url'],
        'receipt_path' => $receipt['receipt_path'],
        'status' => $receipt['status'],
        'generated_at' => $receipt['generated_at'],
        'booking_id' => $bookingId,
        'amount' => floatval($booking['total_amount']),
        'payment_method' => $booking['payment_method'],
        'payment_reference' => $booking['payment_reference'],
        'payment_status' => $booking['payment_status'],
        'created_at' => $booking['created_at'],
        
        // Renter info
        'renter_name' => $booking['renter_name'],
        'renter_email' => $booking['renter_email'],
        'renter_contact' => $booking['renter_contact'],
        
        // Car info
        'car_name' => $booking['brand'] . ' ' . $booking['model'] . ' ' . $booking['car_year'],
        'plate_number' => $booking['plate_number'],
        
        // Rental details
        'pickup_date' => $booking['pickup_date'],
        'return_date' => $booking['return_date'],
        'pickup_time' => $booking['pickup_time'],
        'return_time' => $booking['return_time'],
        'duration' => $days . ' day' . ($days > 1 ? 's' : '')
    ];
}

$conn->close();