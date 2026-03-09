<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ✅ FIXED: Changed from '../config.php' to '../../include/db.php'
require_once '../../include/db.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Unread Notifications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 'unread'");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread_notifications = $result->fetch_assoc()['count'];

    // Unread Messages (implement based on your messaging system)
    $unread_messages = 0;

    echo json_encode([
        'success' => true,
        'unread_notifications' => $unread_notifications,
        'unread_messages' => $unread_messages
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>