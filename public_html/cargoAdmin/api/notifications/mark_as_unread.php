<?php
/**
 * ============================================================================
 * MARK NOTIFICATION AS UNREAD
 * Marks one or more notifications as unread for a specific user
 * ============================================================================
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$notification_id = $_POST['notification_id'] ?? null;
$user_id = $_POST['user_id'] ?? null;
$mark_all = isset($_POST['mark_all']) && $_POST['mark_all'] === 'true';

// Validate user_id
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

if ($mark_all) {
    // Mark all read notifications as unread for this user
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET read_status = 'unread' 
        WHERE user_id = ? AND read_status = 'read'
    ");
    
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => "$affected_rows notification(s) marked as unread",
            'updated_count' => $affected_rows
        ]);
    } else {
        $error = $conn->error;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update notifications: ' . $error
        ]);
    }
} elseif ($notification_id) {
    // Mark single notification as unread
    // First verify the notification belongs to the user
    $verify_stmt = $conn->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
    $verify_stmt->bind_param("ii", $notification_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $verify_stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => false, 
            'message' => 'Notification not found or you do not have permission'
        ]);
        exit;
    }
    $verify_stmt->close();
    
    // Update the notification
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET read_status = 'unread' 
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->bind_param("ii", $notification_id, $user_id);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as unread',
            'notification_id' => $notification_id
        ]);
    } else {
        $error = $conn->error;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update notification: ' . $error
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing notification_id']);
}
?>
