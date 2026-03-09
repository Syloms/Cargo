<?php
/**
 * Process Security Deposit Refund API
 * Admin endpoint to process security deposit refunds
 */
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../../include/db.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Admin login required.'
    ]);
    exit;
}

$adminId = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Validate inputs
if (!isset($_POST['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'booking_id is required'
    ]);
    exit;
}

$bookingId = intval($_POST['booking_id']);
$refundReference = trim($_POST['refund_reference'] ?? '');
$deductionAmount = isset($_POST['deduction_amount']) ? floatval($_POST['deduction_amount']) : 0.00;
$deductionReason = trim($_POST['deduction_reason'] ?? '');

mysqli_begin_transaction($conn);

try {
    // Fetch booking details
    $stmt = $conn->prepare("
        SELECT 
            id,
            user_id,
            owner_id,
            security_deposit_amount,
            security_deposit_status,
            security_deposit_deductions,
            status
        FROM bookings
        WHERE id = ?
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Booking not found');
    }
    
    $booking = $result->fetch_assoc();
    
    // Validate booking status
    if ($booking['status'] !== 'completed') {
        throw new Exception('Cannot refund deposit for non-completed booking');
    }
    
    if ($booking['security_deposit_status'] !== 'held') {
        throw new Exception('Security deposit is not in held status');
    }
    
    if ($booking['security_deposit_amount'] <= 0) {
        throw new Exception('No security deposit to refund');
    }
    
    // Validate deduction amount
    if ($deductionAmount < 0) {
        throw new Exception('Deduction amount cannot be negative');
    }
    
    if ($deductionAmount > $booking['security_deposit_amount']) {
        throw new Exception('Deduction amount cannot exceed deposit amount');
    }
    
    // Calculate refund amount
    $totalDeductions = $deductionAmount;
    $refundAmount = $booking['security_deposit_amount'] - $totalDeductions;
    
    // Determine refund status
    $refundStatus = 'refunded';
    if ($refundAmount <= 0) {
        $refundStatus = 'forfeited';
    } elseif ($totalDeductions > 0) {
        $refundStatus = 'partial_refund';
    }
    
    // Update booking with refund details
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET 
            security_deposit_status = ?,
            security_deposit_refunded_at = NOW(),
            security_deposit_refund_amount = ?,
            security_deposit_deductions = ?,
            security_deposit_deduction_reason = ?,
            security_deposit_refund_reference = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "sddssi",
        $refundStatus,
        $refundAmount,
        $totalDeductions,
        $deductionReason,
        $refundReference,
        $bookingId
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update booking refund status');
    }
    
    // Create notification for renter
    $notificationMessage = "Your security deposit of ₱" . number_format($booking['security_deposit_amount'], 2);
    if ($refundAmount > 0) {
        $notificationMessage .= " has been processed. Refund amount: ₱" . number_format($refundAmount, 2);
        if ($totalDeductions > 0) {
            $notificationMessage .= " (Deductions: ₱" . number_format($totalDeductions, 2) . ")";
        }
    } else {
        $notificationMessage .= " has been forfeited. Reason: " . $deductionReason;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, created_at)
        VALUES (?, 'security_deposit', 'Security Deposit Refund', ?, NOW())
    ");
    $stmt->bind_param("is", $booking['user_id'], $notificationMessage);
    $stmt->execute();
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Security deposit refund processed successfully',
        'data' => [
            'booking_id' => $bookingId,
            'deposit_amount' => floatval($booking['security_deposit_amount']),
            'deductions' => floatval($totalDeductions),
            'refund_amount' => floatval($refundAmount),
            'refund_status' => $refundStatus,
            'refund_reference' => $refundReference
        ]
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
