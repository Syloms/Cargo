<?php
/**
 * Verify Late Fee Payment API
 * Admin endpoint to verify late fee payments from renters
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit();
}

$paymentId = $_POST['payment_id'] ?? null;
$adminId = $_POST['admin_id'] ?? null;
$action = $_POST['action'] ?? null; // 'approve' or 'reject'
$notes = $_POST['notes'] ?? '';

if (!$paymentId || !$adminId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

mysqli_begin_transaction($conn);

try {
    // Get late fee payment details
    $query = "SELECT lfp.*, b.owner_id, b.user_id as renter_id,
              CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name
              FROM late_fee_payments lfp
              JOIN bookings b ON lfp.booking_id = b.id
              LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
              LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
              WHERE lfp.id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $paymentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($result);
    
    if (!$payment) {
        throw new Exception('Payment not found');
    }
    
    if ($payment['payment_status'] !== 'pending') {
        throw new Exception('Payment has already been processed');
    }
    
    if ($action === 'approve') {
        // Update late fee payment status to verified
        $updatePaymentQuery = "UPDATE late_fee_payments 
                              SET payment_status = 'verified',
                                  verified_by = ?,
                                  verified_at = NOW(),
                                  verification_notes = ?
                              WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $updatePaymentQuery);
        mysqli_stmt_bind_param($stmt, "isi", $adminId, $notes, $paymentId);
        mysqli_stmt_execute($stmt);
        
        // Update booking status
        if ($payment['is_rental_paid']) {
            // Only late fee was paid, mark late_fee_payment_status as paid
            $updateBookingQuery = "UPDATE bookings 
                                  SET late_fee_payment_status = 'paid',
                                      late_fee_charged = 1,
                                      updated_at = NOW()
                                  WHERE id = ?";
        } else {
            // Both rental and late fee paid, update both statuses
            $updateBookingQuery = "UPDATE bookings 
                                  SET payment_status = 'paid',
                                      late_fee_payment_status = 'paid',
                                      late_fee_charged = 1,
                                      updated_at = NOW()
                                  WHERE id = ?";
        }
        
        $stmt = mysqli_prepare($conn, $updateBookingQuery);
        mysqli_stmt_bind_param($stmt, "i", $payment['booking_id']);
        mysqli_stmt_execute($stmt);
        
        // Log the transaction
        $description = $payment['is_rental_paid'] 
            ? "Late fee payment verified (rental already paid)" 
            : "Late fee payment with rental verified";
        
        $metadata = json_encode([
            'late_fee_payment_id' => $paymentId,
            'late_fee_amount' => $payment['late_fee_amount'],
            'rental_amount' => $payment['rental_amount'],
            'total_amount' => $payment['total_amount'],
            'verified_by' => $adminId
        ]);
        
        $logQuery = "INSERT INTO payment_transactions 
                     (booking_id, transaction_type, amount, description, reference_id, metadata, created_by, created_at)
                     VALUES (?, 'late_fee_verification', ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($conn, $logQuery);
        mysqli_stmt_bind_param($stmt, "idssis", 
            $payment['booking_id'], 
            $payment['total_amount'], 
            $description, 
            $payment['payment_reference'],
            $metadata,
            $adminId
        );
        mysqli_stmt_execute($stmt);
        
        // Notify renter
        $renterNotif = "Your late fee payment of ₱{$payment['total_amount']} has been verified and approved.";
        $notifQuery = "INSERT INTO notifications (user_id, title, message, read_status, created_at)
                      VALUES (?, 'Late Fee Payment Approved', ?, 'unread', NOW())";
        $stmt = mysqli_prepare($conn, $notifQuery);
        mysqli_stmt_bind_param($stmt, "is", $payment['renter_id'], $renterNotif);
        mysqli_stmt_execute($stmt);
        
        // Notify owner
        $ownerNotif = "Late fee payment of ₱{$payment['total_amount']} for {$payment['vehicle_name']} has been verified.";
        $stmt = mysqli_prepare($conn, $notifQuery);
        mysqli_stmt_bind_param($stmt, "is", $payment['owner_id'], $ownerNotif);
        mysqli_stmt_execute($stmt);
        
        $message = 'Late fee payment approved successfully';
        
    } else if ($action === 'reject') {
        // Update late fee payment status to rejected
        $updatePaymentQuery = "UPDATE late_fee_payments 
                              SET payment_status = 'rejected',
                                  verified_by = ?,
                                  verified_at = NOW(),
                                  verification_notes = ?
                              WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $updatePaymentQuery);
        mysqli_stmt_bind_param($stmt, "isi", $adminId, $notes, $paymentId);
        mysqli_stmt_execute($stmt);
        
        // Update booking late_fee_payment_status back to none
        $updateBookingQuery = "UPDATE bookings 
                              SET late_fee_payment_status = 'none',
                                  updated_at = NOW()
                              WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $updateBookingQuery);
        mysqli_stmt_bind_param($stmt, "i", $payment['booking_id']);
        mysqli_stmt_execute($stmt);
        
        // Notify renter
        $renterNotif = "Your late fee payment of ₱{$payment['total_amount']} was rejected. Reason: {$notes}. Please resubmit with correct details.";
        $notifQuery = "INSERT INTO notifications (user_id, title, message, read_status, created_at)
                      VALUES (?, 'Late Fee Payment Rejected', ?, 'unread', NOW())";
        $stmt = mysqli_prepare($conn, $notifQuery);
        mysqli_stmt_bind_param($stmt, "is", $payment['renter_id'], $renterNotif);
        mysqli_stmt_execute($stmt);
        
        $message = 'Late fee payment rejected';
    } else {
        throw new Exception('Invalid action');
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'payment_id' => $paymentId
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
