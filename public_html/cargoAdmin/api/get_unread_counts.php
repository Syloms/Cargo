<?php
require_once __DIR__ . "/../include/db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Validate user ID
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userIdRaw = $_GET['user_id'] ?? $input['user_id'] ?? $_GET['id'] ?? $input['id'] ?? $_GET['uid'] ?? $input['uid'] ?? $_GET['userId'] ?? $input['userId'] ?? $_GET['owner_id'] ?? $input['owner_id'] ?? $_GET['renter_id'] ?? $input['renter_id'] ?? null;
$emailRaw = $_GET['email'] ?? $input['email'] ?? $_GET['emailAddress'] ?? $input['emailAddress'] ?? null;
if ($userIdRaw === null || !is_numeric($userIdRaw)) {
    if ($emailRaw !== null) {
        require_once __DIR__ . "/../include/db.php";
        $lookup = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $lookup->bind_param("s", $emailRaw);
        $lookup->execute();
        $res = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!empty($res['id'])) {
            $userIdRaw = $res['id'];
        }
    }
}
if ($userIdRaw === null || !is_numeric($userIdRaw)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing or invalid user_id",
        "unread_count" => 0
    ]);
    exit;
}

$user_id = intval($userIdRaw);

try {
    // Fetch unread notifications count
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unread 
        FROM notifications 
        WHERE user_id = ? AND read_status = 'unread'
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Send success response
    echo json_encode([
        "status" => "success",
        "unread_count" => intval($result["unread"])
    ]);
    
    $stmt->close();
} catch (Exception $e) {
    // Send error response
    echo json_encode([
        "status" => "error",
        "message" => "Failed to fetch unread count: " . $e->getMessage(),
        "unread_count" => 0
    ]);
}

$conn->close();
?>
