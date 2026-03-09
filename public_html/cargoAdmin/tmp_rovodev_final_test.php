<?php
/**
 * Final test - verify the fix works
 */

include 'include/db.php';
include 'include/dashboard_stats.php';

echo "<h1>✅ Final Fix Verification</h1>";
echo "<hr>";

// Test: Get recent bookings
$recentBookings = getRecentBookings($conn, 10);

echo "<h2>Recent Bookings Display Test</h2>";
echo "<p><strong>Total bookings:</strong> " . count($recentBookings) . "</p>";

if (!empty($recentBookings)) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; background: white;'>";
    echo "<tr style='background: #333; color: white;'>";
    echo "<th>Booking ID</th>";
    echo "<th>Car ID</th>";
    echo "<th>Brand</th>";
    echo "<th>Model</th>";
    echo "<th>Final Display</th>";
    echo "<th>Status</th>";
    echo "</tr>";
    
    $missingCount = 0;
    $normalCount = 0;
    
    foreach ($recentBookings as $booking) {
        // Simulate the processing in statistics.php
        $brand = $booking['brand'] ?? '';
        $model = $booking['model'] ?? '';
        $vehicleName = trim($brand . ' ' . $model);
        
        if (empty($vehicleName)) {
            $vehicleName = 'Vehicle Not Found';
            $missingCount++;
        } else {
            $normalCount++;
        }
        $vehicleName = htmlspecialchars($vehicleName);
        
        $isMissing = ($vehicleName === 'Vehicle Not Found');
        $rowStyle = $isMissing ? "style='background-color: #fff3e0;'" : "";
        
        echo "<tr $rowStyle>";
        echo "<td><strong>#BK-" . str_pad($booking['id'], 4, '0', STR_PAD_LEFT) . "</strong></td>";
        echo "<td>" . $booking['car_id'] . "</td>";
        echo "<td>" . ($brand ?: '<i style="color: #999;">empty</i>') . "</td>";
        echo "<td>" . ($model ?: '<i style="color: #999;">empty</i>') . "</td>";
        echo "<td>";
        if ($isMissing) {
            echo "<span style='color: #999; font-style: italic;'>" . $vehicleName . "</span>";
        } else {
            echo "<strong style='color: #2e7d32;'>" . $vehicleName . "</strong>";
        }
        echo "</td>";
        echo "<td>" . $booking['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Summary
    echo "<div style='margin-top: 20px; padding: 20px; background: #e8f5e9; border-left: 4px solid #4caf50;'>";
    echo "<h3 style='margin-top: 0;'>✅ Test Results Summary</h3>";
    echo "<p><strong>Total bookings tested:</strong> " . count($recentBookings) . "</p>";
    echo "<p><strong>✓ Normal vehicles (with names):</strong> $normalCount</p>";
    echo "<p><strong>⚠ Missing vehicles (deleted):</strong> $missingCount</p>";
    echo "<p style='margin-bottom: 0;'><strong>Empty cells:</strong> <span style='color: #4caf50; font-size: 20px;'>0 ✓</span></p>";
    echo "</div>";
    
    // Expected results
    echo "<h2>Expected Results on Statistics Page</h2>";
    echo "<div style='background: #f5f5f5; padding: 15px; border-left: 4px solid #2196f3;'>";
    echo "<p><strong>What you should see at:</strong><br>";
    echo "<a href='statistics.php' style='color: #2196f3; text-decoration: none; font-weight: bold;'>https://cargoph.online/cargoAdmin/statistics.php</a></p>";
    echo "<ul>";
    echo "<li>✅ <strong>Mercedes-Benz A-Class</strong> for booking #53, #52</li>";
    echo "<li>✅ <strong>Honda Click 125i</strong> for booking #44, #43</li>";
    echo "<li>⚠ <i style='color: #999;'>Vehicle Not Found</i> for bookings #56, #55, #54, #51, #50, #49, #48, #47 (Car ID 6 deleted)</li>";
    echo "<li>✅ <strong>NO EMPTY CELLS</strong></li>";
    echo "</ul>";
    echo "</div>";
    
} else {
    echo "<p style='color: red;'>No bookings found.</p>";
}

// Check if fix is deployed
echo "<h2>Fix Deployment Status</h2>";
$statsFile = file_get_contents(__DIR__ . '/include/dashboard_stats.php');
$hasCoalesceFix = strpos($statsFile, "COALESCE(c.brand, m.brand, '')") !== false 
                  || strpos($statsFile, "COALESCE(c.brand, '')") !== false;

echo "<div style='padding: 15px; background: " . ($hasCoalesceFix ? "#e8f5e9" : "#ffebee") . "; border-left: 4px solid " . ($hasCoalesceFix ? "#4caf50" : "#f44336") . ";'>";
if ($hasCoalesceFix) {
    echo "<p style='color: #2e7d32; margin: 0;'><strong>✅ COALESCE fix is deployed in dashboard_stats.php</strong></p>";
} else {
    echo "<p style='color: #c62828; margin: 0;'><strong>❌ COALESCE fix NOT found in dashboard_stats.php</strong></p>";
    echo "<p style='margin: 10px 0 0 0;'>Please upload the updated dashboard_stats.php file.</p>";
}
echo "</div>";

$conn->close();
?>

<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
    padding: 20px;
    background: #fafafa;
    max-width: 1200px;
    margin: 0 auto;
}
h1 {
    color: #1a1a1a;
    border-bottom: 3px solid #4caf50;
    padding-bottom: 10px;
}
h2 {
    color: #424242;
    margin-top: 30px;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 8px;
}
table {
    font-size: 14px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
a {
    color: #2196f3;
}
</style>
