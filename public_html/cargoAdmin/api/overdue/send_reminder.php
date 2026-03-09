<?php
/**
 * Send Overdue Reminder
 * Sends notification to renter about overdue booking
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

if (!$bookingId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Get booking details
    $bookingQuery = "SELECT b.*, 
                     u.fullname as renter_name, 
                     u.email as renter_email, 
                     u.phone as renter_phone,
                     u.fcm_token,
                     COALESCE(c.brand, m.brand) as vehicle_brand,
                     COALESCE(c.model, m.model) as vehicle_model,
                     FLOOR(TIMESTAMPDIFF(HOUR, CONCAT(b.return_date, ' ', b.return_time), NOW()) / 24) as days_overdue
                     FROM bookings b
                     JOIN users u ON b.user_id = u.id
                     LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
                     LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
                     WHERE b.id = ?";
    $stmt = mysqli_prepare($conn, $bookingQuery);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Check how many reminders have been sent
    $reminderCountQuery = "SELECT COUNT(*) as reminder_count 
                           FROM admin_action_logs 
                           WHERE booking_id = ? AND action_type = 'send_reminder'";
    $stmt = mysqli_prepare($conn, $reminderCountQuery);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $countResult = mysqli_stmt_get_result($stmt);
    $reminderCount = mysqli_fetch_assoc($countResult)['reminder_count'] + 1;
    
    // Update reminder count in booking
    $updateQuery = "UPDATE bookings 
                    SET reminder_count = reminder_count + 1,
                        last_reminder_sent = NOW()
                    WHERE id = ?";
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    
    // Log the action
    $logNotes = "Reminder #" . $reminderCount . " sent to " . $booking['renter_name'];
    $logQuery = "INSERT INTO admin_action_logs 
                 (admin_id, action_type, booking_id, notes, created_at)
                 VALUES (?, 'send_reminder', ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $logQuery);
    mysqli_stmt_bind_param($stmt, "iis", $adminId, $bookingId, $logNotes);
    mysqli_stmt_execute($stmt);
    
    // Create notification for renter
    $vehicleName = $booking['vehicle_brand'] . ' ' . $booking['vehicle_model'];
    $notificationTitle = "⚠️ Overdue Booking Reminder #" . $reminderCount;
    $notificationMessage = "Your booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . 
                          " for " . $vehicleName . " is " . $booking['days_overdue'] . 
                          " days overdue. Late fee: ₱" . number_format($booking['late_fee_amount'], 2) . 
                          ". Please return the vehicle and complete payment immediately.";
    
    $notifQuery = "INSERT INTO notifications 
                   (user_id, title, message, created_at)
                   VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $notifQuery);
    mysqli_stmt_bind_param($stmt, "iss", $booking['user_id'], $notificationTitle, $notificationMessage);
    mysqli_stmt_execute($stmt);
    
    // TODO: Send push notification if FCM token exists
    // TODO: Send email notification
    // TODO: Send SMS notification if phone exists
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reminder #' . $reminderCount . ' sent successfully to ' . $booking['renter_name'],
        'reminder_count' => $reminderCount
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
