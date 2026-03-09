<?php
/**
 * Waive Late Fee
 * Allows admin to waive late fees for special circumstances
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
$reason = $_POST['reason'] ?? '';

if (!$bookingId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

if (empty($reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Reason for waiving late fee is required'
    ]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Get booking details
    $bookingQuery = "SELECT b.*, u.fullname as renter_name, u.email as renter_email
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
    
    $originalLateFee = $booking['late_fee_amount'];
    
    // Check if late fee was already paid
    if ($booking['late_fee_charged'] == 1) {
        throw new Exception("Cannot waive late fee - payment has already been collected");
    }
    
    // Waive the late fee
    $updateQuery = "UPDATE bookings 
                    SET late_fee_amount = 0,
                        late_fee_waived = 1,
                        late_fee_waived_by = ?,
                        late_fee_waived_at = NOW(),
                        late_fee_waived_reason = ?,
                        late_fee_charged = 1
                    WHERE id = ?";
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "isi", $adminId, $reason, $bookingId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to waive late fee");
    }
    
    // Log the action
    $logNotes = "Late fee waived (Original: ₱" . number_format($originalLateFee, 2) . ") - Reason: " . $reason;
    $logQuery = "INSERT INTO admin_action_logs 
                 (admin_id, action_type, booking_id, notes, created_at)
                 VALUES (?, 'waive_late_fee', ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $logQuery);
    mysqli_stmt_bind_param($stmt, "iis", $adminId, $bookingId, $logNotes);
    mysqli_stmt_execute($stmt);
    
    // Create notification for renter
    $notificationTitle = "Late Fee Waived 🎉";
    $notificationMessage = "Good news! The late fee of ₱" . number_format($originalLateFee, 2) . 
                          " for booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . 
                          " has been waived. Thank you for your patience.";
    
    $notifQuery = "INSERT INTO notifications 
                   (user_id, title, message, created_at)
                   VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $notifQuery);
    mysqli_stmt_bind_param($stmt, "iss", $booking['user_id'], $notificationTitle, $notificationMessage);
    mysqli_stmt_execute($stmt);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Late fee of ₱' . number_format($originalLateFee, 2) . ' has been waived successfully.'
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
