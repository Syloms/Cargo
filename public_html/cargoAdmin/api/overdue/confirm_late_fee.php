<?php
/**
 * Confirm Late Fee
 * Locks the late fee amount and notifies the renter
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
$lateFeeAmount = $_POST['late_fee_amount'] ?? null;
$notes = $_POST['notes'] ?? '';

if (!$bookingId || !$lateFeeAmount) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID and late fee amount are required'
    ]);
    exit;
}

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
    
    // Update booking with confirmed late fee
    $updateQuery = "UPDATE bookings 
                    SET late_fee_amount = ?,
                        late_fee_confirmed = 1,
                        late_fee_confirmed_at = NOW(),
                        late_fee_confirmed_by = ?
                    WHERE id = ?";
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "dii", $lateFeeAmount, $adminId, $bookingId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to confirm late fee");
    }
    
    // Log the action
    $logNotes = "Late fee confirmed: ₱" . number_format($lateFeeAmount, 2) . ($notes ? " - " . $notes : "");
    $logQuery = "INSERT INTO admin_action_logs 
                 (admin_id, action_type, booking_id, notes, created_at)
                 VALUES (?, 'confirm_late_fee', ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $logQuery);
    mysqli_stmt_bind_param($stmt, "iis", $adminId, $bookingId, $logNotes);
    mysqli_stmt_execute($stmt);
    
    // Create notification for renter
    $notificationTitle = "Late Fee Confirmed ⚠️";
    $notificationMessage = "Your overdue booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . " has a confirmed late fee of ₱" . number_format($lateFeeAmount, 2) . ". Please submit payment to complete your booking.";
    
    $notifQuery = "INSERT INTO notifications 
                   (user_id, title, message, created_at)
                   VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $notifQuery);
    mysqli_stmt_bind_param($stmt, "iss", $booking['user_id'], $notificationTitle, $notificationMessage);
    mysqli_stmt_execute($stmt);
    
    // TODO: Send push notification if FCM token exists
    // TODO: Send email notification
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Late fee of ₱' . number_format($lateFeeAmount, 2) . ' confirmed and renter has been notified.'
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
