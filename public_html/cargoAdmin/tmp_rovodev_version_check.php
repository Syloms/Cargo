<?php
/**
 * Version check - verify if the fix is deployed
 */

echo "<h1>Statistics.php Version Check</h1>";
echo "<hr>";

// Check if the updated file exists
$statisticsFile = __DIR__ . '/statistics.php';

if (!file_exists($statisticsFile)) {
    echo "<p style='color: red;'>❌ statistics.php file not found!</p>";
    exit;
}

echo "<p style='color: green;'>✅ statistics.php file found</p>";

// Read the file content
$content = file_get_contents($statisticsFile);

// Check for key fix markers
echo "<h2>Fix Detection:</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; background: white;'>";
echo "<tr style='background: #f0f0f0;'><th>Feature</th><th>Status</th></tr>";

// Check 1: Error handling
$hasErrorHandling = strpos($content, 'error_reporting(E_ALL)') !== false;
echo "<tr>";
echo "<td>Error Handling</td>";
echo "<td>" . ($hasErrorHandling ? "✅ Present" : "❌ Missing") . "</td>";
echo "</tr>";

// Check 2: Try-catch wrapper
$hasTryCatch = strpos($content, 'try {') !== false && strpos($content, 'catch (Exception $e)') !== false;
echo "<tr>";
echo "<td>Try-Catch Protection</td>";
echo "<td>" . ($hasTryCatch ? "✅ Present" : "❌ Missing") . "</td>";
echo "</tr>";

// Check 3: NULL handling for vehicle names
$hasNullHandling = strpos($content, "booking['brand'] ?? ''") !== false;
echo "<tr>";
echo "<td>NULL Handling for Brand/Model</td>";
echo "<td>" . ($hasNullHandling ? "✅ Present" : "❌ Missing") . "</td>";
echo "</tr>";

// Check 4: "Vehicle Not Found" fallback
$hasVehicleNotFound = strpos($content, 'Vehicle Not Found') !== false;
echo "<tr>";
echo "<td>'Vehicle Not Found' Fallback</td>";
echo "<td>" . ($hasVehicleNotFound ? "✅ Present" : "❌ Missing") . "</td>";
echo "</tr>";

// Check 5: Styled display for missing vehicles
$hasStyledDisplay = strpos($content, "vehicleName === 'Vehicle Not Found'") !== false;
echo "<tr>";
echo "<td>Styled Display (Gray Italic)</td>";
echo "<td>" . ($hasStyledDisplay ? "✅ Present" : "❌ Missing") . "</td>";
echo "</tr>";

echo "</table>";

// Overall status
echo "<h2>Overall Status:</h2>";
if ($hasErrorHandling && $hasTryCatch && $hasNullHandling && $hasVehicleNotFound && $hasStyledDisplay) {
    echo "<div style='background: #4caf50; color: white; padding: 20px; border-radius: 8px; text-align: center; font-size: 18px;'>";
    echo "✅ <strong>ALL FIXES ARE DEPLOYED!</strong>";
    echo "</div>";
    echo "<p style='margin-top: 20px;'>The statistics page should now handle missing vehicles correctly.</p>";
    echo "<p><a href='statistics.php' style='display: inline-block; padding: 10px 20px; background: #1a1a1a; color: white; text-decoration: none; border-radius: 4px;'>View Statistics Page →</a></p>";
} else {
    echo "<div style='background: #ff9800; color: white; padding: 20px; border-radius: 8px; text-align: center; font-size: 18px;'>";
    echo "⚠️ <strong>SOME FIXES ARE MISSING!</strong>";
    echo "</div>";
    echo "<p style='margin-top: 20px; color: red;'>The file may not be fully updated. Please upload the latest version.</p>";
    
    echo "<h3>Missing Components:</h3>";
    echo "<ul>";
    if (!$hasErrorHandling) echo "<li>Error handling configuration</li>";
    if (!$hasTryCatch) echo "<li>Try-catch protection wrapper</li>";
    if (!$hasNullHandling) echo "<li>NULL handling for brand/model</li>";
    if (!$hasVehicleNotFound) echo "<li>'Vehicle Not Found' fallback text</li>";
    if (!$hasStyledDisplay) echo "<li>Styled display for missing vehicles</li>";
    echo "</ul>";
}

// Show file modification time
$modTime = filemtime($statisticsFile);
echo "<h3>File Information:</h3>";
echo "<p><strong>Last Modified:</strong> " . date('F d, Y H:i:s', $modTime) . "</p>";
echo "<p><strong>File Size:</strong> " . number_format(filesize($statisticsFile)) . " bytes</p>";

// Check if running on live server
$isLive = isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'cargoph.online') !== false;
echo "<p><strong>Server:</strong> " . ($isLive ? "🌐 LIVE (cargoph.online)" : "💻 LOCAL (localhost)") . "</p>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background: #f5f5f5;
    max-width: 900px;
    margin: 0 auto;
}
h1 {
    color: #333;
    border-bottom: 3px solid #1a1a1a;
    padding-bottom: 10px;
}
h2 {
    color: #555;
    margin-top: 30px;
}
table {
    width: 100%;
    margin: 10px 0;
}
</style>
