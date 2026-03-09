<?php
/**
 * Force a fresh API call without any caching
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$owner_id = $_GET['owner_id'] ?? '16';

echo "<h2>Force Fresh API Call</h2>";
echo "<p>Testing API with cache-busting...</p>";

// Make a fresh API call
$apiUrl = "http://cargoph.online/cargoAdmin/api/bookings/get_owner_active_bookings.php?owner_id=$owner_id&nocache=" . time();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo "<h3>Fresh API Response:</h3>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 8px;'>";
echo json_encode($data, JSON_PRETTY_PRINT);
echo "</pre>";

if (isset($data['bookings'][0])) {
    $booking = $data['bookings'][0];
    echo "<div style='background: " . ($booking['trip_status'] === 'upcoming' ? '#d4edda' : '#fff3cd') . "; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>Booking #{$booking['booking_id']}</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
    echo "<tr>";
    echo "<td><strong>trip_status</strong></td>";
    echo "<td style='font-size: 20px; font-weight: bold; color: " . ($booking['trip_status'] === 'upcoming' ? 'orange' : 'green') . ";'>" . strtoupper($booking['trip_status']) . "</td>";
    
    if ($booking['trip_status'] === 'upcoming') {
        echo "<td style='background: #d4edda; color: green; font-weight: bold;'>✅ CORRECT - Should show green button</td>";
    } else {
        echo "<td style='background: #f8d7da; color: red; font-weight: bold;'>❌ WRONG - Should be 'upcoming'</td>";
    }
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>trip_started_at</td>";
    echo "<td>" . ($booking['trip_started_at'] ?? '<em>NULL</em>') . "</td>";
    echo "<td>" . ($booking['trip_started_at'] === null ? '✅ Not started' : '⚠️ Already started') . "</td>";
    echo "</tr>";
    echo "</table>";
    echo "</div>";
    
    if ($booking['trip_status'] === 'upcoming') {
        echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
        echo "<h3>✅ SUCCESS! API is now returning 'upcoming'</h3>";
        echo "<p><strong>Next step:</strong></p>";
        echo "<ol>";
        echo "<li>Completely close your Flutter app (kill it)</li>";
        echo "<li>Run: <code>flutter clean</code></li>";
        echo "<li>Run: <code>flutter run</code></li>";
        echo "<li>Go to Active Bookings page</li>";
        echo "<li>You should now see the green 'Start Rent / Picked Up' button!</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px;'>";
        echo "<h3>❌ API still returning wrong status</h3>";
        echo "<p>The API file may not be updated on the server.</p>";
        echo "<p>Make sure the file <code>get_owner_active_bookings.php</code> has the updated SQL query.</p>";
        echo "</div>";
    }
}
?>
