<?php
/**
 * Archive Notification
 * Archives a notification (currently implemented as delete)
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../../include/db.php";

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$notification_id = $_POST['id'] ?? $input['id'] ?? $_POST['notification_id'] ?? $input['notification_id'] ?? 0;

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
