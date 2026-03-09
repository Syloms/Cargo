<?php
/**
 * MILEAGE SYSTEM TEST SCRIPT
 * Run this file to test the mileage monitoring system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Mileage System Test</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #10b981; font-weight: bold; }
    .error { color: #ef4444; font-weight: bold; }
    .warning { color: #f59e0b; font-weight: bold; }
    h1 { color: #333; }
    h2 { color: #666; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
    pre { background: #f9fafb; padding: 10px; border-radius: 4px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
    th { background: #f3f4f6; font-weight: 600; }
</style></head><body>";

echo "<h1>🔧 Mileage System Test Suite</h1>";
echo "<p>Testing all components of the mileage monitoring system...</p>";

include "include/db.php";

$tests_passed = 0;
$tests_failed = 0;
$warnings = 0;

// TEST 1: Database Connection
echo "<div class='test-section'>";
echo "<h2>Test 1: Database Connection</h2>";
if ($conn && !$conn->connect_error) {
    echo "<p class='success'>✓ Database connected successfully</p>";
    echo "<p>Host: localhost | Database: dbcargo</p>";
    $tests_passed++;
} else {
    echo "<p class='error'>✗ Database connection failed: " . ($conn ? $conn->connect_error : "Connection object not created") . "</p>";
    $tests_failed++;
    die("Cannot proceed without database connection.</div></body></html>");
}
echo "</div>";

// TEST 2: Check Tables Exist
echo "<div class='test-section'>";
echo "<h2>Test 2: Required Tables</h2>";
$required_tables = ['bookings', 'cars', 'motorcycles', 'mileage_disputes', 'mileage_logs', 'gps_distance_tracking'];
$tables_result = $conn->query("SHOW TABLES");
$existing_tables = [];
while ($row = $tables_result->fetch_array()) {
    $existing_tables[] = $row[0];
}

echo "<table>";
echo "<tr><th>Table</th><th>Status</th></tr>";
foreach ($required_tables as $table) {
    $exists = in_array($table, $existing_tables);
    echo "<tr><td>$table</td><td>";
    if ($exists) {
        echo "<span class='success'>✓ Exists</span>";
        $tests_passed++;
    } else {
        echo "<span class='error'>✗ Missing</span>";
        $tests_failed++;
    }
    echo "</td></tr>";
}
echo "</table>";
echo "</div>";

// TEST 3: Check Bookings Columns
echo "<div class='test-section'>";
echo "<h2>Test 3: Bookings Table Columns</h2>";
$required_columns = [
    'odometer_start', 'odometer_end', 'odometer_start_photo', 'odometer_end_photo',
    'actual_mileage', 'allowed_mileage', 'excess_mileage', 'excess_mileage_fee',
    'gps_distance', 'mileage_verified_by', 'mileage_verified_at'
];

$columns_result = $conn->query("DESCRIBE bookings");
$existing_columns = [];
while ($row = $columns_result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

echo "<table>";
echo "<tr><th>Column</th><th>Status</th></tr>";
foreach ($required_columns as $column) {
    $exists = in_array($column, $existing_columns);
    echo "<tr><td>$column</td><td>";
    if ($exists) {
        echo "<span class='success'>✓ Exists</span>";
        $tests_passed++;
    } else {
        echo "<span class='error'>✗ Missing - Run migration!</span>";
        $tests_failed++;
    }
    echo "</td></tr>";
}
echo "</table>";
echo "</div>";

// TEST 4: Check Cars/Motorcycles Columns
echo "<div class='test-section'>";
echo "<h2>Test 4: Vehicle Mileage Settings Columns</h2>";
$vehicle_columns = ['daily_mileage_limit', 'excess_mileage_rate'];

foreach (['cars', 'motorcycles'] as $table) {
    $columns_result = $conn->query("DESCRIBE $table");
    $existing_columns = [];
    while ($row = $columns_result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    echo "<strong>" . ucfirst($table) . ":</strong><br>";
    foreach ($vehicle_columns as $column) {
        $exists = in_array($column, $existing_columns);
        if ($exists) {
            echo "<span class='success'>✓ $column</span> ";
            $tests_passed++;
        } else {
            echo "<span class='error'>✗ $column missing</span> ";
            $tests_failed++;
        }
    }
    echo "<br><br>";
}
echo "</div>";

// TEST 5: Check API Files Exist
echo "<div class='test-section'>";
echo "<h2>Test 5: API Files</h2>";
$api_files = [
    'api/mileage/record_start_odometer.php',
    'api/mileage/record_end_odometer.php',
    'api/mileage/get_mileage_details.php',
    'api/mileage/update_gps_distance.php',
    'api/mileage/verify_mileage.php',
    'api/mileage/flag_for_review.php'
];

echo "<table>";
echo "<tr><th>API File</th><th>Status</th></tr>";
foreach ($api_files as $file) {
    $exists = file_exists($file);
    echo "<tr><td>$file</td><td>";
    if ($exists) {
        echo "<span class='success'>✓ Exists</span>";
        $tests_passed++;
    } else {
        echo "<span class='error'>✗ Missing</span>";
        $tests_failed++;
    }
    echo "</td></tr>";
}
echo "</table>";
echo "</div>";

// TEST 6: Check Upload Directory
echo "<div class='test-section'>";
echo "<h2>Test 6: Upload Directory</h2>";
$upload_dir = "uploads/odometer";
$dir_exists = is_dir($upload_dir);
$is_writable = is_writable($upload_dir);

if ($dir_exists) {
    echo "<p class='success'>✓ Directory exists: $upload_dir</p>";
    $tests_passed++;
    if ($is_writable) {
        echo "<p class='success'>✓ Directory is writable</p>";
        $tests_passed++;
    } else {
        echo "<p class='error'>✗ Directory is not writable - Run: chmod 777 $upload_dir</p>";
        $tests_failed++;
    }
} else {
    echo "<p class='error'>✗ Directory missing - Run: mkdir -p $upload_dir && chmod 777 $upload_dir</p>";
    $tests_failed++;
}
echo "</div>";

// TEST 7: Check Admin Interface
echo "<div class='test-section'>";
echo "<h2>Test 7: Admin Interface</h2>";
if (file_exists("mileage_verification.php")) {
    echo "<p class='success'>✓ Admin interface file exists</p>";
    echo "<p>Access at: <a href='mileage_verification.php' target='_blank'>mileage_verification.php</a></p>";
    $tests_passed++;
} else {
    echo "<p class='error'>✗ Admin interface missing</p>";
    $tests_failed++;
}
echo "</div>";

// TEST 8: Check Triggers
echo "<div class='test-section'>";
echo "<h2>Test 8: Database Triggers</h2>";
$triggers_result = $conn->query("SHOW TRIGGERS LIKE 'bookings'");
$triggers = [];
while ($row = $triggers_result->fetch_assoc()) {
    $triggers[] = $row['Trigger'];
}

$expected_triggers = ['trg_calculate_actual_mileage', 'trg_calculate_allowed_mileage', 'trg_calculate_excess_mileage'];
echo "<table>";
echo "<tr><th>Trigger</th><th>Status</th></tr>";
foreach ($expected_triggers as $trigger) {
    $exists = in_array($trigger, $triggers);
    echo "<tr><td>$trigger</td><td>";
    if ($exists) {
        echo "<span class='success'>✓ Exists</span>";
        $tests_passed++;
    } else {
        echo "<span class='warning'>⚠ Missing (auto-calculation will need manual handling)</span>";
        $warnings++;
    }
    echo "</td></tr>";
}
echo "</table>";
echo "</div>";

// TEST 9: Check Sample Data
echo "<div class='test-section'>";
echo "<h2>Test 9: Test Data</h2>";
$bookings_count = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
$cars_count = $conn->query("SELECT COUNT(*) as count FROM cars WHERE status = 'approved'")->fetch_assoc()['count'];
$motorcycles_count = $conn->query("SELECT COUNT(*) as count FROM motorcycles WHERE status = 'approved'")->fetch_assoc()['count'];

echo "<table>";
echo "<tr><th>Data</th><th>Count</th><th>Status</th></tr>";
echo "<tr><td>Total Bookings</td><td>$bookings_count</td><td>" . ($bookings_count > 0 ? "<span class='success'>✓</span>" : "<span class='warning'>⚠ No bookings yet</span>") . "</td></tr>";
echo "<tr><td>Approved Cars</td><td>$cars_count</td><td>" . ($cars_count > 0 ? "<span class='success'>✓</span>" : "<span class='warning'>⚠ No cars yet</span>") . "</td></tr>";
echo "<tr><td>Approved Motorcycles</td><td>$motorcycles_count</td><td>" . ($motorcycles_count > 0 ? "<span class='success'>✓</span>" : "<span class='warning'>⚠ No motorcycles yet</span>") . "</td></tr>";
echo "</table>";

// Check if any vehicles have mileage limits configured
$cars_with_limits = $conn->query("SELECT COUNT(*) as count FROM cars WHERE daily_mileage_limit IS NOT NULL")->fetch_assoc()['count'];
$motorcycles_with_limits = $conn->query("SELECT COUNT(*) as count FROM motorcycles WHERE daily_mileage_limit IS NOT NULL")->fetch_assoc()['count'];

echo "<br><p><strong>Vehicles with Mileage Limits Configured:</strong></p>";
echo "<p>Cars: $cars_with_limits | Motorcycles: $motorcycles_with_limits</p>";

if ($cars_with_limits == 0 && $motorcycles_with_limits == 0) {
    echo "<p class='warning'>⚠ No vehicles have mileage limits set. Owners need to configure limits when listing.</p>";
    $warnings++;
}
echo "</div>";

// TEST 10: API Connectivity Test
echo "<div class='test-section'>";
echo "<h2>Test 10: API Connectivity</h2>";
$base_url = "http://10.218.197.49/carGOAdmin/";
$test_api = $base_url . "api/mileage/get_mileage_details.php?booking_id=999";

echo "<p>Testing API endpoint: <code>$test_api</code></p>";
$response = @file_get_contents($test_api);

if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && isset($data['status'])) {
        echo "<p class='success'>✓ API is accessible and returning JSON</p>";
        echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
        $tests_passed++;
    } else {
        echo "<p class='error'>✗ API returned invalid JSON</p>";
        $tests_failed++;
    }
} else {
    echo "<p class='error'>✗ Cannot connect to API (check if web server is running)</p>";
    $tests_failed++;
}
echo "</div>";

// SUMMARY
echo "<div class='test-section' style='background: #f0f9ff; border: 2px solid #3b82f6;'>";
echo "<h2>📊 Test Summary</h2>";
echo "<table>";
echo "<tr><td><strong>Tests Passed:</strong></td><td class='success'>$tests_passed</td></tr>";
echo "<tr><td><strong>Tests Failed:</strong></td><td class='error'>$tests_failed</td></tr>";
echo "<tr><td><strong>Warnings:</strong></td><td class='warning'>$warnings</td></tr>";
$total = $tests_passed + $tests_failed + $warnings;
$percentage = $total > 0 ? round(($tests_passed / $total) * 100) : 0;
echo "<tr><td><strong>Success Rate:</strong></td><td><strong>{$percentage}%</strong></td></tr>";
echo "</table>";

if ($tests_failed > 0) {
    echo "<h3 class='error'>⚠ Action Required:</h3>";
    echo "<ol>";
    if ($tests_failed > 5) {
        echo "<li><strong>Run the database migration:</strong><br>";
        echo "<code>mysql -u root -p dbcargo < database_migrations/mileage_tracking_migration.sql</code></li>";
    }
    echo "<li><strong>Create upload directory:</strong><br>";
    echo "<code>mkdir -p uploads/odometer && chmod 777 uploads/odometer</code></li>";
    echo "<li>Refresh this page after fixing issues</li>";
    echo "</ol>";
} else {
    echo "<h3 class='success'>🎉 All Systems Operational!</h3>";
    echo "<p>The mileage monitoring system is ready to use.</p>";
    echo "<p><a href='mileage_verification.php' style='display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;'>Go to Admin Dashboard</a></p>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>📝 Next Steps</h2>";
echo "<ol>";
echo "<li>If tests failed, follow the action items above</li>";
echo "<li>Configure mileage limits on existing vehicles (Admin → Cars/Motorcycles → Edit)</li>";
echo "<li>Create a test booking and try recording odometer readings</li>";
echo "<li>Check the admin interface to verify mileage data</li>";
echo "<li>Test the Flutter app integration</li>";
echo "</ol>";
echo "</div>";

echo "<p style='text-align: center; color: #999; margin-top: 40px;'>Mileage System Test v1.0 | " . date('Y-m-d H:i:s') . "</p>";
echo "</body></html>";

$conn->close();
?>
