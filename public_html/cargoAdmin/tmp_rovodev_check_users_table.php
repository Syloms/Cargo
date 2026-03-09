<?php
require_once 'include/db.php';

echo "<h2>Users Table Structure</h2>";
$result = $conn->query("DESCRIBE users");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
}
echo "</table>";

echo "<h3>Sample User Data</h3>";
$sample = $conn->query("SELECT * FROM users LIMIT 1");
if ($sample && $sample->num_rows > 0) {
    $user = $sample->fetch_assoc();
    echo "<pre>" . print_r(array_keys($user), true) . "</pre>";
}
?>
