<?php
/**
 * Get Booking Details for Overdue Management
 * Used by View Details and Contact Renter modals
 */

// Start output buffering
ob_start();

session_start();
require_once '../../include/db.php';

// Clear buffer
ob_end_clean();

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Admin access required.'
    ]);
    exit;
}

$bookingId = $_GET['id'] ?? null;

if (!$bookingId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

try {
    // Get complete booking details with all related information
    $query = "SELECT 
        b.id AS booking_id,
        b.user_id AS renter_id,
        b.owner_id,
        b.car_id,
        b.vehicle_type,
        b.total_amount,
        b.pickup_date,
        b.pickup_time,
        b.return_date,
        b.return_time,
        b.status,
        b.payment_status,
        b.overdue_status,
        b.late_fee_amount,
        b.late_fee_charged,
        b.reminder_count,
        b.last_reminder_sent,
        b.created_at,
        
        -- Calculate overdue duration
        FLOOR(TIMESTAMPDIFF(HOUR, CONCAT(b.return_date, ' ', b.return_time), NOW()) / 24) as days_overdue,
        TIMESTAMPDIFF(HOUR, CONCAT(b.return_date, ' ', b.return_time), NOW()) as hours_overdue,
        
        -- Renter information
        u_renter.fullname AS renter_name,
        u_renter.email AS renter_email,
        u_renter.phone AS renter_phone,
        
        -- Owner information
        u_owner.fullname AS owner_name,
        u_owner.email AS owner_email,
        u_owner.phone AS owner_phone,
        
        -- Vehicle information (car or motorcycle)
        COALESCE(c.brand, m.brand) AS vehicle_brand,
        COALESCE(c.model, m.model) AS vehicle_model,
        COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
        COALESCE(c.plate_number, m.plate_number) AS plate_number,
        COALESCE(c.image, m.image) AS vehicle_image,
        CASE 
            WHEN b.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model, ' ', c.car_year)
            WHEN b.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model, ' ', m.motorcycle_year)
            ELSE 'Unknown Vehicle'
        END AS vehicle_name,
        
        -- Payment information
        p.payment_method,
        p.payment_reference,
        p.payment_status AS payment_verification_status,
        
        -- Late fee payment information
        lfp.payment_status AS late_fee_payment_status,
        lfp.payment_reference AS late_fee_payment_reference
        
    FROM bookings b
    JOIN users u_renter ON b.user_id = u_renter.id
    JOIN users u_owner ON b.owner_id = u_owner.id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    LEFT JOIN payments p ON b.id = p.booking_id
    LEFT JOIN late_fee_payments lfp ON b.id = lfp.booking_id
    WHERE b.id = ?
    LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Get action history for this booking
    $historyQuery = "SELECT 
        aal.action_type,
        aal.notes,
        aal.created_at,
        a.fullname as admin_name
        FROM admin_action_logs aal
        LEFT JOIN admin a ON aal.admin_id = a.id
        WHERE aal.booking_id = ?
        ORDER BY aal.created_at DESC
        LIMIT 10";
    
    $historyStmt = mysqli_prepare($conn, $historyQuery);
    mysqli_stmt_bind_param($historyStmt, "i", $bookingId);
    mysqli_stmt_execute($historyStmt);
    $historyResult = mysqli_stmt_get_result($historyStmt);
    
    $history = [];
    while ($row = mysqli_fetch_assoc($historyResult)) {
        $history[] = $row;
    }
    
    $booking['action_history'] = $history;
    
    echo json_encode([
        'success' => true,
        'data' => $booking
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
