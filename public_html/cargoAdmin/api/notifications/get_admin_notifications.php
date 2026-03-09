<?php
session_start();
header('Content-Type: application/json');
require_once '../../include/db.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$status = $_GET['status'] ?? 'unread';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

$where = "WHERE 1=1";
if ($status === 'unread') {
    $where .= " AND read_status = 'unread'";
} elseif ($status === 'read') {
    $where .= " AND read_status = 'read'";
}

$query = "
    SELECT 
        id,
        type,
        title,
        message,
        link,
        icon,
        priority,
        read_status,
        created_at
    FROM admin_notifications
    $where
    ORDER BY 
        CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        created_at DESC
    LIMIT ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $limit);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $time = strtotime($row['created_at']);
    $diff = time() - $time;
    
    if ($diff < 60) {
        $timeAgo = 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        $timeAgo = $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        $timeAgo = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        $timeAgo = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    
    $row['time_ago'] = $timeAgo;
    $notifications[] = $row;
}

$countQuery = "SELECT COUNT(*) as count FROM admin_notifications WHERE read_status = 'unread'";
$countResult = $conn->query($countQuery);
$unreadCount = $countResult->fetch_assoc()['count'];

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount,
    'total' => count($notifications)
]);

$stmt->close();
$conn->close();
?>