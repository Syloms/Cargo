<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
// Accept both field names for backwards/forwards compatibility
$token = $_POST['token'] ?? ($_POST['fcm_token'] ?? '');
$token = is_string($token) ? trim($token) : '';

if ($user_id <= 0 || $token === '') {
    echo json_encode(["status" => "error", "message" => "Missing user_id or token"]);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
$stmt->bind_param("si", $token, $user_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to save token"]);
}

$stmt->close();
$conn->close();
?>
