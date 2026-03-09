<?php
/**
 * Submit Late Fee Payment API
 * Handles payment submission for overdue rental bookings with late fees
 */

// Suppress all errors from appearing in JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../include/db.php';
require_once __DIR__ . '/../security/suspension_guard.php';

// Check if notification_helper.php exists, if not, skip it
if (file_exists('../../include/notification_helper.php')) {
    require_once '../../include/notification_helper.php';
}

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed'
    ]);
    exit();
}

// Get POST data
$bookingId = $_POST['booking_id'] ?? null;
$userId = $_POST['user_id'] ?? null;
$gcashNumber = $_POST['gcash_number'] ?? null;
$referenceNumber = $_POST['reference_number'] ?? null;
$totalAmount = $_POST['total_amount'] ?? null;
$rentalAmount = $_POST['rental_amount'] ?? null;
$lateFeeAmount = $_POST['late_fee_amount'] ?? null;

// Validate required fields
if (!$bookingId || !$userId || !$gcashNumber || !$referenceNumber || !$totalAmount || !$lateFeeAmount) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit();
}

// Block suspended users
require_not_suspended($conn, intval($userId));

// Start transaction
mysqli_begin_transaction($conn);

try {
    // 1. Verify booking exists and is overdue
    $bookingQuery = "SELECT b.*, u.fullname as renter_name, 
                     CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
                     b.owner_id, b.payment_status as booking_payment_status
                     FROM bookings b
                     LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
                     LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
                     LEFT JOIN users u ON b.user_id = u.id
                     WHERE b.id = ? AND b.user_id = ? AND b.status = 'approved'";
    
    $stmt = mysqli_prepare($conn, $bookingQuery);
    mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        throw new Exception('Booking not found or not authorized');
    }
    
    // Check if late fee already paid
    if ($booking['late_fee_charged'] == 1) {
        throw new Exception('Late fee has already been paid for this booking');
    }
    
    // Validate that the submitted late fee amount is reasonable
    // Don't check database late_fee_amount because it might be 0 if cron hasn't run yet
    // Instead, validate that the submitted amount is positive
    if ($lateFeeAmount <= 0) {
        throw new Exception('Invalid late fee amount. Late fee must be greater than 0.');
    }
    
    // Calculate if booking is actually overdue (real-time check)
    $returnDateTime = strtotime($booking['return_date'] . ' ' . $booking['return_time']);
    $currentTime = time();
    $isActuallyOverdue = $currentTime > $returnDateTime;
    
    if (!$isActuallyOverdue) {
        throw new Exception('This booking is not overdue yet. No late fee required.');
    }
    
    // Determine if rental is already paid
    $isRentalPaid = ($booking['booking_payment_status'] === 'paid');
    
    // Calculate correct payment amount
    if ($isRentalPaid) {
        // Rental already paid, only pay late fee
        $correctAmount = $lateFeeAmount;
        $actualRentalAmount = 0; // No rental fee needed
    } else {
        // Rental not paid, pay rental + late fee
        $correctAmount = $rentalAmount + $lateFeeAmount;
        $actualRentalAmount = $rentalAmount;
    }
    
    // Validate submitted amount matches what's expected
    if (abs($totalAmount - $correctAmount) > 0.01) {
        $expectedMsg = $isRentalPaid 
            ? "Late fee only: ₱" . number_format($correctAmount, 2) . " (rental already paid)"
            : "Rental + Late fee: ₱" . number_format($correctAmount, 2);
        throw new Exception("Incorrect payment amount. Expected: {$expectedMsg}");
    }
    
    // 2. Check if late fee payment already exists
    $checkPaymentQuery = "SELECT id FROM late_fee_payments 
                          WHERE booking_id = ? AND payment_status = 'pending'";
    $stmt = mysqli_prepare($conn, $checkPaymentQuery);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $existingPayment = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($existingPayment) > 0) {
        throw new Exception('A late fee payment for this booking is already pending verification');
    }
    
    // 3. Calculate days and hours overdue
    $hoursOverdue = isset($_POST['hours_overdue']) ? intval($_POST['hours_overdue']) : 0;
    $daysOverdue = isset($booking['overdue_days']) && $booking['overdue_days'] > 0 
        ? $booking['overdue_days'] 
        : ceil($hoursOverdue / 24);
    
    // 4. Insert into late_fee_payments table (not regular payments table)
    $insertPaymentQuery = "INSERT INTO late_fee_payments 
                          (booking_id, user_id, late_fee_amount, rental_amount, total_amount, 
                           payment_method, payment_reference, gcash_number, payment_status, 
                           is_rental_paid, hours_overdue, days_overdue, payment_date, created_at) 
                          VALUES (?, ?, ?, ?, ?, 'gcash', ?, ?, 'pending', ?, ?, ?, NOW(), NOW())";
    
    $stmt = mysqli_prepare($conn, $insertPaymentQuery);
    $isRentalPaidInt = $isRentalPaid ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "iidddssiii", 
        $bookingId, 
        $userId, 
        $lateFeeAmount, 
        $actualRentalAmount, 
        $totalAmount, 
        $referenceNumber, 
        $gcashNumber,
        $isRentalPaidInt,
        $hoursOverdue,
        $daysOverdue
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to create late fee payment record: ' . mysqli_error($conn));
    }
    
    $paymentId = mysqli_insert_id($conn);
    
    // 5. Update booking with late fee amount and payment status
    // Record the late fee amount in the database so cron doesn't recalculate it
    if ($isRentalPaid) {
        // Rental already paid, update late_fee_amount and late_fee_payment_status
        $updateBookingQuery = "UPDATE bookings 
                              SET late_fee_amount = ?,
                                  late_fee_payment_status = 'pending',
                                  updated_at = NOW()
                              WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $updateBookingQuery);
        mysqli_stmt_bind_param($stmt, "di", $lateFeeAmount, $bookingId);
    } else {
        // Rental not paid yet, update both payment_status and late_fee fields
        $updateBookingQuery = "UPDATE bookings 
                              SET late_fee_amount = ?,
                                  payment_status = 'pending',
                                  late_fee_payment_status = 'pending',
                                  updated_at = NOW()
                              WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $updateBookingQuery);
        mysqli_stmt_bind_param($stmt, "di", $lateFeeAmount, $bookingId);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update booking: ' . mysqli_error($conn));
    }
    
    // 6. Log transaction
    $descriptionParts = [];
    if ($isRentalPaid) {
        $descriptionParts[] = "Late fee payment only (rental already paid)";
    } else {
        $descriptionParts[] = "Late fee payment with rental";
    }
    $descriptionParts[] = "Rental: ₱{$actualRentalAmount}, Late Fee: ₱{$lateFeeAmount}";
    $description = implode(" - ", $descriptionParts);
    
    $metadata = json_encode([
        'payment_id' => $paymentId,
        'payment_type' => 'late_fee_payment',  // Mark as late fee in metadata
        'rental_amount' => $actualRentalAmount,
        'late_fee_amount' => $lateFeeAmount,
        'is_rental_paid' => $isRentalPaid,
        'rental_already_paid' => $isRentalPaid,
        'gcash_number' => substr($gcashNumber, 0, 4) . '****' . substr($gcashNumber, -4),
        'reference_number' => $referenceNumber
    ]);
    
    $logQuery = "INSERT INTO payment_transactions 
                 (booking_id, transaction_type, amount, description, reference_id, metadata, created_by, created_at)
                 VALUES (?, 'payment', ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $logQuery);
    mysqli_stmt_bind_param($stmt, "idssis", $bookingId, $totalAmount, $description, $referenceNumber, $metadata, $userId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to log transaction: ' . mysqli_error($conn));
    }
    
    // 7. Send notification to admin (if table exists)
    $paymentBreakdown = $isRentalPaid 
        ? "Late Fee Only: ₱{$lateFeeAmount} (rental already paid)"
        : "Rental: ₱{$actualRentalAmount} + Late Fee: ₱{$lateFeeAmount}";
    $adminNotification = "New late fee payment of ₱" . number_format($totalAmount, 2) . " submitted by {$booking['renter_name']} for {$booking['vehicle_name']} (Booking #{$bookingId}). {$paymentBreakdown}. Please verify.";
    
    // Check if admin_notifications table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'admin_notifications'");
    if (mysqli_num_rows($tableCheck) > 0) {
        $notifQuery = "INSERT INTO admin_notifications 
                       (type, title, message, link, icon, priority, read_status, created_at) 
                       VALUES ('payment', 'Late Fee Payment Pending', ?, 'payment.php?type=late_fee', 'credit-card', 'high', 'unread', NOW())";
        
        $stmt = mysqli_prepare($conn, $notifQuery);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $adminNotification);
            mysqli_stmt_execute($stmt);
        }
    }
    
    // 8. Send notification to owner
    $ownerPaymentInfo = $isRentalPaid 
        ? "Late fee: ₱{$totalAmount}"
        : "Total: ₱{$totalAmount} (Rental + Late Fee)";
    $ownerNotification = "Renter {$booking['renter_name']} submitted a late fee payment for {$booking['vehicle_name']}. {$ownerPaymentInfo}";
    
    $ownerNotifQuery = "INSERT INTO notifications 
                        (user_id, title, message, read_status, created_at) 
                        VALUES (?, 'Late Fee Payment Submitted', ?, 'unread', NOW())";
    
    $stmt = mysqli_prepare($conn, $ownerNotifQuery);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $booking['owner_id'], $ownerNotification);
        mysqli_stmt_execute($stmt);
    }
    
    // 9. Send notification to renter
    $renterNotification = "Your late fee payment of ₱{$totalAmount} for {$booking['vehicle_name']} has been submitted and is pending verification.";
    
    $stmt = mysqli_prepare($conn, $ownerNotifQuery);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $userId, $renterNotification);
        mysqli_stmt_execute($stmt);
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Late fee payment submitted successfully',
        'payment_id' => $paymentId,
        'data' => [
            'booking_id' => $bookingId,
            'payment_id' => $paymentId,
            'total_amount' => $totalAmount,
            'late_fee_amount' => $lateFeeAmount,
            'status' => 'pending',
            'reference_number' => $referenceNumber
        ]
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
