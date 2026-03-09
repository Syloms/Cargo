<?php
/**
 * ============================================================================
 * MARK ALL NOTIFICATIONS AS READ
 * Marks all unread notifications as read for a specific user
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

// Get user ID
$user_id = $_POST['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

// Update all notifications for this user
$stmt = $conn->prepare("
    UPDATE notifications 
    SET read_status = 'read' 
    WHERE user_id = ? AND read_status = 'unread'
");

$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $affected_rows = $stmt->affected_rows;
    
    echo json_encode([
        'success' => true,
        'message' => "$affected_rows notification(s) marked as read",
        'updated_count' => $affected_rows
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update notifications: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>
