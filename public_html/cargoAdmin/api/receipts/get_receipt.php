<?php
/**
 * Get Receipt Details by Booking ID
 * Returns receipt information for a specific booking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_GET['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Booking ID required']);
    exit;
}

$bookingId = intval($_GET['booking_id']);

if ($bookingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

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
        // Check if booking exists and payment is verified
        $bookingStmt = $conn->prepare("
            SELECT b.*, b.payment_status, b.verified_at
            FROM bookings b
            WHERE b.id = ?
        ");
        $bookingStmt->bind_param("i", $bookingId);
        $bookingStmt->execute();
        $bookingResult = $bookingStmt->get_result();
        
        if ($bookingResult->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Booking not found'
            ]);
            exit;
        }
        
        $booking = $bookingResult->fetch_assoc();
        
        // Check if payment is verified - payment_status can be 'paid', 'escrowed', 'released', or check verified_at
        $isPaymentVerified = (
            $booking['payment_status'] === 'paid' || 
            $booking['payment_status'] === 'escrowed' || 
            $booking['payment_status'] === 'released' ||
            !empty($booking['verified_at'])
        );
        
        if (!$isPaymentVerified) {
            echo json_encode([
                'success' => false,
                'message' => 'Receipt not available. Payment must be verified first.',
                'payment_status' => $booking['payment_status'],
                'verified_at' => $booking['verified_at']
            ]);
            exit;
        }
        
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
    // Get full booking details with vehicle information
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            CASE 
                WHEN b.vehicle_type = 'car' THEN c.brand
                WHEN b.vehicle_type = 'motorcycle' THEN m.brand
                ELSE NULL
            END as brand,
            CASE 
                WHEN b.vehicle_type = 'car' THEN c.model
                WHEN b.vehicle_type = 'motorcycle' THEN m.model
                ELSE NULL
            END as model,
            CASE 
                WHEN b.vehicle_type = 'car' THEN c.car_year
                WHEN b.vehicle_type = 'motorcycle' THEN m.motorcycle_year
                ELSE NULL
            END as vehicle_year,
            CASE 
                WHEN b.vehicle_type = 'car' THEN c.plate_number
                WHEN b.vehicle_type = 'motorcycle' THEN m.plate_number
                ELSE NULL
            END as plate_number,
            u1.fullname AS renter_name,
            u1.email AS renter_email,
            u1.phone AS renter_contact,
            COALESCE(p.payment_method, b.payment_method) as payment_method,
            COALESCE(p.payment_reference, b.gcash_reference) as payment_reference,
            COALESCE(p.payment_status, b.payment_status) as payment_status,
            COALESCE(p.verified_at, b.verified_at) as verified_at
        FROM bookings b
        LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
        LEFT JOIN users u1 ON b.user_id = u1.id
        LEFT JOIN payments p ON p.id = (
            SELECT id FROM payments
            WHERE booking_id = b.id AND payment_status IN ('verified', 'paid', 'escrowed', 'released')
            ORDER BY id DESC LIMIT 1
        )
        WHERE b.id = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        return null;
    }
    
    // Calculate rental duration
    $pickup = strtotime($booking['pickup_date']);
    $return = strtotime($booking['return_date']);
    $days = max(1, ceil(($return - $pickup) / 86400));

    // Break down payment amounts
    $pricePerDay      = floatval($booking['price_per_day'] ?? 0);
    $baseRental       = round($pricePerDay * $days, 2);
    $totalAmount      = floatval($booking['total_amount']);
    $serviceFee       = round($totalAmount - $baseRental, 2);
    $securityDeposit  = floatval($booking['security_deposit_amount'] ?? 0);
    $grandTotal       = round($totalAmount + $securityDeposit, 2);

    // Build vehicle name
    $vehicleName = 'N/A';
    if ($booking['brand'] && $booking['model']) {
        $vehicleName = trim($booking['brand'] . ' ' . $booking['model']);
        if ($booking['vehicle_year']) {
            $vehicleName .= ' ' . $booking['vehicle_year'];
        }
    }

    return [
        'receipt_no' => $receipt['receipt_no'],
        'receipt_url' => $receipt['receipt_url'],
        'receipt_path' => $receipt['receipt_path'],
        'status' => $receipt['status'],
        'generated_at' => $receipt['generated_at'],
        'booking_id' => $bookingId,

        // Payment breakdown
        'daily_rate'       => $pricePerDay > 0 ? $pricePerDay : null,
        'rental_days'      => $days,
        'base_rental'      => $baseRental,
        'service_fee'      => $serviceFee > 0 ? $serviceFee : null,
        'amount'           => $totalAmount,        // base + service fee (total rental cost)
        'security_deposit' => $securityDeposit,
        'grand_total'      => $grandTotal,

        'payment_method'   => $booking['payment_method'] ?? 'N/A',
        'payment_reference'=> $booking['payment_reference'] ?? 'N/A',
        'payment_status'   => $booking['payment_status'] ?? 'N/A',
        'created_at'       => $booking['created_at'],
        'payment_verified_at' => $booking['verified_at'] ?? null,
        'escrow_status'    => $booking['escrow_status'] ?? null,
        'platform_fee'     => isset($booking['platform_fee']) ? floatval($booking['platform_fee']) : null,
        'owner_payout'     => isset($booking['owner_payout']) ? floatval($booking['owner_payout']) : null,

        // Renter info
        'renter_name'    => $booking['renter_name'] ?? 'N/A',
        'renter_email'   => $booking['renter_email'] ?? 'N/A',
        'renter_contact' => $booking['renter_contact'] ?? 'N/A',

        // Vehicle info
        'car_name'     => $vehicleName,
        'plate_number' => $booking['plate_number'] ?? 'N/A',
        'vehicle_type' => $booking['vehicle_type'] ?? 'car',

        // Rental details
        'pickup_date' => $booking['pickup_date'],
        'return_date' => $booking['return_date'],
        'pickup_time' => $booking['pickup_time'] ?? 'N/A',
        'return_time' => $booking['return_time'] ?? 'N/A',
        'duration'    => $days . ' day' . ($days > 1 ? 's' : '')
    ];
}

$conn->close();
