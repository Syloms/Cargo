<?php
/**
 * Show the full API response for debugging
 */
header('Content-Type: text/html; charset=utf-8');

$owner_id = $_GET['owner_id'] ?? '16';

echo "<h2>Full API Response for Owner ID: $owner_id</h2>";
echo "<a href='debug_owner_bookings.php?owner_id=$owner_id'>Back to Debug Tool</a><br><br>";

// Get the response
$apiUrl = "http://cargoph.online/cargoAdmin/api/bookings/get_owner_active_bookings.php?owner_id=$owner_id";
$response = file_get_contents($apiUrl);
$data = json_decode($response, true);

echo "<h3>Raw JSON Response:</h3>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-x: auto;'>";
echo json_encode($data, JSON_PRETTY_PRINT);
echo "</pre>";

if (isset($data['bookings']) && !empty($data['bookings'])) {
    foreach ($data['bookings'] as $booking) {
        echo "<div style='background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border: 2px solid #ddd;'>";
        echo "<h3>Booking #{$booking['booking_id']}</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td><strong>trip_status</strong></td><td style='font-size: 18px; color: " . 
             ($booking['trip_status'] === 'upcoming' ? 'orange' : 'green') . ";'><strong>" . 
             strtoupper($booking['trip_status']) . "</strong></td></tr>";
        echo "<tr><td>trip_started_at</td><td>" . ($booking['trip_started_at'] ?? '<em>NULL</em>') . "</td></tr>";
        echo "<tr><td>pickup_date</td><td>{$booking['pickup_date']}</td></tr>";
        echo "<tr><td>return_date</td><td>{$booking['return_date']}</td></tr>";
        echo "</table>";
        
        if ($booking['trip_status'] === 'upcoming') {
            echo "<div style='background: #d4edda; padding: 15px; margin-top: 15px; border-radius: 8px;'>";
            echo "<h4>✅ This booking should show the GREEN 'Start Rent / Picked Up' button</h4>";
            echo "</div>";
        } else {
            echo "<div style='background: #cce5ff; padding: 15px; margin-top: 15px; border-radius: 8px;'>";
            echo "<h4>🔵 This booking should show the BLUE 'Track Car Location' button</h4>";
            echo "</div>";
        }
        echo "</div>";
    }
}
?>
