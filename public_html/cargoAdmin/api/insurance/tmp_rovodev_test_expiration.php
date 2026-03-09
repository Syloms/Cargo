<?php
/**
 * Test Script - Insurance Expiration Status Update
 * This script tests that expired insurance policies are properly marked as 'expired'
 */

require_once __DIR__ . '/../../include/db.php';

echo "<h2>Insurance Expiration Status Test</h2>\n";
echo "<hr>\n";

// Step 1: Check current policies with status 'active' but expired dates
echo "<h3>Step 1: Finding active policies that are actually expired</h3>\n";
$query1 = "
    SELECT 
        id, 
        policy_number, 
        status, 
        policy_start, 
        policy_end,
        DATEDIFF(NOW(), policy_end) as days_past_expiration
    FROM insurance_policies 
    WHERE status = 'active' 
    AND policy_end < NOW()
    ORDER BY policy_end DESC
    LIMIT 10
";

$result1 = $conn->query($query1);
if ($result1 && $result1->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Found {$result1->num_rows} active policies that should be expired:</p>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Policy Number</th><th>Status</th><th>Policy End</th><th>Days Past Expiration</th></tr>\n";
    while ($row = $result1->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['policy_number']}</td>";
        echo "<td><strong style='color: red;'>{$row['status']}</strong></td>";
        echo "<td>{$row['policy_end']}</td>";
        echo "<td>{$row['days_past_expiration']} days</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p style='color: green;'>✅ No active policies with expired dates found!</p>\n";
}

echo "<hr>\n";

// Step 2: Run the auto-expire update
echo "<h3>Step 2: Running auto-expire update</h3>\n";
$updateStmt = $conn->prepare("
    UPDATE insurance_policies 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND policy_end < NOW()
");
$updateStmt->execute();
$affectedRows = $updateStmt->affected_rows;
$updateStmt->close();

if ($affectedRows > 0) {
    echo "<p style='color: blue;'>🔄 Updated {$affectedRows} policies from 'active' to 'expired'</p>\n";
} else {
    echo "<p style='color: green;'>✅ No policies needed updating</p>\n";
}

echo "<hr>\n";

// Step 3: Verify - Check again for active policies with expired dates
echo "<h3>Step 3: Verification - Checking for any remaining issues</h3>\n";
$result3 = $conn->query($query1);
if ($result3 && $result3->num_rows > 0) {
    echo "<p style='color: red;'>❌ ERROR: Still found {$result3->num_rows} active policies with expired dates!</p>\n";
} else {
    echo "<p style='color: green;'>✅ SUCCESS: All expired policies now have 'expired' status!</p>\n";
}

echo "<hr>\n";

// Step 4: Show summary statistics
echo "<h3>Step 4: Current Insurance Policy Statistics</h3>\n";
$statsQuery = "
    SELECT 
        status,
        COUNT(*) as count,
        SUM(CASE WHEN policy_end < NOW() THEN 1 ELSE 0 END) as actually_expired_count
    FROM insurance_policies
    GROUP BY status
    ORDER BY status
";

$statsResult = $conn->query($statsQuery);
if ($statsResult && $statsResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Status</th><th>Count</th><th>Actually Expired (by date)</th></tr>\n";
    while ($row = $statsResult->fetch_assoc()) {
        $statusColor = 'black';
        if ($row['status'] === 'active') $statusColor = 'green';
        if ($row['status'] === 'expired') $statusColor = 'orange';
        if ($row['status'] === 'cancelled') $statusColor = 'red';
        
        echo "<tr>";
        echo "<td><strong style='color: {$statusColor};'>{$row['status']}</strong></td>";
        echo "<td>{$row['count']}</td>";
        echo "<td>{$row['actually_expired_count']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "<hr>\n";

// Step 5: Show sample of policies by status
echo "<h3>Step 5: Sample Policies (5 per status)</h3>\n";
$sampleQuery = "
    SELECT 
        id,
        policy_number,
        status,
        policy_start,
        policy_end,
        CASE 
            WHEN policy_end < NOW() THEN 'YES'
            ELSE 'NO'
        END as is_actually_expired
    FROM insurance_policies
    WHERE status IN ('active', 'expired')
    ORDER BY status, policy_end DESC
    LIMIT 10
";

$sampleResult = $conn->query($sampleQuery);
if ($sampleResult && $sampleResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Policy Number</th><th>Status</th><th>Policy End</th><th>Is Actually Expired?</th></tr>\n";
    while ($row = $sampleResult->fetch_assoc()) {
        $highlight = ($row['status'] === 'active' && $row['is_actually_expired'] === 'YES') ? 
            "style='background-color: #ffcccc;'" : "";
        
        echo "<tr {$highlight}>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['policy_number']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['policy_end']}</td>";
        echo "<td>{$row['is_actually_expired']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    echo "<p><em>Note: Red highlighted rows indicate a mismatch (should not exist after fix)</em></p>\n";
}

echo "<hr>\n";
echo "<h3>✅ Test Complete</h3>\n";
echo "<p>All insurance policies should now have correct status based on their expiration date.</p>\n";

$conn->close();
?>
