<?php
/**
 * ============================================================================
 * GET REFUND HISTORY - For renter's refund history screen
 * ============================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../../include/db.php';

try {
    // ============================================================================
    // GET USER ID
    // ============================================================================
    
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user ID'
        ]);
        exit;
    }

// ============================================================================
    // GET REFUND HISTORY
    // ============================================================================

$query = "
    SELECT 
        r.id,
        r.refund_id,
        r.booking_id,
        r.payment_id,
        r.refund_amount,
        r.original_amount,
        r.deduction_amount,
        r.deduction_reason,
        r.refund_method,
        r.account_number,
        r.account_name,
        r.bank_name,
        r.refund_reason,
        r.reason_details,
        r.status,
        r.rejection_reason,
        r.completion_reference,
        r.created_at,
        r.processed_at,
        
        -- Booking info
        CONCAT('#BK-', LPAD(b.id, 4, '0')) AS booking_reference,
        b.pickup_date,
        b.return_date,
        b.status AS booking_status,
        b.total_amount AS booking_total,
        
        -- Car info with proper fallback
        COALESCE(c.brand, m.brand, 'Unknown') AS car_brand,
        COALESCE(c.model, m.model, 'Vehicle') AS car_model,
        COALESCE(c.car_year, m.motorcycle_year, '') AS car_year,
        COALESCE(c.image, m.image, '') AS car_image,
        CONCAT(
            COALESCE(c.brand, m.brand, 'Unknown'), ' ',
            COALESCE(c.model, m.model, 'Vehicle'), ' ',
            COALESCE(c.car_year, m.motorcycle_year, '')
        ) AS car_full_name,
        b.vehicle_type,
        
        -- Payment info
        p.payment_method AS original_payment_method,
        p.payment_reference AS original_payment_reference,
        p.amount AS payment_amount,
        
        -- Calculate days since request
        DATEDIFF(NOW(), r.created_at) AS days_pending
        
    FROM refunds r
    INNER JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN payments p ON r.payment_id = p.id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Query preparation failed: ' . $conn->error
        ]);
        exit;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

$refunds = [];

while ($row = $result->fetch_assoc()) {
    // Calculate final refund amount
    $refund_amount = floatval($row['refund_amount'] ?? 0);
    $deduction_amount = floatval($row['deduction_amount'] ?? 0);
    $final_amount = $refund_amount - $deduction_amount;
    
    // Format dates with null checks
    $created_at_formatted = $row['created_at'] ? date('M d, Y h:i A', strtotime($row['created_at'])) : '';
    $processed_at_formatted = $row['processed_at'] ? date('M d, Y h:i A', strtotime($row['processed_at'])) : null;
    
    // Build car image URL
    $car_image = $row['car_image'] ?? '';
    if ($car_image && strpos($car_image, 'http') !== 0) {
        // Load config if not already loaded
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../../include/config.php';
        }
        $car_image = BASE_URL . '/' . $car_image;
    }
    
    $refunds[] = [
        // IDs
        'id' => intval($row['id']),
        'refund_id' => $row['refund_id'] ?? '',
        'booking_id' => intval($row['booking_id']),
        'payment_id' => intval($row['payment_id'] ?? 0),
        
        // Financial
        'refund_amount' => $refund_amount,
        'original_amount' => floatval($row['original_amount'] ?? 0),
        'deduction_amount' => $deduction_amount,
        'deduction_reason' => $row['deduction_reason'] ?? null,
        'final_refund_amount' => $final_amount,
        'booking_total' => floatval($row['booking_total'] ?? 0),
        
        // Refund method
        'refund_method' => $row['refund_method'] ?? '',
        'account_number' => $row['account_number'] ?? '',
        'account_name' => $row['account_name'] ?? '',
        'bank_name' => $row['bank_name'] ?? null,
        
        // Reason
        'refund_reason' => $row['refund_reason'] ?? '',
        'reason_details' => $row['reason_details'] ?? null,
        
        // Status
        'status' => $row['status'] ?? 'pending',
        'rejection_reason' => $row['rejection_reason'] ?? null,
        
        // References
        'completion_reference' => $row['completion_reference'] ?? null,
        
        // Timestamps
        'created_at' => $row['created_at'] ?? null,
        'created_at_formatted' => $created_at_formatted,
        'processed_at' => $row['processed_at'] ?? null,
        'processed_at_formatted' => $processed_at_formatted,
        
        // Metrics
        'days_pending' => intval($row['days_pending'] ?? 0),
        
        // Booking info
        'booking_reference' => $row['booking_reference'] ?? '',
        'booking_status' => $row['booking_status'] ?? '',
        'pickup_date' => $row['pickup_date'] ?? null,
        'return_date' => $row['return_date'] ?? null,
        'vehicle_type' => $row['vehicle_type'] ?? 'car',
        
        // Car info
        'car_brand' => $row['car_brand'] ?? 'Unknown',
        'car_model' => $row['car_model'] ?? 'Vehicle',
        'car_year' => $row['car_year'] ?? '',
        'car_full_name' => $row['car_full_name'] ?? 'Unknown Vehicle',
        'car_image' => $car_image,
        
        // Payment info
        'original_payment_method' => $row['original_payment_method'] ?? '',
        'original_payment_reference' => $row['original_payment_reference'] ?? '',
        'payment_amount' => floatval($row['payment_amount'] ?? 0)
    ];
}

// ============================================================================
// GET STATISTICS
// ============================================================================

$stats_query = "
    SELECT 
        COUNT(*) AS total_refunds,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
        COALESCE(SUM(CASE WHEN status IN ('approved', 'processing', 'completed') THEN (refund_amount - COALESCE(deduction_amount, 0)) ELSE 0 END), 0) AS total_refunded
    FROM refunds
    WHERE user_id = ?
";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();

// Format statistics
$statistics = [
    'total_refunds' => intval($stats['total_refunds'] ?? 0),
    'pending_count' => intval($stats['pending_count'] ?? 0),
    'approved_count' => intval($stats['approved_count'] ?? 0),
    'processing_count' => intval($stats['processing_count'] ?? 0),
    'completed_count' => intval($stats['completed_count'] ?? 0),
    'rejected_count' => intval($stats['rejected_count'] ?? 0),
    'total_refunded' => floatval($stats['total_refunded'] ?? 0),
    'avg_processing_days' => 0.0  // Add this field for Flutter compatibility
];

// ============================================================================
// SUCCESS RESPONSE
// ============================================================================

    $conn->close();
    
    echo json_encode([
        'success' => true,
        'refunds' => $refunds,
        'statistics' => $statistics,
        'count' => count($refunds)
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'line' => $e->getLine()
    ]);
}