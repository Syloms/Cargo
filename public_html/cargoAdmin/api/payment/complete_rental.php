<?php
/**
 * =====================================================
 * RELEASE ESCROW & RECORD OWNER PAYOUT (ADMIN)
 * CarGo – Manual GCash Payout
 * =====================================================
 */

session_start();
header("Content-Type: application/json");

require_once __DIR__ . "/../../include/db.php";

$response = ["success" => false, "message" => ""];

/* ----------------------------------------------------
 * AUTH CHECK
 * --------------------------------------------------*/
if (!isset($_SESSION['admin_id'])) {
    $response["message"] = "Unauthorized access";
    echo json_encode($response);
    exit;
}

$adminId = intval($_SESSION['admin_id']);

/* ----------------------------------------------------
 * INPUT VALIDATION (FORM DATA)
 * --------------------------------------------------*/
if (!isset($_POST['booking_id'], $_POST['payout_reference'])) {
    $response["message"] = "Missing required fields";
    echo json_encode($response);
    exit;
}

$bookingId = intval($_POST['booking_id']);
$payoutReference = trim($_POST['payout_reference']);

if ($payoutReference === "") {
    $response["message"] = "Payout reference is required";
    echo json_encode($response);
    exit;
}

/* ----------------------------------------------------
 * TRANSACTION
 * --------------------------------------------------*/
mysqli_begin_transaction($conn);

try {

    /* ----------------------------------------------
     * GET ESCROWED PAYMENT
     * --------------------------------------------*/
    $sql = "
        SELECT p.id
        FROM payments p
        WHERE p.booking_id = ?
          AND p.status = 'escrow_held'
        FOR UPDATE
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("No escrowed payment found for this booking");
    }

    $payment = $result->fetch_assoc();
    $paymentId = intval($payment['id']);

    /* ----------------------------------------------
     * RELEASE ESCROW
     * --------------------------------------------*/
    $stmt = $conn->prepare("
        UPDATE payments
        SET 
            status = 'released',
            payout_reference = ?,
            released_at = NOW(),
            released_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $payoutReference, $adminId, $paymentId);
    $stmt->execute();

    /* ----------------------------------------------
     * COMPLETE BOOKING
     * --------------------------------------------*/
    $stmt = $conn->prepare("
        UPDATE bookings
        SET 
            booking_status = 'completed',
            payment_status = 'released'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();

    mysqli_commit($conn);

    $response["success"] = true;
    $response["message"] = "Escrow released and payout recorded successfully.";

} catch (Exception $e) {
    mysqli_rollback($conn);
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
$conn->close();
