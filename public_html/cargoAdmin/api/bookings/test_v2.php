<?php
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test V2 API</h2>";

$owner_id = $_GET['owner_id'] ?? '16';

ob_start();
$_GET['owner_id'] = $owner_id;
include 'get_owner_active_bookings.php';
$response = ob_get_clean();

$data = json_decode($response, true);

echo "<h3>V2 API Response:</h3>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 8px;'>";
echo json_encode($data, JSON_PRETTY_PRINT);
echo "</pre>";

if (isset($data['bookings'][0])) {
    $booking = $data['bookings'][0];
    echo "<div style='background: " . ($booking['trip_status'] === 'upcoming' ? '#d4edda' : '#fff3cd') . "; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h3>Booking #{$booking['booking_id']}</h3>";
    echo "<p><strong>trip_status:</strong> <span style='font-size: 28px; font-weight: bold; color: " . 
         ($booking['trip_status'] === 'upcoming' ? 'orange' : 'green') . ";'>" . 
         strtoupper($booking['trip_status']) . "</span></p>";
    echo "<p><strong>trip_started_at:</strong> " . ($booking['trip_started_at'] ?? '<em>NULL</em>') . "</p>";
    
    if ($booking['trip_status'] === 'upcoming') {
        echo "<div style='background: #28a745; color: white; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
        echo "<h3>✅ SUCCESS! V2 API is working correctly!</h3>";
        echo "<p>Now let's use this version. I'll rename the files.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #dc3545; color: white; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
        echo "<h3>❌ Still wrong</h3>";
        echo "</div>";
    }
    echo "</div>";
}
?>
