<?php
/**
 * ============================================================================
 * HOLD ESCROW API - FIXED
 * Put escrow on hold for disputes or investigation
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

if (!isset($_POST['reason']) || empty(trim($_POST['reason']))) {
    echo json_encode(['success' => false, 'message' => 'Reason for hold is required']);
    exit;
}

if (!isset($_POST['details']) || empty(trim($_POST['details']))) {
    echo json_encode(['success' => false, 'message' => 'Hold details are required']);
    exit;
}

$bookingId = intval($_POST['booking_id']);
$reason = mysqli_real_escape_string($conn, trim($_POST['reason']));
$details = mysqli_real_escape_string($conn, trim($_POST['details']));
$adminId = $_SESSION['admin_id'];

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
    
    // Validate current escrow status
    if ($booking['escrow_status'] !== 'held') {
        throw new Exception('Can only put escrow on hold from "held" status. Current: ' . $booking['escrow_status']);
    }
    
    // Check if already on hold
    if (!empty($booking['escrow_hold_reason'])) {
        throw new Exception('This escrow is already on hold');
    }
    
    // Update escrow - use escrow_hold_reason instead of changing status
    $updateQuery = "
        UPDATE bookings 
        SET 
            escrow_hold_reason = ?,
            escrow_hold_details = ?
        WHERE id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "ssi", $reason, $details, $bookingId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update escrow status: ' . mysqli_error($conn));
    }
    
    // Log the hold action if escrow_logs table exists
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
            ) VALUES (?, 'hold', 'held', 'on_hold', ?, ?, NOW())
        ";
        
        $logNotes = "Reason: $reason - $details";
        $stmt = mysqli_prepare($conn, $logQuery);
        mysqli_stmt_bind_param($stmt, "iis", $bookingId, $adminId, $logNotes);
        mysqli_stmt_execute($stmt);
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // TODO: Send notifications to both renter and owner
    
    echo json_encode([
        'success' => true,
        'message' => 'Escrow put on hold successfully. Both parties will be notified.',
        'booking_id' => $bookingId,
        'reason' => $reason
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