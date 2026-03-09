<?php
/**
 * Mark Notification as Read
 * Marks one or multiple notifications as read
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../include/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $notificationIdRaw = $_POST['notification_id'] ?? $input['notification_id'] ?? 0;
    $notificationId = intval($notificationIdRaw);
    $userIdRaw = $_POST['user_id'] ?? $input['user_id'] ?? $_POST['id'] ?? $input['id'] ?? $_POST['uid'] ?? $input['uid'] ?? $_POST['userId'] ?? $input['userId'] ?? $_POST['owner_id'] ?? $input['owner_id'] ?? $_POST['renter_id'] ?? $input['renter_id'] ?? 0;
    $userId = intval($userIdRaw);
    $emailRaw = $_POST['email'] ?? $input['email'] ?? $_POST['emailAddress'] ?? $input['emailAddress'] ?? '';
    if ($userId <= 0 && !empty($emailRaw)) {
        $lookup = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $lookup->bind_param("s", $emailRaw);
        $lookup->execute();
        $res = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!empty($res['id'])) {
            $userId = intval($res['id']);
        }
    }
    $markAllRaw = $_POST['mark_all'] ?? $input['mark_all'] ?? false;
    $markAll = filter_var($markAllRaw, FILTER_VALIDATE_BOOLEAN);

    if ($userId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid user ID'
        ]);
        exit;
    }

    if ($markAll) {
        // Mark all notifications as read for this user
        $query = "UPDATE notifications SET read_status = 'read' WHERE user_id = ? AND read_status = 'unread'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
    } else {
        if ($notificationId <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid notification ID'
            ]);
            exit;
        }

        // Mark specific notification as read
        $query = "UPDATE notifications SET read_status = 'read' WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $notificationId, $userId);
    }

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        
        echo json_encode([
            'success' => true,
            'message' => $markAll ? 'All notifications marked as read' : 'Notification marked as read',
            'affected_rows' => $affectedRows
        ]);
    } else {
        throw new Exception('Failed to update notification: ' . $conn->error);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
