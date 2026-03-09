<?php
/**
 * ============================================================================
 * DELETE ADMIN NOTIFICATION
 * Permanently deletes an admin notification
 * ============================================================================
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../include/db.php';


if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$notification_id = $_POST['notification_id'] ?? null;

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Missing notification_id']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM admin_notifications WHERE id = ?");

$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Notification deleted successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete notification: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
