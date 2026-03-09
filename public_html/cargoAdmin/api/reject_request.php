<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../include/db.php";

$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$reason     = isset($_POST['reason']) ? trim($_POST['reason']) : "";

if ($booking_id <= 0 || empty($reason)) {
    echo json_encode(["success" => false, "message" => "Missing booking_id or reason"]);
    exit;
}

// Update booking status only
$update = $conn->prepare("
    UPDATE bookings 
    SET status='rejected'
    WHERE id=? AND status='pending'
    LIMIT 1
");
$update->bind_param("i", $booking_id);
$update->execute();

if ($update->affected_rows <= 0) {
    echo json_encode(["success" => false, "message" => "Booking not found or not pending"]);
    exit;
}
$update->close();

// Fetch booking data for notifications - Support both cars and motorcycles
$q = $conn->prepare("
    SELECT b.*, 
           b.vehicle_type,
           COALESCE(c.brand, m.brand) AS car_name,
           COALESCE(c.model, m.model) AS model
    FROM bookings b
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    WHERE b.id=? LIMIT 1
");
$q->bind_param("i", $booking_id);
$q->execute();
$res = $q->get_result();
$booking = $res->fetch_assoc();
$q->close();

if (!$booking) {
    echo json_encode(["success" => false, "message" => "Booking not found"]);
    exit;
}

$renter_id = $booking['user_id'];   // renter
$owner_id  = $booking['owner_id'];  // owner
$vehicle_name = trim($booking['car_name'] . ' ' . $booking['model']);
$vehicle_type = ucfirst($booking['vehicle_type'] ?? 'vehicle');

// Notification for renter
$title_r = "Booking Rejected";
$msg_r   = "Your booking for {$vehicle_name} ({$vehicle_type}) was rejected. Reason: {$reason}";
$type_r  = "booking_rejected";

$notifR = $conn->prepare("
    INSERT INTO notifications (user_id, title, message, type, read_status, created_at)
    VALUES (?, ?, ?, ?, 'unread', NOW())
");
$notifR->bind_param("isss", $renter_id, $title_r, $msg_r, $type_r);
$notifR->execute();
$notifR->close();

// Owner log notification
$title_o = "Booking Rejected";
$msg_o   = "You rejected booking #{$booking_id} for {$vehicle_name} ({$vehicle_type}).";
$type_o  = "booking_update";

$notifO = $conn->prepare("
    INSERT INTO notifications (user_id, title, message, type, read_status, created_at)
    VALUES (?, ?, ?, ?, 'unread', NOW())
");
$notifO->bind_param("isss", $owner_id, $title_o, $msg_o, $type_o);
$notifO->execute();
$notifO->close();

echo json_encode([
    "success" => true,
    "message" => "Booking rejected successfully.",
    "booking_id" => $booking_id
]);

$conn->close();
?>
