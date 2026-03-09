<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once "include/db.php";

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$emailRaw = $_GET["email"] ?? $input["email"] ?? $_GET["emailAddress"] ?? $input["emailAddress"] ?? null;
if (!isset($_GET["user_id"]) && !isset($_GET["id"]) && !isset($_GET["uid"]) && !isset($_GET["userId"]) && !isset($_GET["owner_id"]) && !isset($_GET["renter_id"]) && !isset($input["user_id"]) && !isset($input["id"]) && !isset($input["uid"]) && !isset($input["userId"]) && !isset($input["owner_id"]) && !isset($input["renter_id"]) && $emailRaw === null) {
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit;
}

$uidRaw = $_GET["user_id"] ?? $input["user_id"] ?? $_GET["id"] ?? $input["id"] ?? $_GET["uid"] ?? $input["uid"] ?? $_GET["userId"] ?? $input["userId"] ?? $_GET["owner_id"] ?? $input["owner_id"] ?? $_GET["renter_id"] ?? $input["renter_id"] ?? 0;
if ((!is_numeric($uidRaw) || intval($uidRaw) <= 0) && $emailRaw !== null) {
    $lookup = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $lookup->bind_param("s", $emailRaw);
    $lookup->execute();
    $res = $lookup->get_result()->fetch_assoc();
    $lookup->close();
    if (!empty($res['id'])) {
        $uidRaw = $res['id'];
    }
}
$user_id = intval($uidRaw);

// Fetch notifications
$sql = "SELECT id, title, message, read_status, created_at 
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];

function detectType($title) {
    $title = strtolower($title);

    if (str_contains($title, "approved")) return "booking_approved";
    if (str_contains($title, "declined")) return "booking_declined";
    if (str_contains($title, "returned")) return "car_returned";
    if (str_contains($title, "message")) return "new_message";
    if (str_contains($title, "payment") && str_contains($title, "successful")) return "payment_success";
    if (str_contains($title, "payment") && str_contains($title, "failed")) return "payment_failed";
    if (str_contains($title, "refund")) return "refund_processed";
    if (str_contains($title, "announce")) return "system_announcement";
    if (str_contains($title, "pending")) return "verification_pending";
    if (str_contains($title, "reminder")) return "booking_reminder";

    return "info"; // default
}

function iconForType($type) {
    return match($type) {
      "booking_approved"      => "✓",   
    "booking_declined"      => "✕",   
    "car_returned"          => "⚐",   
    "new_message"           => "✉",  
    "payment_success"       => "✓",   
    "payment_failed"        => "!",  
    "refund_processed"      => "↻",   
    "system_announcement"   => "⚑",  
    "verification_pending"  => "⏱",  
    "booking_reminder"      => "⏰",  
    default                 => "•"   
    };
}

while ($row = $result->fetch_assoc()) {

    $type = detectType($row["title"]);
    $emoji = iconForType($type);

    $notifications[] = [
        "id"        => $row["id"],
        "title"     => $emoji . " " . $row["title"],
        "message"   => $row["message"],
        "date"      => date("M d", strtotime($row["created_at"])),
        "time"      => date("h:i A", strtotime($row["created_at"])),
        "type"      => $type,
        "isRead"    => $row["read_status"] === "read"
    ];
}

// Get unread count
$unreadStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 'unread'");
$unreadStmt->bind_param("i", $user_id);
$unreadStmt->execute();
$unreadResult = $unreadStmt->get_result()->fetch_assoc();
$unreadCount = $unreadResult['count'] ?? 0;
$unreadStmt->close();

echo json_encode([
    "status" => "success",
    "notifications" => $notifications,
    "unread_count" => $unreadCount
], JSON_UNESCAPED_UNICODE); // IMPORTANT for emojis

$stmt->close();
$conn->close();
?>
