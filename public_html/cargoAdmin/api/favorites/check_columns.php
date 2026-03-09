<?php
header('Content-Type: application/json');
require_once '../../include/db.php';

$response = [];

// Get cars table structure
$cars_structure = $conn->query("DESCRIBE cars");
$response['cars_columns'] = [];
while ($row = $cars_structure->fetch_assoc()) {
    $response['cars_columns'][] = $row['Field'];
}

// Get motorcycles table structure
$moto_structure = $conn->query("DESCRIBE motorcycles");
$response['motorcycles_columns'] = [];
while ($row = $moto_structure->fetch_assoc()) {
    $response['motorcycles_columns'][] = $row['Field'];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
