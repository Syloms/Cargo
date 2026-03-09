<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

include "../../include/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit;
}

$booking_id = $_POST['booking_id'] ?? null;
$admin_id = $_POST['admin_id'] ?? null;
$notes = $_POST['notes'] ?? '';

if (empty($booking_id) || empty($admin_id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: booking_id, admin_id"
    ]);
    exit;
}

// Update booking with notes
$stmt = $conn->prepare("
    UPDATE bookings 
    SET mileage_notes = ?
    WHERE id = ?
");
$stmt->bind_param("si", $notes, $booking_id);
$stmt->execute();

// Log the flag
$log_stmt = $conn->prepare("
    INSERT INTO mileage_logs 
    (booking_id, log_type, recorded_by, recorded_by_type, notes, created_at)
    VALUES (?, 'admin_verified', ?, 'admin', ?, NOW())
");
$log_stmt->bind_param("iis", $booking_id, $admin_id, $notes);
$log_stmt->execute();

// Get booking details
$booking_stmt = $conn->prepare("
    SELECT user_id, owner_id FROM bookings WHERE id = ?
");
$booking_stmt->bind_param("i", $booking_id);
$booking_stmt->execute();
$booking = $booking_stmt->get_result()->fetch_assoc();

// Notify renter
if ($booking) {
    $notify = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, 'Mileage Needs Review ⚠', ?, 'warning', NOW())
    ");
    $message = "Your mileage reading for booking #$booking_id has been flagged for review. Please check with support.";
    $notify->bind_param("is", $booking['user_id'], $message);
    $notify->execute();
}

echo json_encode([
    "status" => "success",
    "message" => "Booking flagged for review"
]);

$conn->close();
?>
