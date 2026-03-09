<?php
require_once 'include/db.php';

echo "<h2>Cars Table Structure</h2>";
$result = $conn->query("DESCRIBE cars");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
}
echo "</table>";

echo "<h2>Motorcycles Table Structure</h2>";
$result2 = $conn->query("DESCRIBE motorcycles");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
while ($row = $result2->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
}
echo "</table>";

echo "<h3>Sample Car Data</h3>";
$sample = $conn->query("SELECT * FROM cars LIMIT 1");
if ($sample && $sample->num_rows > 0) {
    $car = $sample->fetch_assoc();
    echo "<pre>" . print_r(array_keys($car), true) . "</pre>";
}

echo "<h3>Sample Motorcycle Data</h3>";
$sample2 = $conn->query("SELECT * FROM motorcycles LIMIT 1");
if ($sample2 && $sample2->num_rows > 0) {
    $moto = $sample2->fetch_assoc();
    echo "<pre>" . print_r(array_keys($moto), true) . "</pre>";
}
?>
