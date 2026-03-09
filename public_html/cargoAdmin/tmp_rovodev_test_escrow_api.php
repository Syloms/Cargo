<?php
/**
 * Test Escrow Release API Endpoints
 */

echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    h2 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
    .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .test-result { padding: 15px; margin: 10px 0; border-left: 4px solid #4CAF50; background: #f9f9f9; }
    .test-result.error { border-left-color: #DC3545; background: #F8D7DA; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
    .btn { padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
    .btn:hover { background: #45a049; }
</style>";

echo "<h2>🧪 Escrow Release API Testing</h2>";

// Test 1: Check Releasable Escrows API
echo "<div class='test-section'>";
echo "<h3>Test 1: Check Releasable Escrows API</h3>";
echo "<p>Endpoint: <code>api/escrow/check_releasable_escrows.php</code></p>";

$url = "http://" . $_SERVER['HTTP_HOST'] . "/cargoAdmin/api/escrow/check_releasable_escrows.php";
$response = @file_get_contents($url);

if ($response) {
    $data = json_decode($response, true);
    if ($data) {
        echo "<div class='test-result'>";
        echo "<strong>✅ API Response Successful</strong><br>";
        echo "Releasable Count: <strong>{$data['releasable_count']}</strong><br>";
        echo "Total Releasable Amount: <strong>₱" . number_format($data['total_releasable_amount'], 2) . "</strong><br>";
        echo "Pending Payouts Count: <strong>{$data['pending_payouts_count']}</strong><br>";
        echo "Pending Payouts Amount: <strong>₱" . number_format($data['pending_payouts_amount'], 2) . "</strong><br>";
        
        if (isset($data['warnings'])) {
            echo "<br><strong>⚠️ Warnings:</strong> {$data['warnings']['message']}<br>";
        }
        
        echo "</div>";
        
        echo "<details><summary>Full Response JSON</summary><pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre></details>";
    } else {
        echo "<div class='test-result error'>❌ Invalid JSON response</div>";
        echo "<pre>$response</pre>";
    }
} else {
    echo "<div class='test-result error'>❌ Failed to connect to API</div>";
}
echo "</div>";

// Test 2: Database Schema Check
echo "<div class='test-section'>";
echo "<h3>Test 2: Database Schema Verification</h3>";

require_once __DIR__ . '/include/db.php';

$requiredColumns = [
    'bookings' => ['escrow_status', 'payout_status', 'escrow_released_at', 'owner_payout', 'platform_fee'],
    'users' => ['gcash_number', 'gcash_name']
];

echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
echo "<tr><th>Table</th><th>Column</th><th>Status</th></tr>";

foreach ($requiredColumns as $table => $columns) {
    $tableExists = $conn->query("SHOW TABLES LIKE '$table'")->num_rows > 0;
    
    if ($tableExists) {
        $existingColumns = [];
        $result = $conn->query("SHOW COLUMNS FROM $table");
        while ($row = $result->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
        
        foreach ($columns as $col) {
            $exists = in_array($col, $existingColumns);
            $status = $exists ? "✅ Exists" : "❌ Missing";
            $color = $exists ? "green" : "red";
            echo "<tr><td>$table</td><td>$col</td><td style='color: $color'>$status</td></tr>";
        }
    } else {
        echo "<tr><td colspan='3' style='color: red'>❌ Table '$table' not found</td></tr>";
    }
}

echo "</table>";
echo "</div>";

// Test 3: Check Optional Tables
echo "<div class='test-section'>";
echo "<h3>Test 3: Optional Tables Check</h3>";

$optionalTables = ['escrow', 'payouts', 'payment_transactions', 'notifications'];

echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
echo "<tr><th>Table Name</th><th>Status</th><th>Record Count</th></tr>";

foreach ($optionalTables as $table) {
    $exists = $conn->query("SHOW TABLES LIKE '$table'")->num_rows > 0;
    
    if ($exists) {
        $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
        echo "<tr><td>$table</td><td style='color: green'>✅ Exists</td><td>$count records</td></tr>";
    } else {
        echo "<tr><td>$table</td><td style='color: orange'>⚠️ Optional (Not Found)</td><td>N/A</td></tr>";
    }
}

echo "</table>";
echo "</div>";

// Test 4: Sample Query Test
echo "<div class='test-section'>";
echo "<h3>Test 4: Sample Releasable Bookings Query</h3>";

$query = "
    SELECT 
        b.id,
        b.owner_id,
        b.escrow_status,
        b.payout_status,
        b.owner_payout,
        b.return_date,
        DATEDIFF(NOW(), b.return_date) as days_since_return,
        u.fullname as owner_name,
        u.gcash_number,
        u.gcash_name
    FROM bookings b
    LEFT JOIN users u ON b.owner_id = u.id
    WHERE b.status = 'completed'
      AND b.escrow_status = 'held'
      AND b.return_date IS NOT NULL
      AND b.return_date < DATE_SUB(NOW(), INTERVAL 3 DAY)
      AND b.owner_payout > 0
    LIMIT 5
";

$result = $conn->query($query);

if ($result) {
    echo "<div class='test-result'>";
    echo "✅ Query executed successfully<br>";
    echo "Found <strong>{$result->num_rows}</strong> releasable booking(s)<br>";
    echo "</div>";
    
    if ($result->num_rows > 0) {
        echo "<table border='1' style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr><th>ID</th><th>Owner</th><th>Amount</th><th>Days Since Return</th><th>GCash Status</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $gcashStatus = (!empty($row['gcash_number']) && !empty($row['gcash_name'])) ? "✅ Set" : "❌ Not Set";
            echo "<tr>";
            echo "<td>BK-{$row['id']}</td>";
            echo "<td>{$row['owner_name']}</td>";
            echo "<td>₱" . number_format($row['owner_payout'], 2) . "</td>";
            echo "<td>{$row['days_since_return']} days</td>";
            echo "<td>$gcashStatus</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} else {
    echo "<div class='test-result error'>❌ Query failed: " . $conn->error . "</div>";
}

echo "</div>";

$conn->close();

// Links section
echo "<div class='test-section'>";
echo "<h3>📋 Quick Links</h3>";
echo "<a href='tmp_rovodev_check_escrow_releases.php' class='btn'>View Detailed Escrow Check</a>";
echo "<a href='tmp_rovodev_auto_release_escrow.php' class='btn'>Run Auto-Release Script</a>";
echo "<a href='escrow_release_manager.php' class='btn'>Open Escrow Manager</a>";
echo "<a href='payouts.php' class='btn'>View Payouts Page</a>";
echo "</div>";
?>
