<?php
/**
 * Debug script for security_deposits.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'include/db.php';

// Set admin session for testing
$_SESSION['admin_id'] = 1;

echo "<h1>Security Deposits Debug</h1>";

// Test 1: Check if motorcycles table exists
echo "<h2>Test 1: Check Tables</h2>";
$tables = ['bookings', 'motorcycles', 'cars', 'users'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✓ Table `$table` exists<br>";
    } else {
        echo "✗ Table `$table` NOT FOUND<br>";
    }
}

// Test 2: Check bookings table structure
echo "<h2>Test 2: Bookings Table Structure</h2>";
$result = $conn->query("DESCRIBE bookings");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo "Columns: " . implode(', ', $columns) . "<br>";

// Check for security deposit columns
$securityDepositColumns = [
    'security_deposit_amount',
    'security_deposit_status',
    'security_deposit_held_at',
    'security_deposit_refunded_at',
    'security_deposit_refund_amount',
    'security_deposit_deductions',
    'security_deposit_deduction_reason',
    'security_deposit_refund_reference'
];

echo "<h3>Security Deposit Columns:</h3>";
foreach ($securityDepositColumns as $col) {
    if (in_array($col, $columns)) {
        echo "✓ $col exists<br>";
    } else {
        echo "✗ $col MISSING<br>";
    }
}

// Test 3: Try the original query
echo "<h2>Test 3: Original Query Test</h2>";
try {
    $sql = "SELECT 
            b.id,
            b.user_id,
            b.vehicle_type,
            b.car_id as vehicle_id,
            b.total_amount,
            b.security_deposit_amount,
            b.security_deposit_status,
            b.created_at,
            u.fullname as renter_name,
            u.email as renter_email,
            CASE 
                WHEN b.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model)
                WHEN b.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model)
            END as vehicle_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
        WHERE b.status = 'completed'
        LIMIT 5";
    
    $result = $conn->query($sql);
    
    if ($result) {
        echo "✓ Query executed successfully<br>";
        echo "Rows returned: " . $result->num_rows . "<br>";
        
        if ($result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Vehicle Type</th><th>Vehicle Name</th><th>Deposit Amount</th><th>Status</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['vehicle_type'] . "</td>";
                echo "<td>" . ($row['vehicle_name'] ?? 'N/A') . "</td>";
                echo "<td>" . ($row['security_deposit_amount'] ?? 'N/A') . "</td>";
                echo "<td>" . ($row['security_deposit_status'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "✗ Query failed: " . $conn->error . "<br>";
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "<br>";
}

// Test 4: Check if there are any completed bookings
echo "<h2>Test 4: Completed Bookings Count</h2>";
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'completed'");
$count = $result->fetch_assoc()['count'];
echo "Completed bookings: $count<br>";

// Test 5: Check stats query
echo "<h2>Test 5: Stats Query Test</h2>";
try {
    $statsQuery = "SELECT 
        COUNT(CASE WHEN security_deposit_status = 'held' THEN 1 END) as held_count,
        SUM(CASE WHEN security_deposit_status = 'held' THEN security_deposit_amount ELSE 0 END) as held_amount,
        COUNT(CASE WHEN security_deposit_status = 'refunded' THEN 1 END) as refunded_count,
        SUM(CASE WHEN security_deposit_status = 'refunded' THEN security_deposit_refund_amount ELSE 0 END) as refunded_amount,
        COUNT(CASE WHEN security_deposit_status = 'partial_refund' THEN 1 END) as partial_count,
        COUNT(CASE WHEN security_deposit_status = 'forfeited' THEN 1 END) as forfeited_count
        FROM bookings WHERE status = 'completed'";
    
    $result = $conn->query($statsQuery);
    
    if ($result) {
        echo "✓ Stats query executed successfully<br>";
        $stats = $result->fetch_assoc();
        echo "<pre>" . print_r($stats, true) . "</pre>";
    } else {
        echo "✗ Stats query failed: " . $conn->error . "<br>";
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "<br>";
}

echo "<h2>Conclusion</h2>";
echo "Check the results above to identify the issue causing the 500 error.";
?>
