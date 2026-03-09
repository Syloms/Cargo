<?php
/**
 * Temporary diagnostic script to check payouts table structure
 */
include "include/db.php";

echo "<h2>Checking Payouts Table Structure</h2>";

// Check if payouts table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'payouts'");
if (mysqli_num_rows($result) > 0) {
    echo "<p style='color:green;'>✓ Payouts table exists</p>";
    
    // Get table structure
    echo "<h3>Table Columns:</h3>";
    $columns = mysqli_query($conn, "DESCRIBE payouts");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($col = mysqli_fetch_assoc($columns)) {
        $highlight = ($col['Field'] == 'transfer_proof') ? "style='background-color: yellow;'" : "";
        echo "<tr $highlight>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if transfer_proof column exists
    $hasTransferProof = mysqli_query($conn, "SHOW COLUMNS FROM payouts LIKE 'transfer_proof'");
    if (mysqli_num_rows($hasTransferProof) > 0) {
        echo "<p style='color:green;'>✓ transfer_proof column exists</p>";
    } else {
        echo "<p style='color:red;'>✗ transfer_proof column MISSING - Migration needed!</p>";
        echo "<h3>Run this SQL to add the column:</h3>";
        echo "<pre>ALTER TABLE payouts ADD COLUMN transfer_proof VARCHAR(255) NULL COMMENT 'Screenshot/proof of transfer' AFTER completion_reference;</pre>";
    }
    
    // Test query
    echo "<h3>Test Query:</h3>";
    $testQuery = "SELECT p.id, p.booking_id, p.status, p.transfer_proof FROM payouts p LIMIT 1";
    echo "<pre>$testQuery</pre>";
    
    $testResult = mysqli_query($conn, $testQuery);
    if ($testResult) {
        echo "<p style='color:green;'>✓ Query executed successfully</p>";
        if (mysqli_num_rows($testResult) > 0) {
            $row = mysqli_fetch_assoc($testResult);
            echo "<pre>" . print_r($row, true) . "</pre>";
        } else {
            echo "<p>No payouts in database yet</p>";
        }
    } else {
        echo "<p style='color:red;'>✗ Query failed: " . mysqli_error($conn) . "</p>";
    }
    
} else {
    echo "<p style='color:red;'>✗ Payouts table does not exist!</p>";
}

mysqli_close($conn);
?>
