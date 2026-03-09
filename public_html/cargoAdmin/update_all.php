<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
require_once "include/db.php";

$user_id = $_POST['user_id'] ?? 0;

if ($user_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing user_id"]);
    exit;
}

// Mark all notifications as read for this user
$stmt = $conn->prepare("UPDATE notifications SET read_status = 'read' WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true, 
        "message" => "All notifications marked as read",
        "updated_count" => $stmt->affected_rows
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update notifications"]);
}

$stmt->close();
$conn->close();
?>