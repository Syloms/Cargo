<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../include/db.php";

$notification_id = $_POST['notification_id'] ?? 0;

if ($notification_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing notification_id"]);
    exit;
}

$stmt = $conn->prepare("UPDATE notifications SET read_status = 'read' WHERE id = ?");
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Notification marked as read"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update notification"]);
}

$stmt->close();
$conn->close();
?>