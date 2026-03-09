<?php
/**
 * =====================================================
 * VERIFY / REJECT PAYMENT (ADMIN)
 * CarGo – GCash Escrow System
 * =====================================================
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . "/../../include/db.php";

$response = [
    "success" => false,
    "message" => ""
];

/**
 * -----------------------------------------------------
 * 1. AUTH CHECK
 * -----------------------------------------------------
 */
if (!isset($_SESSION['admin_id'])) {
    $response["message"] = "Unauthorized access";
    echo json_encode($response);
    exit;
}

$adminId = intval($_SESSION['admin_id']);

/**
 * -----------------------------------------------------
 * 2. INPUT VALIDATION
 * -----------------------------------------------------
 */
if (!isset($_POST['payment_id'], $_POST['action'])) {
    $response["message"] = "Missing required parameters";
    echo json_encode($response);
    exit;
}

$paymentId = intval($_POST['payment_id']);
$action = $_POST['action']; // verify | reject

if (!in_array($action, ['verify', 'reject'])) {
    $response["message"] = "Invalid action";
    echo json_encode($response);
    exit;
}

/**
 * -----------------------------------------------------
 * 3. START TRANSACTION
 * -----------------------------------------------------
 */
mysqli_begin_transaction($conn);

try {

    /**
     * -------------------------------------------------
     * 4. FETCH PAYMENT (ESCROW PENDING ONLY)
     * -------------------------------------------------
     */
    $sql = "
        SELECT 
            p.id AS payment_id,
            p.booking_id,
            p.status AS payment_status,
            b.id AS booking_id,
            b.user_id,
            b.owner_id
        FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id
        WHERE p.id = ? AND p.status = 'escrow_pending'
        FOR UPDATE
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Payment not found or already processed");
    }

    $row = $result->fetch_assoc();
    $bookingId = intval($row['booking_id']);

    /**
     * -------------------------------------------------
     * 5A. VERIFY PAYMENT → HOLD ESCROW
     * -------------------------------------------------
     */
    if ($action === 'verify') {

        // Update payment status
        $stmt = $conn->prepare("
            UPDATE payments
            SET 
                status = 'escrow_held',
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $adminId, $paymentId);
        $stmt->execute();

        // Update booking payment status
        $stmt = $conn->prepare("
            UPDATE bookings
            SET 
                payment_status = 'paid_escrow'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();

        $response["success"] = true;
        $response["message"] = "Payment verified. Funds are now held in escrow.";
    }

    /**
     * -------------------------------------------------
     * 5B. REJECT PAYMENT
     * -------------------------------------------------
     */
    if ($action === 'reject') {

        // Update payment
        $stmt = $conn->prepare("
            UPDATE payments
            SET 
                status = 'rejected',
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $adminId, $paymentId);
        $stmt->execute();

        // Cancel booking
        $stmt = $conn->prepare("
            UPDATE bookings
            SET 
                booking_status = 'cancelled',
                payment_status = 'payment_under_review',
                cancelled_at = NOW(),
                cancellation_reason = 'Payment rejected by admin'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();

        $response["success"] = true;
        $response["message"] = "Payment rejected. Booking has been cancelled.";
    }

    /**
     * -------------------------------------------------
     * 6. COMMIT
     * -------------------------------------------------
     */
    mysqli_commit($conn);

} catch (Exception $e) {
    mysqli_rollback($conn);
    $response["message"] = "Error: " . $e->getMessage();
}

/**
 * -----------------------------------------------------
 * 7. RESPONSE
 * -----------------------------------------------------
 */
echo json_encode($response);
$conn->close();
