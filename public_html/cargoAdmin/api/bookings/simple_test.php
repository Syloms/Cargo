<?php
/**
 * Simplest possible test
 */
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Simple Direct Test</h2>";

require_once '../../include/db.php';

$owner_id = '16';

echo "<p>Testing booking #48 for owner $owner_id...</p>";

// Simplest query possible
$sql = "SELECT 
    b.id,
    b.trip_started_at,
    b.return_date,
    CURDATE() as today
FROM bookings b
WHERE b.id = 48";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    echo "<h3>Database Values:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><td>ID</td><td>{$row['id']}</td></tr>";
    echo "<tr><td>trip_started_at</td><td>" . ($row['trip_started_at'] ?? '<strong>NULL</strong>') . "</td></tr>";
    echo "<tr><td>return_date</td><td>{$row['return_date']}</td></tr>";
    echo "<tr><td>today</td><td>{$row['today']}</td></tr>";
    echo "</table>";
    
    // Calculate trip_status in PHP
    $tripStatus = 'past';
    if ($row['trip_started_at'] !== null) {
        $tripStatus = 'in_progress';
    } elseif (strtotime($row['return_date']) >= strtotime(date('Y-m-d'))) {
        $tripStatus = 'upcoming';
    }
    
    echo "<h3>Calculated trip_status in PHP:</h3>";
    echo "<div style='background: " . ($tripStatus === 'upcoming' ? '#d4edda' : '#f8d7da') . "; padding: 20px; font-size: 24px; font-weight: bold;'>";
    echo strtoupper($tripStatus);
    echo "</div>";
    
    echo "<h3>Logic Breakdown:</h3>";
    echo "<ul>";
    echo "<li>trip_started_at is " . ($row['trip_started_at'] === null ? 'NULL' : 'NOT NULL') . "</li>";
    echo "<li>return_date ({$row['return_date']}) >= today ({$row['today']}): " . (strtotime($row['return_date']) >= strtotime(date('Y-m-d')) ? 'TRUE' : 'FALSE') . "</li>";
    echo "<li>Therefore: trip_status = <strong>$tripStatus</strong></li>";
    echo "</ul>";
    
    if ($tripStatus === 'upcoming') {
        echo "<div style='background: #28a745; color: white; padding: 20px; border-radius: 8px;'>";
        echo "<h3>✅ PHP calculation is CORRECT!</h3>";
        echo "<p>The logic works. Now let's fix the API file.</p>";
        echo "</div>";
    }
    
} else {
    echo "<p style='color: red;'>Error: Booking not found</p>";
}

$conn->close();
?>
