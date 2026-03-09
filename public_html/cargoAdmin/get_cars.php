<?php
include "../include/db.php";
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$owner_id = $_GET['owner_id'] ?? 0;

$query = "SELECT * FROM cars WHERE owner_id = $owner_id ORDER BY id DESC";
$result = $conn->query($query);

$cars = [];
while ($row = $result->fetch_assoc()) {
  $cars[] = $row;
}

echo json_encode($cars);
$conn->close();
?>
