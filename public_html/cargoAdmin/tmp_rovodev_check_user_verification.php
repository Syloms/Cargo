<?php
// Simple verification checker - just provide user_id as query parameter
// Usage: tmp_rovodev_check_user_verification.php?user_id=5

header('Content-Type: text/html; charset=utf-8');
require_once 'include/db.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo "<h2>User Verification Checker</h2>";
    echo "<form method='GET'>";
    echo "Enter User ID: <input type='number' name='user_id' required>";
    echo "<button type='submit'>Check</button>";
    echo "</form>";
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Verification Status for User #<?= $user_id ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
        .verified { background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 4px; }
        .not-verified { background: #f8d7da; color: #721c24; padding: 5px 10px; border-radius: 4px; }
        .pending { background: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 4px; }
        .code { background: #f4f4f4; padding: 10px; border-left: 3px solid #4CAF50; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-left: 3px solid #ffc107; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Verification Status for User #<?= htmlspecialchars($user_id) ?></h2>
        
        <?php
        // Get user info
        $userQuery = "SELECT id, fullname, email, role FROM users WHERE id = ?";
        $stmt = $conn->prepare($userQuery);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $userResult = $stmt->get_result();
        $user = $userResult->fetch_assoc();
        
        if (!$user) {
            echo "<p class='not-verified'>❌ User not found!</p>";
            exit;
        }
        
        echo "<h3>User Information</h3>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>ID</td><td>{$user['id']}</td></tr>";
        echo "<tr><td>Name</td><td>{$user['fullname']}</td></tr>";
        echo "<tr><td>Email</td><td>{$user['email']}</td></tr>";
        echo "<tr><td>Role</td><td>{$user['role']}</td></tr>";
        echo "</table>";
        
        // Get verification records
        $verifyQuery = "SELECT * FROM user_verifications WHERE user_id = ? ORDER BY created_at DESC";
        $stmt2 = $conn->prepare($verifyQuery);
        $stmt2->bind_param('i', $user_id);
        $stmt2->execute();
        $verifyResult = $stmt2->get_result();
        
        echo "<h3>Verification Records</h3>";
        
        if ($verifyResult->num_rows == 0) {
            echo "<p class='not-verified'>❌ No verification records found for this user.</p>";
            echo "<div class='warning'>⚠️ <strong>This means:</strong> The user has never submitted verification documents.</div>";
        } else {
            echo "<table>";
            echo "<tr><th>ID</th><th>Status</th><th>Status (raw)</th><th>Submitted</th><th>Verified At</th></tr>";
            
            while ($row = $verifyResult->fetch_assoc()) {
                $statusRaw = $row['status'];
                $statusClean = trim(strtolower($statusRaw));
                
                $statusClass = 'pending';
                if ($statusClean === 'approved') $statusClass = 'verified';
                elseif ($statusClean === 'rejected') $statusClass = 'not-verified';
                
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td><span class='$statusClass'>" . ucfirst($statusClean) . "</span></td>";
                echo "<td style='font-family: monospace;'>'{$statusRaw}'</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "<td>" . ($row['verified_at'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Test the API query
        echo "<h3>API Test Results</h3>";
        
        $apiQuery = "SELECT status, verified_at
                     FROM user_verifications
                     WHERE user_id = ?
                     AND TRIM(LOWER(status)) = 'approved'
                    LIMIT 1";
        $stmt3 = $conn->prepare($apiQuery);
        $stmt3->bind_param('i', $user_id);
        $stmt3->execute();
        $apiResult = $stmt3->get_result();
        $apiData = $apiResult->fetch_assoc();
        
        if ($apiData) {
            echo "<p class='verified'>✅ <strong>API Returns: VERIFIED</strong></p>";
            echo "<div class='code'>";
            echo "<strong>Response:</strong><br>";
            echo json_encode([
                'is_verified' => true,
                'can_add_car' => true,
                'message' => 'Account verified',
                'verified_at' => $apiData['verified_at']
            ], JSON_PRETTY_PRINT);
            echo "</div>";
            echo "<div class='warning'>✅ <strong>Expected behavior:</strong> Verification popup should NOT show for this user.</div>";
        } else {
            echo "<p class='not-verified'>❌ <strong>API Returns: NOT VERIFIED</strong></p>";
            echo "<div class='warning'>⚠️ <strong>Expected behavior:</strong> Verification popup WILL show for this user.</div>";
        }
        
        $conn->close();
        ?>
        
        <hr style="margin: 30px 0;">
        <form method='GET' style="margin-top: 20px;">
            <input type='number' name='user_id' placeholder="Check another user ID" required>
            <button type='submit'>Check</button>
        </form>
    </div>
</body>
</html>