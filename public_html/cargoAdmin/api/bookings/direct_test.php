<?php
/**
 * Direct test - bypass the API call and run the code directly
 */
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Direct API Code Test</h2>";

// Run the API code directly
ob_start();
$_GET['owner_id'] = '16';
include 'get_owner_active_bookings.php';
$apiResponse = ob_get_clean();

echo "<h3>API Response:</h3>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 8px;'>";
echo htmlspecialchars($apiResponse);
echo "</pre>";

$data = json_decode($apiResponse, true);

if ($data === null) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px;'>";
    echo "<h3>❌ JSON Decode Error</h3>";
    echo "<p>The API is returning invalid JSON or there's a PHP error.</p>";
    echo "<p><strong>JSON Error:</strong> " . json_last_error_msg() . "</p>";
    echo "</div>";
    
    // Show raw response
    echo "<h3>Raw Response (first 500 chars):</h3>";
    echo "<pre>" . htmlspecialchars(substr($apiResponse, 0, 500)) . "</pre>";
} else {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
    echo "<h3>✅ API Response Valid</h3>";
    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
    echo "</div>";
    
    if (isset($data['bookings'][0])) {
        $booking = $data['bookings'][0];
        echo "<h3>Booking #{$booking['booking_id']}</h3>";
        echo "<p><strong>trip_status:</strong> <span style='font-size: 24px; color: " . 
             ($booking['trip_status'] === 'upcoming' ? 'orange' : 'green') . ";'>" . 
             strtoupper($booking['trip_status']) . "</span></p>";
        
        if ($booking['trip_status'] === 'upcoming') {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
            echo "<h4>✅ SUCCESS! Now restart your Flutter app:</h4>";
            echo "<ol>";
            echo "<li>Close the app completely</li>";
            echo "<li>Run: <code>flutter clean && flutter run</code></li>";
            echo "<li>Check Active Bookings page</li>";
            echo "</ol>";
            echo "</div>";
        }
    }
}
?>
