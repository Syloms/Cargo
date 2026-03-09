<?php
/**
 * Test Delete Notification API
 * This script helps debug the delete notification functionality
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../include/db.php';

echo "<h1>🔍 Delete Notification API Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .test { margin: 20px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; font-weight: bold; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
    h2 { color: #333; margin-top: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #f0f0f0; }
</style>";

// Get test notification
echo "<div class='test'>";
echo "<h2>🔍 Database Connection Test</h2>";
if ($conn) {
    echo "<span class='success'>✅ Database connected successfully</span><br>";
} else {
    echo "<span class='error'>❌ Database connection failed</span><br>";
    echo "</div>";
    exit;
}

// Check if notifications table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($tableCheck->num_rows > 0) {
    echo "<span class='success'>✅ Notifications table exists</span><br>";
} else {
    echo "<span class='error'>❌ Notifications table does not exist</span><br>";
    echo "</div>";
    exit;
}

// Count total notifications
$countQuery = "SELECT COUNT(*) as total FROM notifications";
$countResult = $conn->query($countQuery);
$countRow = $countResult->fetch_assoc();
echo "<span class='info'>ℹ️ Total notifications in database: {$countRow['total']}</span><br>";
echo "</div>";

// Get test notification
$testQuery = "SELECT n.*, u.name as user_name 
              FROM notifications n 
              LEFT JOIN users u ON n.user_id = u.id 
              ORDER BY n.id DESC 
              LIMIT 5";
$result = $conn->query($testQuery);

if (!$result) {
    echo "<div class='test'>";
    echo "<span class='error'>❌ Query Error: " . $conn->error . "</span>";
    echo "</div>";
    exit;
}

echo "<div class='test'>";
echo "<h2>📋 Available Test Notifications</h2>";

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>User ID</th><th>User Name</th><th>Title</th><th>Status</th><th>Action</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>" . ($row['user_name'] ?? 'Unknown') . "</td>";
        echo "<td>" . substr($row['title'], 0, 50) . "...</td>";
        echo "<td>{$row['read_status']}</td>";
        echo "<td><a href='?test_delete={$row['id']}&user_id={$row['user_id']}'>Test Delete</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<span class='error'>❌ No notifications found in database.</span>";
}
echo "</div>";

// Test delete if requested
if (isset($_GET['test_delete']) && isset($_GET['user_id'])) {
    $test_notif_id = intval($_GET['test_delete']);
    $test_user_id = intval($_GET['user_id']);
    
    echo "<div class='test'>";
    echo "<h2>🧪 Testing Delete API</h2>";
    
    // Simulate the API call
    echo "<h3>Step 1: Verify Notification Exists</h3>";
    $verify_stmt = $conn->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
    $verify_stmt->bind_param("ii", $test_notif_id, $test_user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        echo "<span class='success'>✅ Notification found</span><br>";
        echo "<pre>";
        echo "Notification ID: $test_notif_id\n";
        echo "User ID: $test_user_id";
        echo "</pre>";
    } else {
        echo "<span class='error'>❌ Notification not found or doesn't belong to user</span>";
        echo "</div>";
        exit;
    }
    $verify_stmt->close();
    
    echo "<h3>Step 2: Simulate API Request</h3>";
    echo "<pre>";
    echo "POST /api/notifications/delete_user_notification.php\n";
    echo "notification_id: $test_notif_id\n";
    echo "user_id: $test_user_id";
    echo "</pre>";
    
    echo "<h3>Step 3: Execute Delete (DRY RUN - Not Actually Deleting)</h3>";
    $delete_stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
    $delete_stmt->bind_param("ii", $test_notif_id, $test_user_id);
    $delete_stmt->execute();
    $delete_result = $delete_stmt->get_result();
    
    if ($delete_result->num_rows > 0) {
        echo "<span class='info'>ℹ️ Would delete this notification:</span><br>";
        $notif = $delete_result->fetch_assoc();
        echo "<pre>";
        print_r($notif);
        echo "</pre>";
        echo "<span class='success'>✅ API would return: {\"success\": true, \"message\": \"Notification deleted successfully\", \"deleted_id\": $test_notif_id}</span>";
    }
    $delete_stmt->close();
    
    echo "<h3>Step 4: Actual API Endpoint</h3>";
    echo "<p>The actual delete API is at:</p>";
    echo "<pre>" . $_SERVER['HTTP_HOST'] . "/cargoAdmin/api/notifications/delete_user_notification.php</pre>";
    
    echo "<h3>Step 5: Test with cURL</h3>";
    echo "<pre>";
    echo "curl -X POST 'http://{$_SERVER['HTTP_HOST']}/cargoAdmin/api/notifications/delete_user_notification.php' \\\n";
    echo "  -d 'notification_id=$test_notif_id' \\\n";
    echo "  -d 'user_id=$test_user_id'";
    echo "</pre>";
    
    echo "</div>";
}

// Check API endpoint accessibility
echo "<div class='test'>";
echo "<h2>🔗 API Endpoint Check</h2>";

$api_path = __DIR__ . '/delete_user_notification.php';
if (file_exists($api_path)) {
    echo "<span class='success'>✅ delete_user_notification.php exists</span><br>";
    echo "File size: " . filesize($api_path) . " bytes<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($api_path)), -4) . "<br>";
} else {
    echo "<span class='error'>❌ delete_user_notification.php NOT FOUND</span><br>";
}

// Test the API directly
echo "<h3>Direct API Test</h3>";
$api_url = "http://" . $_SERVER['HTTP_HOST'] . "/cargoAdmin/api/notifications/delete_user_notification.php";
echo "API URL: <code>$api_url</code><br>";

echo "</div>";

// Common issues
echo "<div class='test'>";
echo "<h2>❓ Troubleshooting Guide</h2>";
echo "<h3>If delete is not working, check:</h3>";
echo "<ol>";
echo "<li><strong>API URL Configuration</strong>: Check GlobalApiConfig.apiUrl in Flutter app</li>";
echo "<li><strong>Network Request</strong>: Check if the request is reaching the server (check server logs)</li>";
echo "<li><strong>Response Parsing</strong>: Check if the response is being parsed correctly in Flutter</li>";
echo "<li><strong>Database Permissions</strong>: Ensure the database user has DELETE permissions</li>";
echo "<li><strong>CORS Headers</strong>: Check if CORS is blocking the request</li>";
echo "</ol>";

echo "<h3>Debug Steps for Flutter App:</h3>";
echo "<pre>";
echo "1. Add this to your code before the API call:
   print('🔍 Deleting notification ID: \$notificationId');
   print('🔍 User ID: \$_loadedUserId');
   print('🔍 API URL: \${GlobalApiConfig.apiUrl}/notifications/delete_user_notification.php');

2. After the API call:
   print('📥 Response status: \${response.statusCode}');
   print('📥 Response body: \${response.body}');
   
3. Check the console logs when you swipe to delete
";
echo "</pre>";

echo "<h3>Check Flutter Console for:</h3>";
echo "<ul>";
echo "<li>❌ Delete Error: [error message] - API call failed</li>";
echo "<li>📥 Delete Response: {json} - See what the server returned</li>";
echo "<li>Connection timeout - Network issue</li>";
echo "</ul>";

echo "</div>";

$conn->close();
?>
