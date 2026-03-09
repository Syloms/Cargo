<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");

include "include/db.php";

$user_id     = $_POST['user_id'] ?? '';
$old_pass    = $_POST['old_password'] ?? '';
$new_pass    = $_POST['new_password'] ?? '';

if (!$user_id || !$old_pass || !$new_pass) {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit;
}

// Fetch current password
$stmt = $conn->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
}

$data = $result->fetch_assoc();
$currentPassword = $data["password"];

// Check if old password matches (plain text comparison)
if ($old_pass !== $currentPassword) {
    echo json_encode(["success" => false, "message" => "Incorrect current password"]);
    exit;
}

// Update password (plain text)
$stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
$stmt2->bind_param("si", $new_pass, $user_id);

if ($stmt2->execute()) {
    echo json_encode(["success" => true, "message" => "Password updated successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update password"]);
}

$stmt2->close();
$conn->close();
?>
