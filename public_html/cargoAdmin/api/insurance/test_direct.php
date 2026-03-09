<?php
/**
 * Direct Test - Check for PHP Errors
 */

// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Database Connection</h2>";

// Test 1: Check if db.php exists
$dbPath = __DIR__ . '/../../include/db.php';
echo "<p>DB Path: $dbPath</p>";
echo "<p>File Exists: " . (file_exists($dbPath) ? "✓ YES" : "✗ NO") . "</p>";

if (!file_exists($dbPath)) {
    echo "<p style='color: red;'><strong>ERROR: db.php not found!</strong></p>";
    exit;
}

// Test 2: Include db.php
try {
    require_once $dbPath;
    echo "<p style='color: green;'>✓ db.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error loading db.php: " . $e->getMessage() . "</p>";
    exit;
}

// Test 3: Check database connection
if (isset($conn) && $conn) {
    echo "<p style='color: green;'>✓ Database connection established</p>";
    
    // Test 4: Check required tables
    $tables = [
        'insurance_policies',
        'insurance_claims',
        'insurance_providers',
        'insurance_coverage_types',
        'bookings',
        'users'
    ];
    
    echo "<h3>Checking Required Tables:</h3>";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' NOT FOUND</p>";
        }
    }
    
    // Test 5: Check for data
    echo "<h3>Checking Data:</h3>";
    
    $result = $conn->query("SELECT COUNT(*) as cnt FROM insurance_providers WHERE status = 'active'");
    $row = $result->fetch_assoc();
    echo "<p>Active Providers: " . $row['cnt'] . ($row['cnt'] > 0 ? " ✓" : " ⚠️ Need at least 1") . "</p>";
    
    $result = $conn->query("SELECT COUNT(*) as cnt FROM insurance_coverage_types WHERE is_active = 1");
    $row = $result->fetch_assoc();
    echo "<p>Active Coverage Types: " . $row['cnt'] . ($row['cnt'] >= 4 ? " ✓" : " ⚠️ Need 4 types") . "</p>";
    
    $result = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'approved'");
    $row = $result->fetch_assoc();
    echo "<p>Approved Bookings: " . $row['cnt'] . "</p>";
    
    // Test 6: Test a simple query
    echo "<h3>Testing API Query:</h3>";
    try {
        $stmt = $conn->prepare("SELECT * FROM insurance_coverage_types WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Query executed successfully</p>";
            $data = $result->fetch_assoc();
            echo "<pre>" . print_r($data, true) . "</pre>";
        } else {
            echo "<p style='color: orange;'>⚠️ Query executed but no data found</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Query error: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    echo "<p>Error: " . (isset($conn) ? $conn->connect_error : 'Connection object not created') . "</p>";
}

echo "<hr>";
echo "<h3>PHP Configuration:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Error Reporting: " . error_reporting() . "</p>";
echo "<p>Display Errors: " . ini_get('display_errors') . "</p>";

echo "<hr>";
echo "<h3>Next Steps:</h3>";
if (isset($conn) && $conn) {
    echo "<p>✓ Database connection is working</p>";
    echo "<p>Now test the actual API endpoint:</p>";
    echo "<p><a href='../insurance/get_coverage_types.php' target='_blank'>Test Get Coverage Types API</a></p>";
} else {
    echo "<p style='color: red;'>Fix database connection first in include/db.php</p>";
}
?>
