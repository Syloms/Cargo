<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
require_once "include/db.php";

$user_id = $_POST['user_id'] ?? 0;

if ($user_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing user_id"]);
    exit;
}

$stmt = $conn->prepare("UPDATE notifications SET read_status = 'read' WHERE user_id = ? AND read_status = 'unread'");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    echo json_encode([
        "success" => true, 
        "message" => "All notifications marked as read",
        "updated_count" => $affected
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update notifications"]);
}

$stmt->close();
$conn->close();
?>