<?php
include "../include/db.php";
require_once __DIR__ . "/security/suspension_guard.php";

if (!isset($_POST['booking_id']) || !isset($_POST['user_id'])) {
  echo json_encode([
    "success" => false,
    "message" => "Missing parameters"
  ]);
  exit;
}

$booking_id = intval($_POST['booking_id']);
$user_id = intval($_POST['user_id']);

// Block suspended users
require_not_suspended($conn, $user_id);

// First, check the booking status
$checkSql = "SELECT status, user_id FROM bookings WHERE id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $booking_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
  echo json_encode([
    "success" => false,
    "message" => "Booking not found"
  ]);
  exit;
}

$booking = $result->fetch_assoc();

// Verify user owns this booking
if ($booking['user_id'] != $user_id) {
  echo json_encode([
    "success" => false,
    "message" => "You don't have permission to cancel this booking"
  ]);
  exit;
}

// Check if booking can be cancelled
$currentStatus = $booking['status'];
if (!in_array($currentStatus, ['pending', 'approved'])) {
  echo json_encode([
    "success" => false,
    "message" => "Cannot cancel booking. Current status: " . $currentStatus,
    "current_status" => $currentStatus
  ]);
  exit;
}

// Proceed with cancellation
$sql = "
UPDATE bookings
SET status = 'cancelled',
    cancelled_at = NOW(),
    cancellation_reason = 'Cancelled by renter'
WHERE id = ? 
  AND user_id = ?
  AND status IN ('pending', 'approved')
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $booking_id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
  echo json_encode([
    "success" => true,
    "message" => "Booking cancelled successfully"
  ]);
} else {
  echo json_encode([
    "success" => false,
    "message" => "Failed to cancel booking. Please try again.",
    "affected_rows" => $stmt->affected_rows
  ]);
}
