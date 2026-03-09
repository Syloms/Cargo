<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
require_once "include/db.php";

$notification_id = $_POST['id'] ?? 0;

if ($notification_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing notification ID"]);
    exit;
}

// Delete the notification (archive by removing)
$stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Notification archived"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to archive notification"]);
}

$stmt->close();
$conn->close();
?>