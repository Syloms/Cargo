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

if (empty($booking_id) || empty($admin_id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: booking_id, admin_id"
    ]);
    exit;
}

// Update booking with verification
$stmt = $conn->prepare("
    UPDATE bookings 
    SET mileage_verified_by = ?,
        mileage_verified_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("ii", $admin_id, $booking_id);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to verify mileage: " . $stmt->error
    ]);
    exit;
}

// Log the verification
$log_stmt = $conn->prepare("
    INSERT INTO mileage_logs 
    (booking_id, log_type, recorded_by, recorded_by_type, notes, created_at)
    VALUES (?, 'admin_verified', ?, 'admin', 'Mileage verified by admin', NOW())
");
$log_stmt->bind_param("ii", $booking_id, $admin_id);
$log_stmt->execute();

// Get booking details for notification
$booking_stmt = $conn->prepare("
    SELECT user_id, owner_id, excess_mileage, excess_mileage_fee 
    FROM bookings 
    WHERE id = ?
");
$booking_stmt->bind_param("i", $booking_id);
$booking_stmt->execute();
$booking = $booking_stmt->get_result()->fetch_assoc();

// Send notifications
if ($booking) {
    // Notify renter
    $notify_renter = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, 'Mileage Verified ✓', ?, 'info', NOW())
    ");
    $renter_message = $booking['excess_mileage'] > 0 
        ? "Your mileage has been verified. Excess fee: ₱" . number_format($booking['excess_mileage_fee'], 2)
        : "Your mileage has been verified. No excess charges.";
    $notify_renter->bind_param("is", $booking['user_id'], $renter_message);
    $notify_renter->execute();

    // Notify owner
    $notify_owner = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, 'Mileage Verified ✓', ?, 'info', NOW())
    ");
    $owner_message = "Booking #$booking_id mileage has been verified by admin.";
    $notify_owner->bind_param("is", $booking['owner_id'], $owner_message);
    $notify_owner->execute();
}

echo json_encode([
    "status" => "success",
    "message" => "Mileage verified successfully"
]);

$conn->close();
?>
