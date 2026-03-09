<?php
/**
 * ============================================================================
 * RESUME ESCROW API - FIXED
 * Resume escrow from on_hold status back to held
 * ============================================================================
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../include/db.php';

// Validate input
if (!isset($_POST['booking_id']) || empty($_POST['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}

$bookingId = intval($_POST['booking_id']);
$adminId = $_SESSION['admin_id'];
$notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, trim($_POST['notes'])) : 'Issue resolved, escrow resumed';

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get booking details
    $bookingQuery = "
        SELECT 
            b.*,
            u_renter.fullname AS renter_name,
            u_renter.email AS renter_email,
            u_owner.fullname AS owner_name,
            u_owner.email AS owner_email
        FROM bookings b
        LEFT JOIN users u_renter ON b.user_id = u_renter.id
        LEFT JOIN users u_owner ON b.owner_id = u_owner.id
        WHERE b.id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $bookingQuery);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Validate current escrow status - check if it has hold reason
    if (empty($booking['escrow_hold_reason'])) {
        throw new Exception('This escrow is not on hold');
    }
    
    if ($booking['escrow_status'] !== 'held') {
        throw new Exception('Can only resume escrow with "held" status. Current: ' . $booking['escrow_status']);
    }
    
    // Update escrow status - clear hold reason
    $updateQuery = "
        UPDATE bookings 
        SET 
            escrow_hold_reason = NULL,
            escrow_hold_details = NULL
        WHERE id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update escrow status: ' . mysqli_error($conn));
    }
    
    // Log the resume action if escrow_logs table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'escrow_logs'");
    if (mysqli_num_rows($checkTable) > 0) {
        $logQuery = "
            INSERT INTO escrow_logs (
                booking_id,
                action,
                previous_status,
                new_status,
                admin_id,
                notes,
                created_at
            ) VALUES (?, 'resume', 'on_hold', 'held', ?, ?, NOW())
        ";
        
        $stmt = mysqli_prepare($conn, $logQuery);
        mysqli_stmt_bind_param($stmt, "iis", $bookingId, $adminId, $notes);
        mysqli_stmt_execute($stmt);
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // TODO: Send notifications to both renter and owner
    
    echo json_encode([
        'success' => true,
        'message' => 'Escrow resumed successfully. Normal processing will continue.',
        'booking_id' => $bookingId
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>