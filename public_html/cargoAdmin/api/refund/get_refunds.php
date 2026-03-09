<?php
/**
 * =====================================================
 * GET REFUND DETAILS
 * Fetches detailed information for a single refund
 * =====================================================
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../include/db.php';

// Validate input
if (!isset($_GET['refund_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Refund ID is required'
    ]);
    exit;
}

$refundId = intval($_GET['refund_id']);

try {
    // Get refund details with all related information
    $sql = "
        SELECT 
            -- Refund information
            r.*,
            
            -- Booking details
            b.total_amount AS booking_total,
            b.platform_fee,
            b.owner_payout,
            b.status AS booking_status,
            b.pickup_date,
            b.return_date,
            b.pickup_time,
            b.return_time,
            b.vehicle_type,
            b.payment_status,
            b.escrow_status,
            b.payout_status,
            b.created_at AS booking_created_at,
            
            -- Payment details
            p.payment_method,
            p.payment_reference,
            p.amount AS payment_amount,
            p.payment_status AS payment_verification_status,
            p.created_at AS payment_created_at,
            
            -- Renter details
            u.fullname AS renter_name,
            u.email AS renter_email,
            u.phone AS renter_phone,
            u.address AS renter_address,
            u.gcash_number AS renter_gcash,
            
            -- Owner details
            owner.id AS owner_id,
            owner.fullname AS owner_name,
            owner.email AS owner_email,
            owner.phone AS owner_phone,
            
            -- Car/Motorcycle details
            COALESCE(c.brand, m.brand) AS car_brand,
            COALESCE(c.model, m.model) AS car_model,
            COALESCE(c.car_year, m.motorcycle_year) AS car_year,
            COALESCE(c.plate_number, m.plate_number) AS plate_number,
            COALESCE(c.image, m.image) AS car_image,
            COALESCE(c.price_per_day, m.price_per_day) AS price_per_day,
            
            -- Admin who processed
            a.fullname AS processed_by_name,
            a.email AS processed_by_email
            
        FROM refunds r
        INNER JOIN bookings b ON r.booking_id = b.id
        LEFT JOIN payments p ON r.payment_id = p.id
        INNER JOIN users u ON r.user_id = u.id
        LEFT JOIN users owner ON b.owner_id = owner.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        LEFT JOIN admin a ON r.processed_by = a.id
        
        WHERE r.id = ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $refundId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Refund not found'
        ]);
        exit;
    }
    
    $row = $result->fetch_assoc();
    
    // Format car details
    $carName = trim($row['car_brand'] . ' ' . $row['car_model'] . ' ' . $row['car_year']);
    $carImage = $row['car_image'] ?? '';
    if (!empty($carImage) && strpos($carImage, 'http') !== 0) {
        // Load config if not already loaded
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../../include/config.php';
        }
        $carImage = BASE_URL . '/' . $carImage;
    }
    
    // Calculate rental duration
    $pickupDate = strtotime($row['pickup_date']);
    $returnDate = strtotime($row['return_date']);
    $rentalDays = max(1, ceil(($returnDate - $pickupDate) / 86400));
    
    // Calculate refund percentage
    $refundPercentage = $row['booking_total'] > 0 
        ? round(($row['refund_amount'] / $row['booking_total']) * 100, 1)
        : 100;
    
    // Get refund transaction history
    $historySql = "
        SELECT 
            transaction_type,
            amount,
            description,
            created_at,
            metadata
        FROM payment_transactions
        WHERE booking_id = ? AND transaction_type LIKE '%refund%'
        ORDER BY created_at DESC
    ";
    
    $historyStmt = $conn->prepare($historySql);
    $historyStmt->bind_param("i", $row['booking_id']);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    
    $transactionHistory = [];
    while ($historyRow = $historyResult->fetch_assoc()) {
        $metadata = $historyRow['metadata'] ? json_decode($historyRow['metadata'], true) : null;
        
        $transactionHistory[] = [
            'type' => $historyRow['transaction_type'],
            'amount' => floatval($historyRow['amount']),
            'description' => $historyRow['description'],
            'created_at' => $historyRow['created_at'],
            'created_at_formatted' => date('M d, Y h:i A', strtotime($historyRow['created_at'])),
            'metadata' => $metadata
        ];
    }
    
    // Build detailed response
    $refund = [
        // Refund information
        'id' => intval($row['id']),
        'booking_id' => intval($row['booking_id']),
        'booking_id_formatted' => '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT),
        'payment_id' => intval($row['payment_id']),
        'user_id' => intval($row['user_id']),
        
        // Financial details
        'refund_amount' => floatval($row['refund_amount']),
        'refund_amount_formatted' => '₱' . number_format($row['refund_amount'], 2),
        'booking_total' => floatval($row['booking_total']),
        'booking_total_formatted' => '₱' . number_format($row['booking_total'], 2),
        'platform_fee' => floatval($row['platform_fee']),
        'owner_payout' => floatval($row['owner_payout']),
        'refund_percentage' => $refundPercentage,
        'is_full_refund' => $refundPercentage >= 99,
        'is_partial_refund' => $refundPercentage < 99,
        
        // Refund method details
        'refund_method' => $row['refund_method'],
        'account_number' => $row['account_number'],
        'account_name' => $row['account_name'],
        
        // Original payment info
        'original_payment_method' => $row['original_payment_method'],
        'original_payment_reference' => $row['original_payment_reference'],
        'payment_amount' => floatval($row['payment_amount']),
        
        // Refund reason
        'refund_reason' => $row['refund_reason'],
        'reason_details' => $row['reason_details'],
        
        // Status information
        'status' => $row['status'],
        'status_label' => ucfirst($row['status']),
        'status_color' => [
            'pending' => 'warning',
            'completed' => 'success',
            'rejected' => 'danger'
        ][$row['status']],
        
        // Processing details
        'processed_by' => $row['processed_by'],
        'processed_by_name' => $row['processed_by_name'],
        'processed_by_email' => $row['processed_by_email'],
        'processed_at' => $row['processed_at'],
        'processed_at_formatted' => $row['processed_at'] 
            ? date('M d, Y h:i A', strtotime($row['processed_at']))
            : null,
        'completion_reference' => $row['completion_reference'],
        'rejection_reason' => $row['rejection_reason'],
        
        // Timestamps
        'created_at' => $row['created_at'],
        'created_at_formatted' => date('M d, Y h:i A', strtotime($row['created_at'])),
        'days_since_request' => ceil((time() - strtotime($row['created_at'])) / 86400),
        'is_urgent' => (
            $row['status'] === 'pending' && 
            (time() - strtotime($row['created_at'])) > (3 * 86400)
        ),
        
        // Renter information
        'renter' => [
            'id' => intval($row['user_id']),
            'name' => $row['renter_name'],
            'email' => $row['renter_email'],
            'phone' => $row['renter_phone'],
            'address' => $row['renter_address'],
            'gcash_number' => $row['renter_gcash']
        ],
        
        // Owner information
        'owner' => [
            'id' => intval($row['owner_id']),
            'name' => $row['owner_name'],
            'email' => $row['owner_email'],
            'phone' => $row['owner_phone']
        ],
        
        // Booking information
        'booking' => [
            'id' => intval($row['booking_id']),
            'status' => $row['booking_status'],
            'payment_status' => $row['payment_status'],
            'escrow_status' => $row['escrow_status'],
            'payout_status' => $row['payout_status'],
            'pickup_date' => $row['pickup_date'],
            'pickup_date_formatted' => date('M d, Y', strtotime($row['pickup_date'])),
            'return_date' => $row['return_date'],
            'return_date_formatted' => date('M d, Y', strtotime($row['return_date'])),
            'pickup_time' => $row['pickup_time'],
            'return_time' => $row['return_time'],
            'rental_days' => $rentalDays,
            'vehicle_type' => $row['vehicle_type'],
            'created_at' => $row['booking_created_at'],
            'created_at_formatted' => date('M d, Y h:i A', strtotime($row['booking_created_at']))
        ],
        
        // Vehicle information
        'vehicle' => [
            'name' => $carName,
            'brand' => $row['car_brand'],
            'model' => $row['car_model'],
            'year' => $row['car_year'],
            'plate_number' => $row['plate_number'],
            'image' => $carImage,
            'price_per_day' => floatval($row['price_per_day']),
            'type' => $row['vehicle_type']
        ],
        
        // Transaction history
        'transaction_history' => $transactionHistory,
        
        // Actions available
        'can_approve' => $row['status'] === 'pending',
        'can_reject' => $row['status'] === 'pending',
        'is_processed' => in_array($row['status'], ['completed', 'rejected'])
    ];
    
    echo json_encode([
        'success' => true,
        'refund' => $refund
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching refund details: ' . $e->getMessage()
    ]);
}

$conn->close();