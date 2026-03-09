<?php
session_start();
require_once 'include/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['fullname'] ?? 'Admin';

// -----------------------------------------------------------------------------
// Ensure admin_notifications table exists (self-healing for fresh/partial setups)
// -----------------------------------------------------------------------------
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'admin_notifications'");
$tableExists = $tableCheck && mysqli_num_rows($tableCheck) > 0;

if (!$tableExists) {
    // Create table if missing (minimal schema aligned with your DB dump)
    $createSql = "CREATE TABLE IF NOT EXISTS `admin_notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `admin_id` int(11) DEFAULT NULL,
        `type` enum('booking','payment','verification','report','car','user','system') NOT NULL DEFAULT 'system',
        `title` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `link` varchar(255) DEFAULT NULL,
        `icon` varchar(50) DEFAULT 'bi-bell',
        `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
        `read_status` enum('read','unread') DEFAULT 'unread',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `read_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    mysqli_query($conn, $createSql);
}

// If table exists but is empty, insert one welcome notification (helps verify UI)
$seedRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM admin_notifications");
if ($seedRes) {
    $seedCount = (int)(mysqli_fetch_assoc($seedRes)['c'] ?? 0);
    if ($seedCount === 0) {
        $welcomeTitle = 'Welcome to CarGo Admin';
        $welcomeMsg = 'Notifications will appear here when there are new payments to verify, verifications to review, reports, and system alerts.';
        $stmt = $conn->prepare("INSERT INTO admin_notifications (type, title, message, link, icon, priority, read_status, created_at) VALUES ('system', ?, ?, 'dashboard.php', 'bi-bell', 'low', 'unread', NOW())");
        if ($stmt) {
            $stmt->bind_param('ss', $welcomeTitle, $welcomeMsg);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status_filter = $_GET['status'] ?? 'all';
        $type_filter = $_GET['type'] ?? 'all';
        $priority_filter = $_GET['priority'] ?? 'all';

// Build WHERE clause
$where_clauses = [];
if ($status_filter !== 'all') {
    $where_clauses[] = "read_status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
if ($type_filter !== 'all') {
            $where_clauses[] = "type = '" . mysqli_real_escape_string($conn, $type_filter) . "'";
        }
        if ($priority_filter !== 'all') {
            $where_clauses[] = "priority = '" . mysqli_real_escape_string($conn, $priority_filter) . "'";
        }

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM admin_notifications $where_sql";
$count_result = mysqli_query($conn, $count_query);
if (!$count_result) {
    die('Notifications count query failed: ' . mysqli_error($conn));
}
$total_notifications = (int)(mysqli_fetch_assoc($count_result)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_notifications / $limit));

// Get notifications
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
        created_at,
        TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago,
        TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_ago,
        TIMESTAMPDIFF(DAY, created_at, NOW()) as days_ago
    FROM admin_notifications
    $where_sql
    ORDER BY 
        CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        created_at DESC
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);
if (!$result) {
    die('Notifications list query failed: ' . mysqli_error($conn));
}
$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN read_status = 'unread' THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high
    FROM admin_notifications
";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

function formatTimeAgo($notification) {
    $minutes = $notification['minutes_ago'];
    $hours = $notification['hours_ago'];
    $days = $notification['days_ago'];
    
    if ($minutes < 1) return 'Just now';
    if ($minutes < 60) return $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
    if ($hours < 24) return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - CarGo Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        /* Header */
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 0.5rem 0;
        }
        .page-subtitle {
            color: #666;
            font-size: 0.9375rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-label {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        
        /* Filters */
        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.875rem;
        }
        
        /* Notification List */
        .notification-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            gap: 1rem;
            align-items: start;
            transition: background 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        .notification-item:hover {
            background: #f8f9fa;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item.unread {
            background: #f8f9ff;
            border-left: 3px solid #667eea;
        }
        .notification-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .priority-urgent { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; }
        .priority-high { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .priority-medium { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .priority-low { background: linear-gradient(135deg, #e0e0e0 0%, #f0f0f0 100%); color: #666; }
        
        .notification-content {
            flex: 1;
        }
        .notification-title {
            font-weight: 600;
            font-size: 1rem;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .unread-dot {
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
        }
        .notification-message {
            font-size: 0.875rem;
            color: #666;
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }
        .notification-time {
            font-size: 0.8125rem;
            color: #999;
        }
        .notification-actions {
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .notification-item:hover .notification-actions {
            opacity: 1;
        }
        .notification-action-btn {
            padding: 0.375rem 0.75rem;
            background: #f5f5f5;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .notification-action-btn:hover {
            background: #e0e0e0;
        }
        .notification-action-btn.delete:hover {
            background: #fee;
            color: #dc3545;
        }
        
        /* Pagination */
        .pagination-wrapper {
            padding: 1.5rem;
            text-align: center;
            background: white;
            border-radius: 0 0 12px 12px;
        }
        
        /* Empty State */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }
        .empty-state i {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 1rem;
        }
        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }
        .empty-state p {
            color: #666;
            font-size: 0.9375rem;
        }
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-bell me-2"></i>Notifications
            </h1>
            <p class="page-subtitle">Manage all admin notifications and alerts</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="bi bi-bell-fill"></i>
                </div>
                <div class="stat-label">Total Notifications</div>
                <div class="stat-value"><?= number_format($stats['total']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <i class="bi bi-inbox-fill"></i>
                </div>
                <div class="stat-label">Unread</div>
                <div class="stat-value"><?= number_format($stats['unread']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="stat-label">Urgent</div>
                <div class="stat-value"><?= number_format($stats['urgent']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <i class="bi bi-flag-fill"></i>
                </div>
                <div class="stat-label">High Priority</div>
                <div class="stat-value"><?= number_format($stats['high']) ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-bar">
            <div>
                <i class="bi bi-funnel me-2"></i>
                <strong>Filters:</strong>
            </div>
            <select class="filter-select" id="statusFilter" onchange="applyFilters()">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="unread" <?= $status_filter === 'unread' ? 'selected' : '' ?>>Unread</option>
                <option value="read" <?= $status_filter === 'read' ? 'selected' : '' ?>>Read</option>
            </select>
            <select class="filter-select" id="typeFilter" onchange="applyFilters()">
                <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="booking" <?= $type_filter === 'booking' ? 'selected' : '' ?>>Bookings</option>
                <option value="payment" <?= $type_filter === 'payment' ? 'selected' : '' ?>>Payments</option>
                <option value="verification" <?= $type_filter === 'verification' ? 'selected' : '' ?>>Verifications</option>
                <option value="report" <?= $type_filter === 'report' ? 'selected' : '' ?>>Reports</option>
                <option value="car" <?= $type_filter === 'car' ? 'selected' : '' ?>>Car Listings</option>
                <option value="user" <?= $type_filter === 'user' ? 'selected' : '' ?>>Users</option>
                <option value="system" <?= $type_filter === 'system' ? 'selected' : '' ?>>System</option>
            </select>
            <select class="filter-select" id="priorityFilter" onchange="applyFilters()">
                <option value="all" <?= $priority_filter === 'all' ? 'selected' : '' ?>>All Priorities</option>
                <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $priority_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>High</option>
                <option value="urgent" <?= $priority_filter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
            </select>
            <div style="margin-left: auto;">
                <button class="btn btn-sm btn-outline-primary" onclick="markAllAsRead()">
                    <i class="bi bi-check-all me-1"></i>Mark All as Read
                </button>
            </div>
        </div>
        
        <!-- Notifications List -->
        <div class="notification-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! No notifications to display.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?= $notif['read_status'] ?>" 
                         data-id="<?= $notif['id'] ?>"
                         onclick="handleNotificationClick(<?= $notif['id'] ?>, '<?= htmlspecialchars($notif['link'] ?? '') ?>')">
                        <div class="notification-icon-wrapper priority-<?= $notif['priority'] ?>">
                            <i class="<?= htmlspecialchars($notif['icon'] ?? 'bi bi-bell') ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">
                                <?php if ($notif['read_status'] === 'unread'): ?>
                                    <span class="unread-dot"></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($notif['title']) ?>
                            </div>
                            <div class="notification-message">
                                <?= htmlspecialchars($notif['message']) ?>
                            </div>
                            <div class="notification-time">
                                <i class="bi bi-clock me-1"></i><?= formatTimeAgo($notif) ?>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if ($notif['read_status'] === 'unread'): ?>
                                <button class="notification-action-btn" onclick="event.stopPropagation(); markAsRead(<?= $notif['id'] ?>)">
                                    <i class="bi bi-check"></i> Mark Read
                                </button>
                            <?php endif; ?>
                            <button class="notification-action-btn delete" onclick="event.stopPropagation(); deleteNotification(<?= $notif['id'] ?>)">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&status=<?= $status_filter ?>&type=<?= $type_filter ?>&priority=<?= $priority_filter ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;
            const priority = document.getElementById('priorityFilter').value;
            window.location.href = `notifications.php?status=${status}&type=${type}&priority=${priority}`;
        }
        
        function handleNotificationClick(id, link) {
            // Mark as read
            markAsRead(id, false);
            
            // Navigate if link exists
            if (link) {
                setTimeout(() => {
                    window.location.href = link;
                }, 100);
            }
        }
        
        function markAsRead(id, reload = true) {
            const formData = new FormData();
            formData.append('notification_id', id);
            
            fetch('api/notifications/mark_as_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && reload) {
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function markAllAsRead() {
            if (!confirm('Mark all notifications as read?')) return;
            
            const formData = new FormData();
            formData.append('mark_all', 'true');
            
            fetch('api/notifications/mark_as_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function deleteNotification(id) {
            if (!confirm('Delete this notification?')) return;
            
            const formData = new FormData();
            formData.append('notification_id', id);
            
            fetch('api/notifications/delete_notification.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>