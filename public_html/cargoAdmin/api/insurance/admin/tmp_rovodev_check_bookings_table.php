<?php
/**
 * Check Bookings Table Structure
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Bookings Table Structure</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} table{border-collapse:collapse;} th,td{border:1px solid #ddd;padding:8px;}</style>";

require_once __DIR__ . '/../../../include/db.php';

if (!$conn) {
    die("<p style='color:red;'>❌ Database connection failed!</p>");
}

echo "<p class='success'>✅ Database connected</p>";

// Show bookings table structure
echo "<h2>Bookings Table Columns</h2>";
$result = $conn->query("DESCRIBE bookings");
echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td><strong>{$row['Field']}</strong></td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show a sample booking record
echo "<h2>Sample Booking Record (ID=1)</h2>";
$result = $conn->query("SELECT * FROM bookings WHERE id = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $booking = $result->fetch_assoc();
    echo "<table>";
    foreach ($booking as $key => $value) {
        echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No booking found with ID=1</p>";
}

$conn->close();
?>
