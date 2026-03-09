<?php
/**
 * Force Complete Overdue Booking
 * Allows admin to manually complete an overdue booking
 * Used for offline payments or special circumstances
 */

// Start output buffering to catch any errors
ob_start();

session_start();
require_once '../../include/db.php';

// Clear any output that might have been generated
ob_end_clean();

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Admin access required. Please login.'
    ]);
    exit;
}

$adminId = $_SESSION['admin_id'];
$bookingId = $_POST['booking_id'] ?? null;
$notes = $_POST['notes'] ?? 'Manually completed by admin';

if (!$bookingId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get booking details
    $bookingQuery = "SELECT b.*, u.fullname as renter_name, u.email as renter_email, u.fcm_token
                     FROM bookings b
                     JOIN users u ON b.user_id = u.id
                     WHERE b.id = ?";
    $stmt = mysqli_prepare($conn, $bookingQuery);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Check if already completed
    if ($booking['status'] === 'completed') {
        throw new Exception("Booking is already completed");
    }
    
    // Update booking status to completed
    $updateBooking = "UPDATE bookings 
                      SET status = 'completed',
                          late_fee_charged = 1,
                          late_fee_payment_status = 'paid',
                          overdue_status = 'completed',
                          completed_at = NOW()
                      WHERE id = ?";
    $stmt = mysqli_prepare($conn, $updateBooking);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update booking status");
    }
    
    // Log the action
    $logQuery = "INSERT INTO admin_action_logs 
                 (admin_id, action_type, booking_id, notes, created_at)
                 VALUES (?, 'force_complete_overdue', ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $logQuery);
    mysqli_stmt_bind_param($stmt, "iis", $adminId, $bookingId, $notes);
    mysqli_stmt_execute($stmt);
    
    // Create notification for renter
    $notificationTitle = "Booking Completed ✅";
    $notificationMessage = "Your booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . " has been completed. Late fee: ₱" . number_format($booking['late_fee_amount'], 2);
    
    $notifQuery = "INSERT INTO notifications 
                   (user_id, title, message, created_at)
                   VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $notifQuery);
    mysqli_stmt_bind_param($stmt, "iss", $booking['user_id'], $notificationTitle, $notificationMessage);
    mysqli_stmt_execute($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking completed successfully. Late fee of ₱' . number_format($booking['late_fee_amount'], 2) . ' has been marked as collected.'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
