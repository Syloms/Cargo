<?php
/**
 * ============================================================================
 * RELEASE ESCROW API - FIXED
 * Release funds from escrow to owner (schedule payout)
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

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get booking details
    $bookingQuery = "
        SELECT 
            b.*,
            p.payment_status,
            u.fullname AS owner_name,
            u.email AS owner_email,
            u.gcash_number AS owner_gcash
        FROM bookings b
        LEFT JOIN payments p ON b.id = p.booking_id
        LEFT JOIN users u ON b.owner_id = u.id
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
    
    // Validate escrow can be released
    if ($booking['escrow_status'] !== 'held') {
        throw new Exception('Escrow is not in held status. Current status: ' . $booking['escrow_status']);
    }
    
    // Check if it's on hold
    if (!empty($booking['escrow_hold_reason'])) {
        throw new Exception('Cannot release escrow that is on hold. Please resolve the hold first.');
    }
    
    // Check if payment is verified
    if ($booking['payment_status'] !== 'verified' && $booking['payment_status'] !== 'paid') {
        throw new Exception('Payment must be verified before releasing escrow');
    }
    
    // Ideally, booking should be completed before releasing
    if (!in_array($booking['status'], ['completed', 'ongoing', 'approved'])) {
        throw new Exception('Booking status must be completed, ongoing, or approved. Current: ' . $booking['status']);
    }
    
    // Check if escrow table exists and get/create escrow record
    $escrowId = null;
    $checkEscrowTable = mysqli_query($conn, "SHOW TABLES LIKE 'escrow'");
    
    if (mysqli_num_rows($checkEscrowTable) > 0) {
        // Check if escrow record exists
        $escrowQuery = "SELECT id, status FROM escrow WHERE booking_id = ?";
        $stmt = mysqli_prepare($conn, $escrowQuery);
        mysqli_stmt_bind_param($stmt, "i", $bookingId);
        mysqli_stmt_execute($stmt);
        $escrowResult = mysqli_stmt_get_result($stmt);
        
        if ($escrowRow = mysqli_fetch_assoc($escrowResult)) {
            $escrowId = $escrowRow['id'];
            
            // Update escrow table status
            $updateEscrowTable = "
                UPDATE escrow 
                SET 
                    status = 'released',
                    released_at = NOW(),
                    release_reason = 'Normal release - rental completed'
                WHERE id = ?
            ";
            $stmt = mysqli_prepare($conn, $updateEscrowTable);
            mysqli_stmt_bind_param($stmt, "i", $escrowId);
            mysqli_stmt_execute($stmt);
        } else {
            // Create escrow record if it doesn't exist
            $getPaymentId = "SELECT id FROM payments WHERE booking_id = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $getPaymentId);
            mysqli_stmt_bind_param($stmt, "i", $bookingId);
            mysqli_stmt_execute($stmt);
            $paymentResult = mysqli_stmt_get_result($stmt);
            $paymentRow = mysqli_fetch_assoc($paymentResult);
            $paymentId = $paymentRow['id'] ?? null;
            
            $createEscrow = "
                INSERT INTO escrow (
                    booking_id, 
                    payment_id, 
                    amount, 
                    status, 
                    held_at,
                    released_at, 
                    release_reason,
                    created_at
                ) VALUES (?, ?, ?, 'released', NOW(), NOW(), 'Normal release - rental completed', NOW())
            ";
            $stmt = mysqli_prepare($conn, $createEscrow);
            mysqli_stmt_bind_param($stmt, "iid", $bookingId, $paymentId, $booking['total_amount']);
            
            if (mysqli_stmt_execute($stmt)) {
                $escrowId = mysqli_insert_id($conn);
            }
        }
    }
    
    // Update booking escrow status
    $updateBooking = "
        UPDATE bookings 
        SET 
            escrow_status = 'released_to_owner',
            escrow_released_at = NOW(),
            payout_status = 'pending'
        WHERE id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $updateBooking);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update booking escrow status: ' . mysqli_error($conn));
    }
    
    // Create payout record with escrow_id
    if ($escrowId) {
        $createPayout = "
            INSERT INTO payouts (
                booking_id,
                owner_id,
                escrow_id,
                amount,
                platform_fee,
                net_amount,
                payout_method,
                payout_account,
                status,
                scheduled_at,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'gcash', ?, 'pending', NOW(), NOW())
        ";
        
        $stmt = mysqli_prepare($conn, $createPayout);
        $gcashAccount = $booking['owner_gcash'] ?? 'Not Set';
        
        mysqli_stmt_bind_param($stmt, "iiiddds", 
            $bookingId,
            $booking['owner_id'],
            $escrowId,
            $booking['total_amount'],
            $booking['platform_fee'],
            $booking['owner_payout'],
            $gcashAccount
        );
    } else {
        throw new Exception('Failed to get or create escrow record');
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to create payout record: ' . mysqli_error($conn));
    }
    
    // Log escrow release if escrow_logs table exists
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
            ) VALUES (?, 'release', 'held', 'released_to_owner', ?, 'Escrow released to owner', NOW())
        ";
        
        $stmt = mysqli_prepare($conn, $logQuery);
        mysqli_stmt_bind_param($stmt, "ii", $bookingId, $adminId);
        mysqli_stmt_execute($stmt);
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // TODO: Send notification to owner about payout
    
    echo json_encode([
        'success' => true,
        'message' => 'Escrow released successfully! Payout scheduled for owner.',
        'booking_id' => $bookingId,
        'owner_payout' => $booking['owner_payout'],
        'owner_name' => $booking['owner_name']
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