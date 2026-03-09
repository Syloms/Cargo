<?php
/**
 * Check if security deposit columns exist in bookings table
 */
require_once 'include/db.php';

echo "<h2>Checking Security Deposit Columns</h2>";

// Check bookings table structure
$result = $conn->query("DESCRIBE bookings");
echo "<h3>Bookings Table Columns:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";

$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check for security deposit columns
echo "<h3>Security Deposit Columns Status:</h3>";
$requiredColumns = [
    'security_deposit_amount',
    'security_deposit_status',
    'security_deposit_held_at',
    'security_deposit_refunded_at',
    'security_deposit_refund_amount',
    'security_deposit_deductions',
    'security_deposit_deduction_reason',
    'security_deposit_refund_reference'
];

echo "<ul>";
foreach ($requiredColumns as $col) {
    $exists = in_array($col, $columns);
    $status = $exists ? "✅ EXISTS" : "❌ MISSING";
    echo "<li><strong>$col:</strong> $status</li>";
}
echo "</ul>";

// If columns are missing, show migration SQL
$missingColumns = array_diff($requiredColumns, $columns);
if (!empty($missingColumns)) {
    echo "<h3>⚠️ Missing Columns - Run This Migration:</h3>";
    echo "<pre>";
    echo "ALTER TABLE bookings\n";
    $alterStatements = [];
    
    if (in_array('security_deposit_amount', $missingColumns)) {
        $alterStatements[] = "ADD COLUMN security_deposit_amount DECIMAL(10,2) DEFAULT 0.00";
    }
    if (in_array('security_deposit_status', $missingColumns)) {
        $alterStatements[] = "ADD COLUMN security_deposit_status ENUM('none', 'held', 'refunded', 'partial_refund', 'forfeited') DEFAULT 'none'";
    }
    if (in_array('security_deposit_held_at', $missingColumns)) {
        $alterStatements[] = "ADD COLUMN security_deposit_held_at DATETIME NULL";
    }
    if (in_array('security_deposit_refunded_at', $missingColumns)) {
        $alterStatements[] = "ADD COLUMN security_deposit_refunded_at DATETIME NULL";
    }
    if (in_array('security_deposit_refund_amount', $missingColumns)) {
        $alterStatements[] = "ADD COLUMN security_deposit_refund_amount DECIMAL(10,2) DEFAULT 0.00";
    }
    if (in_array('security_deposit_deductions', $missingColumns)) {
        $alterStatements[] = "ADD COLUMN security_deposit_deductions DECIMAL(10,2) DEFAULT 0.00";
    }
    if (in_array('security_deposit_deduction_reason', $missingColumns)) {
        $alterStatements[] = "ADD COLUMN security_deposit_deduction_reason TEXT NULL";
    }
    if (in_array('security_deposit_refund_reference', $missingColumns)) {
        $alterStatements[] = "ADD COLUMN security_deposit_refund_reference VARCHAR(100) NULL";
    }
    
    echo implode(",\n", $alterStatements) . ";";
    echo "</pre>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✅ All required columns exist!</p>";
}

// Test query
echo "<h3>Test Query:</h3>";
$testQuery = "SELECT COUNT(*) as total FROM bookings WHERE status = 'completed'";
$testResult = $conn->query($testQuery);
if ($testResult) {
    $testData = $testResult->fetch_assoc();
    echo "<p>✅ Found {$testData['total']} completed bookings</p>";
} else {
    echo "<p>❌ Query failed: " . $conn->error . "</p>";
}
?>
