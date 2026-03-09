<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php'; // your DB connection (mysqli $conn)

$user_id = $_POST['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "Missing user_id"]);
    exit;
}

$stmt = $conn->prepare("SELECT id, fullname, email, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // If profile_image is relative, prepend full uploads path
    if (!empty($row["profile_image"]) && !preg_match("~^https?://~", $row["profile_image"])) {
        if (!defined('UPLOADS_URL')) {
            require_once __DIR__ . '/include/config.php';
        }
        $row["profile_image"] = UPLOADS_URL . '/profile_images/' . $row["profile_image"];
    }

    echo json_encode([
        "success" => true,
        "user" => $row
    ]);
} else {
    echo json_encode(["success" => false, "message" => "User not found"]);
}

$stmt->close();
$conn->close();
