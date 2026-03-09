<?php
/**
 * ============================================================================
 * MARK NOTIFICATION AS READ
 * Marks one or more notifications as read for a specific user
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

// Start session only if not already started (works for both admin + user context)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$notification_id = $_POST['notification_id'] ?? null;
$user_id = $_POST['user_id'] ?? null;
$mark_all = isset($_POST['mark_all']) && $_POST['mark_all'] === 'true';

// ============================================================================
// ADMIN CONTEXT (admin_notifications)
// If an admin is logged in, we operate on admin_notifications and do NOT require user_id.
// This fixes the admin top-bar dropdown actions.
// ============================================================================
if (!empty($_SESSION['admin_id'])) {
    $admin_id = (int)$_SESSION['admin_id'];

    if ($mark_all) {
        $stmt = $conn->prepare("UPDATE admin_notifications SET read_status = 'read', read_at = NOW() WHERE read_status = 'unread'");
        if ($stmt && $stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            $conn->close();
            echo json_encode(['success' => true, 'message' => "$affected_rows admin notification(s) marked as read", 'updated_count' => $affected_rows]);
            exit;
        }

        $error = $conn->error;
        if ($stmt) $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update admin notifications: ' . $error]);
        exit;
    }

    if ($notification_id) {
        $stmt = $conn->prepare("UPDATE admin_notifications SET read_status = 'read', read_at = NOW() WHERE id = ?");
        $id = (int)$notification_id;
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            echo json_encode(['success' => true, 'message' => 'Admin notification marked as read', 'notification_id' => $id]);
            exit;
        }

        $error = $conn->error;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update admin notification: ' . $error]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Missing notification_id']);
    exit;
}

// ============================================================================
// USER CONTEXT (notifications)
// Backwards-compatible behavior for app users.
// ============================================================================

// Validate user_id
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

if ($mark_all) {
    // Mark all unread notifications as read for this user
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET read_status = 'read' 
        WHERE user_id = ? AND read_status = 'unread'
    ");
    
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => "$affected_rows notification(s) marked as read",
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
    // Mark single notification as read
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
        SET read_status = 'read' 
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->bind_param("ii", $notification_id, $user_id);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read',
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
