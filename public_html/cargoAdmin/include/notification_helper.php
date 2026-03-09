<?php
/**
 * Helper function to create admin notifications
 */
function createAdminNotification($conn, $type, $title, $message, $link = null, $priority = 'medium', $icon = 'bi-bell') {
    $stmt = $conn->prepare("
        INSERT INTO admin_notifications (type, title, message, link, icon, priority, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param('ssssss', $type, $title, $message, $link, $icon, $priority);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Notification types and their default icons
 */
function getNotificationIcon($type) {
    $icons = [
        'booking' => 'bi-calendar-check',
        'payment' => 'bi-cash-coin',
        'verification' => 'bi-shield-check',
        'report' => 'bi-flag',
        'car' => 'bi-car-front',
        'user' => 'bi-person',
        'system' => 'bi-gear'
    ];
    
    return $icons[$type] ?? 'bi-bell';
}
?>