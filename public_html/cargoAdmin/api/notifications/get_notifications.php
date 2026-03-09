<?php
/**
 * Fetch Notifications
 * Returns a list of notifications for a specific user
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../../include/db.php";

// Validate user ID
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userIdRaw = $_GET['user_id'] ?? $input['user_id'] ?? $_GET['id'] ?? $input['id'] ?? $_GET['uid'] ?? $input['uid'] ?? $_GET['userId'] ?? $input['userId'] ?? $_GET['owner_id'] ?? $input['owner_id'] ?? $_GET['renter_id'] ?? $input['renter_id'] ?? null;
$emailRaw = $_GET['email'] ?? $input['email'] ?? $_GET['emailAddress'] ?? $input['emailAddress'] ?? null;

if ($userIdRaw === null || !is_numeric($userIdRaw)) {
    if ($emailRaw !== null) {
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
        "notifications" => []
    ]);
    exit;
}

$user_id = intval($userIdRaw);

// Fetch notifications
$stmt = $conn->prepare("
    SELECT id, title, message, read_status, created_at, type, related_id 
    FROM notifications 
    WHERE user_id=? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch unread count
$stmt2 = $conn->prepare("
    SELECT COUNT(*) AS unread 
    FROM notifications 
    WHERE user_id=? AND read_status='unread'
");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result()->fetch_assoc();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// Send response
echo json_encode([
    "status" => "success",
    "unread_count" => $result2["unread"],
    "notifications" => $notifications
]);

$stmt->close();
$stmt2->close();
$conn->close();
?>
