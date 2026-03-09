<?php
/**
 * Delete User Notification
 * Permanently removes a notification
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
$notification_id = $_POST['notification_id'] ?? $input['notification_id'] ?? $_POST['id'] ?? $input['id'] ?? 0;
$user_id = $_POST['user_id'] ?? $input['user_id'] ?? 0;

if ($notification_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing notification ID"]);
    exit;
}

// If user_id is provided, verify ownership
if ($user_id > 0) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
} else {
    // Fallback if no user_id (less secure, but supports legacy calls if any)
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
}

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Notification deleted"]);
    } else {
        echo json_encode(["success" => false, "message" => "Notification not found or access denied"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Failed to delete notification: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>
