<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../../include/db.php";
require_once __DIR__ . "/transaction_logger.php";

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit;
}

$adminId = (int) $_SESSION['admin_id'];

if (!isset($_POST['booking_id'])) {
    echo json_encode(["success" => false, "message" => "Missing booking_id"]);
    exit;
}

$bookingId = (int) $_POST['booking_id'];

mysqli_begin_transaction($conn);

try {
    $logger = new TransactionLogger($conn);
    
    // Get booking & escrow info
    $sql = "
        SELECT 
            b.id,
            b.escrow_status,
            b.payout_status,
            b.owner_payout,
            b.owner_id,
            e.id as escrow_id,
            e.amount as escrow_amount
        FROM bookings b
        LEFT JOIN escrow e ON e.booking_id = b.id AND e.status = 'held'
        WHERE b.id = ? 
        FOR UPDATE
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Booking not found");
    }

    $booking = $result->fetch_assoc();

    if ($booking['escrow_status'] !== 'held') {
        throw new Exception("Escrow is not held or already released");
    }

    if ($booking['payout_status'] === 'completed') {
        throw new Exception("Payout already completed");
    }
    
    if (!$booking['escrow_id']) {
        throw new Exception("Escrow record not found");
    }

    // Update escrow status
    $stmt = $conn->prepare("
        UPDATE escrow SET
            status = 'released',
            released_at = NOW(),
            release_reason = 'Rental completed successfully',
            processed_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $adminId, $booking['escrow_id']);
    $stmt->execute();
    
    // Log escrow release
    $logger->log(
        $bookingId,
        'escrow_release',
        $booking['escrow_amount'],
        "Escrow released by admin",
        $adminId,
        ['escrow_id' => $booking['escrow_id']]
    );

    // Release escrow
    $updateBooking = "
        UPDATE bookings SET
            escrow_status = 'released_to_owner',
            escrow_released_at = NOW(),
            payout_status = 'processing'
        WHERE id = ?
    ";

    $stmt = $conn->prepare($updateBooking);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    
    // Create payout record
    $stmt = $conn->prepare("
        INSERT INTO payouts 
        (booking_id, owner_id, escrow_id, amount, platform_fee, net_amount, status, scheduled_at, processed_by)
        SELECT 
            b.id,
            b.owner_id,
            ?,
            b.total_amount,
            b.platform_fee,
            b.owner_payout,
            'pending',
            NOW(),
            ?
        FROM bookings b
        WHERE b.id = ?
    ");
    $stmt->bind_param("iii", $booking['escrow_id'], $adminId, $bookingId);
    $stmt->execute();
    
    $payoutId = $stmt->insert_id;
    
    // Log payout creation
    $logger->log(
        $bookingId,
        'payout',
        $booking['owner_payout'],
        "Payout scheduled for owner (Payout ID: $payoutId)",
        $adminId,
        [
            'payout_id' => $payoutId,
            'owner_id' => $booking['owner_id']
        ]
    );
    
    // Notify owner
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message)
        VALUES (?, 'Payment Released 💰', CONCAT('Your payout of ₱', FORMAT(?, 2), ' is being processed.'))
    ");
    $stmt->bind_param("id", $booking['owner_id'], $booking['owner_payout']);
    $stmt->execute();

    mysqli_commit($conn);

    echo json_encode([
        "success" => true,
        "message" => "Escrow released successfully",
        "payout_id" => $payoutId
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();