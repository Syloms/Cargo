<?php
/**
 * API ENDPOINTS TEST SCRIPT
 * Tests all mileage API endpoints
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>API Test</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .test { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #10b981; font-weight: bold; }
    .error { color: #ef4444; font-weight: bold; }
    pre { background: #f9fafb; padding: 15px; border-radius: 4px; overflow-x: auto; border: 1px solid #e5e7eb; }
    h1 { color: #333; }
    h2 { color: #3b82f6; }
    .endpoint { background: #dbeafe; padding: 8px 12px; border-radius: 4px; font-family: monospace; }
</style></head><body>";

echo "<h1>🧪 API Endpoints Test</h1>";
echo "<p>Testing all mileage monitoring API endpoints...</p>";

$base_url = "http://10.218.197.49/carGOAdmin/api/mileage/";
$tests_passed = 0;
$tests_failed = 0;

// TEST 1: Get Mileage Details (for non-existent booking)
echo "<div class='test'>";
echo "<h2>Test 1: GET - Get Mileage Details</h2>";
echo "<p class='endpoint'>GET {$base_url}get_mileage_details.php?booking_id=999</p>";

$response = @file_get_contents($base_url . "get_mileage_details.php?booking_id=999");
if ($response !== false) {
    $data = json_decode($response, true);
    echo "<p class='success'>✓ API Responded</p>";
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
    
    if ($data && isset($data['status'])) {
        echo "<p class='success'>✓ Valid JSON response with status field</p>";
        $tests_passed++;
    } else {
        echo "<p class='error'>✗ Invalid response structure</p>";
        $tests_failed++;
    }
} else {
    echo "<p class='error'>✗ API not accessible</p>";
    $tests_failed++;
}
echo "</div>";

// TEST 2: Update GPS Distance (POST simulation)
echo "<div class='test'>";
echo "<h2>Test 2: POST - Update GPS Distance</h2>";
echo "<p class='endpoint'>POST {$base_url}update_gps_distance.php</p>";

$test_data = [
    'booking_id' => 999,
    'latitude' => 8.4312419,
    'longitude' => 125.9831042
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($test_data)
    ]
];
$context  = stream_context_create($options);
$response = @file_get_contents($base_url . "update_gps_distance.php", false, $context);

if ($response !== false) {
    $data = json_decode($response, true);
    echo "<p class='success'>✓ API Responded</p>";
    echo "<p><strong>Test Data Sent:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($test_data, JSON_PRETTY_PRINT)) . "</pre>";
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
    
    if ($data && isset($data['status'])) {
        echo "<p class='success'>✓ Valid JSON response</p>";
        $tests_passed++;
    } else {
        echo "<p class='error'>✗ Invalid response</p>";
        $tests_failed++;
    }
} else {
    echo "<p class='error'>✗ API not accessible</p>";
    $tests_failed++;
}
echo "</div>";

// TEST 3: Check if actual bookings exist
echo "<div class='test'>";
echo "<h2>Test 3: Check Real Bookings</h2>";

include "include/db.php";

$result = $conn->query("SELECT id, status, odometer_start, odometer_end, actual_mileage FROM bookings ORDER BY id DESC LIMIT 5");
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

if (count($bookings) > 0) {
    echo "<p class='success'>✓ Found " . count($bookings) . " bookings in database</p>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Status</th><th>Odometer Start</th><th>Odometer End</th><th>Actual Mileage</th><th>Test</th></tr>";
    
    foreach ($bookings as $booking) {
        echo "<tr>";
        echo "<td>#{$booking['id']}</td>";
        echo "<td>{$booking['status']}</td>";
        echo "<td>" . ($booking['odometer_start'] ?? '<em>null</em>') . "</td>";
        echo "<td>" . ($booking['odometer_end'] ?? '<em>null</em>') . "</td>";
        echo "<td>" . ($booking['actual_mileage'] ?? '<em>null</em>') . "</td>";
        echo "<td><a href='test_api_endpoints.php?test_booking_id={$booking['id']}' target='_blank'>Test with this booking</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    $tests_passed++;
} else {
    echo "<p class='error'>✗ No bookings found in database</p>";
    $tests_failed++;
}
echo "</div>";

// TEST 4: If booking ID provided, test with real booking
if (isset($_GET['test_booking_id'])) {
    $test_booking_id = intval($_GET['test_booking_id']);
    
    echo "<div class='test'>";
    echo "<h2>Test 4: Real Booking Test (ID: $test_booking_id)</h2>";
    
    $response = @file_get_contents($base_url . "get_mileage_details.php?booking_id=$test_booking_id");
    if ($response !== false) {
        $data = json_decode($response, true);
        echo "<p class='success'>✓ Successfully retrieved mileage details for booking #$test_booking_id</p>";
        echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
        $tests_passed++;
    } else {
        echo "<p class='error'>✗ Failed to retrieve booking details</p>";
        $tests_failed++;
    }
    echo "</div>";
}

// TEST 5: Check file upload capability
echo "<div class='test'>";
echo "<h2>Test 5: File Upload Test</h2>";

$upload_dir = "uploads/odometer";
if (is_dir($upload_dir)) {
    echo "<p class='success'>✓ Upload directory exists: $upload_dir</p>";
    
    if (is_writable($upload_dir)) {
        echo "<p class='success'>✓ Directory is writable</p>";
        
        // Try creating a test file
        $test_file = $upload_dir . "/test_" . time() . ".txt";
        if (file_put_contents($test_file, "Test file")) {
            echo "<p class='success'>✓ Successfully created test file</p>";
            unlink($test_file);
            echo "<p>✓ Test file deleted</p>";
            $tests_passed++;
        } else {
            echo "<p class='error'>✗ Could not create test file</p>";
            $tests_failed++;
        }
    } else {
        echo "<p class='error'>✗ Directory is not writable</p>";
        echo "<p>Run: <code>chmod 777 $upload_dir</code></p>";
        $tests_failed++;
    }
} else {
    echo "<p class='error'>✗ Upload directory does not exist</p>";
    echo "<p>Run: <code>mkdir -p $upload_dir && chmod 777 $upload_dir</code></p>";
    $tests_failed++;
}
echo "</div>";

// TEST 6: Admin APIs
echo "<div class='test'>";
echo "<h2>Test 6: Admin Verification APIs</h2>";

$admin_apis = [
    'verify_mileage.php' => 'POST',
    'flag_for_review.php' => 'POST'
];

foreach ($admin_apis as $api => $method) {
    $file_path = "api/mileage/$api";
    if (file_exists($file_path)) {
        echo "<p class='success'>✓ $api exists</p>";
        
        // Check if file is readable
        if (is_readable($file_path)) {
            echo "<p>✓ File is readable</p>";
            $tests_passed++;
        } else {
            echo "<p class='error'>✗ File is not readable</p>";
            $tests_failed++;
        }
    } else {
        echo "<p class='error'>✗ $api not found</p>";
        $tests_failed++;
    }
}
echo "</div>";

// SUMMARY
echo "<div class='test' style='background: #f0f9ff; border: 2px solid #3b82f6;'>";
echo "<h2>📊 Test Summary</h2>";
echo "<p><strong>Tests Passed:</strong> <span class='success'>$tests_passed</span></p>";
echo "<p><strong>Tests Failed:</strong> <span class='error'>$tests_failed</span></p>";
$total = $tests_passed + $tests_failed;
$percentage = $total > 0 ? round(($tests_passed / $total) * 100) : 0;
echo "<p><strong>Success Rate:</strong> {$percentage}%</p>";

if ($tests_failed == 0) {
    echo "<h3 class='success'>🎉 All API Tests Passed!</h3>";
    echo "<p>The API endpoints are working correctly.</p>";
} else {
    echo "<h3 class='error'>⚠ Some Tests Failed</h3>";
    echo "<p>Please fix the issues above and retest.</p>";
}
echo "</div>";

echo "<p style='text-align: center; color: #999; margin-top: 40px;'>API Test v1.0 | " . date('Y-m-d H:i:s') . "</p>";
echo "</body></html>";

if (isset($conn)) $conn->close();
?>
