<?php
/**
 * ============================================================================
 * PROCESS REFUND API - Admin processes refund requests
 * Actions: approve, reject, complete
 * FIXED: Proper timestamp updates and status tracking
 * ============================================================================
 */

session_start();
header('Content-Type: application/json');

require_once '../../include/db.php';

// ============================================================================
// CHECK ADMIN AUTHENTICATION
// ============================================================================

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Admin login required.'
    ]);
    exit;
}

$admin_id = $_SESSION['admin_id'];

// ============================================================================
// VALIDATE REQUEST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$refund_id = isset($_POST['refund_id']) ? intval($_POST['refund_id']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
$completion_reference = isset($_POST['completion_reference']) ? trim($_POST['completion_reference']) : '';
$refund_reference = isset($_POST['refund_reference']) ? trim($_POST['refund_reference']) : '';
$deduction_amount = isset($_POST['deduction_amount']) ? floatval($_POST['deduction_amount']) : 0;
$deduction_reason = isset($_POST['deduction_reason']) ? trim($_POST['deduction_reason']) : '';

// ============================================================================
// VALIDATION
// ============================================================================

if ($refund_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid refund ID'
    ]);
    exit;
}

$allowed_actions = ['approve', 'reject', 'complete'];
if (!in_array($action, $allowed_actions)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action. Allowed: approve, reject, complete'
    ]);
    exit;
}

// ============================================================================
// GET REFUND DETAILS
// ============================================================================

$refund_query = "
    SELECT 
        r.*,
        b.status AS booking_status,
        b.total_amount AS booking_amount,
        b.owner_id,
        b.user_id AS renter_id,
        u_owner.email AS owner_email,
        u_renter.email AS renter_email,
        u_renter.fullname AS renter_name
    FROM refunds r
    INNER JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN users u_owner ON b.owner_id = u_owner.id
    LEFT JOIN users u_renter ON r.user_id = u_renter.id
    WHERE r.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($refund_query);
$stmt->bind_param("i", $refund_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Refund request not found'
    ]);
    exit;
}

$refund = $result->fetch_assoc();

// ============================================================================
// PROCESS ACTION
// ============================================================================

$conn->begin_transaction();

try {
    $message = '';
    $new_status = '';
    
    switch ($action) {
        // ========================================
        // APPROVE REFUND
        // ========================================
        case 'approve':
            if ($refund['status'] !== 'pending') {
                throw new Exception('Only pending refunds can be approved');
            }

            // Apply deductions if any
            $final_deduction = max(0, $deduction_amount);
            $deduction_reason_text = $final_deduction > 0 ? $deduction_reason : null;

            // Update refund record with approved timestamp
            $update_refund = "
                UPDATE refunds 
                SET 
                    status = 'approved',
                    deduction_amount = ?,
                    deduction_reason = ?,
                    processed_by = ?,
                    processed_at = NOW(),
                    approved_at = NOW()
                WHERE id = ?
            ";

            $stmt = $conn->prepare($update_refund);
            $stmt->bind_param("dsii", $final_deduction, $deduction_reason_text, $admin_id, $refund_id);
            $stmt->execute();

            // Update booking refund status
            $update_booking = "
                UPDATE bookings 
                SET refund_status = 'approved'
                WHERE id = ?
            ";
            $stmt = $conn->prepare($update_booking);
            $stmt->bind_param("i", $refund['booking_id']);
            $stmt->execute();

            $new_status = 'approved';
            $message = 'Refund approved successfully';
            
            // Notify renter about approval
            $notify_sql = "
                INSERT INTO notifications (user_id, title, message, type, read_status, created_at)
                VALUES (?, 'Refund Approved ✓', CONCAT('Your refund request of ₱', ?, ' has been approved and will be processed shortly.'), 'refund_approved', 'unread', NOW())
            ";
            $stmt = $conn->prepare($notify_sql);
            $stmt->bind_param("id", $refund['renter_id'], $refund['refund_amount']);
            $stmt->execute();
            
            break;

        // ========================================
        // REJECT REFUND
        // ========================================
        case 'reject':
            if ($refund['status'] !== 'pending') {
                throw new Exception('Only pending refunds can be rejected');
            }

            if (empty($rejection_reason)) {
                throw new Exception('Rejection reason is required');
            }

            // DELETE the refund record instead of updating it
            // This allows the user to submit a new refund request
            $delete_refund = "DELETE FROM refunds WHERE id = ?";
            $stmt = $conn->prepare($delete_refund);
            $stmt->bind_param("i", $refund_id);
            $stmt->execute();

            // Update booking to reset refund status
            $update_booking = "
                UPDATE bookings 
                SET 
                    refund_status = 'not_requested',
                    refund_requested = 0,
                    refund_amount = 0
                WHERE id = ?
            ";
            $stmt = $conn->prepare($update_booking);
            $stmt->bind_param("i", $refund['booking_id']);
            $stmt->execute();

            // Log the rejection for admin records
            $log_rejection = "
                INSERT INTO payment_transactions 
                (booking_id, transaction_type, amount, description, created_by, created_at)
                VALUES (?, 'refund_rejected', ?, ?, ?, NOW())
            ";
            
            $refund_amount = floatval($refund['refund_amount']);
            $description = "Refund rejected - Reason: " . $rejection_reason;
            $stmt = $conn->prepare($log_rejection);
            $stmt->bind_param("idsi", 
                $refund['booking_id'], 
                $refund_amount, 
                $description, 
                $admin_id
            );
            $stmt->execute();

            $new_status = 'rejected';
            $message = 'Refund rejected - User can submit a new request';
            
            // Notify renter about rejection with reason
            $notify_sql = "
                INSERT INTO notifications (user_id, title, message, type, read_status, created_at)
                VALUES (?, 'Refund Request Rejected ✗', CONCAT('Your refund request has been rejected. Reason: ', ?), 'refund_rejected', 'unread', NOW())
            ";
            $stmt = $conn->prepare($notify_sql);
            $stmt->bind_param("is", $refund['renter_id'], $rejection_reason);
            $stmt->execute();
            
            break;

        // ========================================
        // COMPLETE REFUND (Mark as transferred)
        // ========================================
        case 'complete':
            if (!in_array($refund['status'], ['approved', 'processing'])) {
                throw new Exception('Only approved or processing refunds can be completed');
            }

            // Require either completion_reference or refund_reference
            if (empty($completion_reference) && empty($refund_reference)) {
                throw new Exception('Transaction reference number is required');
            }

            $reference = !empty($completion_reference) ? $completion_reference : $refund_reference;

            // Update refund record with completed timestamp
            $update_refund = "
                UPDATE refunds 
                SET 
                    status = 'completed',
                    completion_reference = ?,
                    refund_reference = ?,
                    processed_by = ?,
                    processed_at = NOW(),
                    completed_at = NOW()
                WHERE id = ?
            ";

            $stmt = $conn->prepare($update_refund);
            $stmt->bind_param("ssii", $reference, $reference, $admin_id, $refund_id);
            $stmt->execute();

            // **FIX: Update booking with BOTH refund_status AND escrow_status**
            $update_booking = "
                UPDATE bookings 
                SET 
                    refund_status = 'completed',
                    escrow_status = 'refunded',
                    escrow_refunded_at = NOW()
                WHERE id = ?
            ";
            $stmt = $conn->prepare($update_booking);
            $stmt->bind_param("i", $refund['booking_id']);
            $stmt->execute();

            // **FIX: Update payment status to refunded**
            if (!empty($refund['payment_id'])) {
                $update_payment = "
                    UPDATE payments 
                    SET payment_status = 'refunded'
                    WHERE id = ?
                ";
                $stmt = $conn->prepare($update_payment);
                $stmt->bind_param("i", $refund['payment_id']);
                $stmt->execute();
            }

            // **FIX: Update escrow table if it exists**
            $check_escrow_table = $conn->query("SHOW TABLES LIKE 'escrow'");
            if ($check_escrow_table->num_rows > 0) {
                $update_escrow = "
                    UPDATE escrow 
                    SET 
                        status = 'refunded',
                        refunded_at = NOW(),
                        refund_reason = CONCAT('Refund completed - ', ?),
                        processed_by = ?
                    WHERE booking_id = ? AND status = 'held'
                ";
                $stmt = $conn->prepare($update_escrow);
                $stmt->bind_param("sii", $reference, $admin_id, $refund['booking_id']);
                $stmt->execute();
            }

            // **FIX: Log in escrow_logs if table exists**
            $check_logs_table = $conn->query("SHOW TABLES LIKE 'escrow_logs'");
            if ($check_logs_table->num_rows > 0) {
                $log_escrow = "
                    INSERT INTO escrow_logs 
                    (booking_id, action, previous_status, new_status, admin_id, notes, created_at)
                    VALUES (?, 'refund', 'held', 'refunded', ?, CONCAT('Refund completed via process_refund.php - Reference: ', ?), NOW())
                ";
                $stmt = $conn->prepare($log_escrow);
                $stmt->bind_param("iis", $refund['booking_id'], $admin_id, $reference);
                $stmt->execute();
            }

            // Log transaction
            $final_amount = floatval($refund['refund_amount']) - floatval($refund['deduction_amount']);
            
            $log_transaction = "
                INSERT INTO payment_transactions 
                (booking_id, transaction_type, amount, description, created_by, created_at)
                VALUES (?, 'refund', ?, ?, ?, NOW())
            ";
            
            $description = "Refund completed - Reference: " . $reference;
            $stmt = $conn->prepare($log_transaction);
            $stmt->bind_param("idsi", 
                $refund['booking_id'], 
                $final_amount, 
                $description, 
                $admin_id
            );
            $stmt->execute();

            $new_status = 'completed';
            $message = 'Refund completed and funds transferred';
            
            // Notify renter about completion
            $notify_sql = "
                INSERT INTO notifications (user_id, title, message, type, read_status, created_at)
                VALUES (?, 'Refund Completed 💵', CONCAT('Your refund of ₱', FORMAT(?, 2), ' has been processed. Reference: ', ?), 'refund_completed', 'unread', NOW())
            ";
            $stmt = $conn->prepare($notify_sql);
            $stmt->bind_param("ids", $refund['renter_id'], $final_amount, $reference);
            $stmt->execute();
            
            break;
    }

    $conn->commit();

    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'refund_id' => $refund_id,
            'action' => $action,
            'new_status' => $new_status,
            'processed_at' => date('Y-m-d H:i:s'),
            'processed_by' => $admin_id,
            'final_amount' => isset($final_amount) ? $final_amount : null,
            'reference' => isset($reference) ? $reference : null
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();