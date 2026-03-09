<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include database connection from include folder
include '../include/db.php';  // ✅ FIXED PATH

// Get owner_id from query parameter
$owner_id = $_GET['owner_id'] ?? '';

// Validate owner_id
if (empty($owner_id)) {
    echo json_encode([
        "status" => "error", 
        "message" => "Owner ID is required"
    ]);
    exit;
}

// Query to get only approved cars for this owner
$query = $conn->prepare("
    SELECT 
        id, 
        brand, 
        model, 
        car_year, 
        image, 
        price_per_day,
        location,
        rating,
        seat,
        has_unlimited_mileage,
        status,
        owner_id
    FROM cars 
    WHERE owner_id = ? AND status = 'approved'
    ORDER BY created_at DESC
");

$query->bind_param("s", $owner_id);
$query->execute();
$result = $query->get_result();

$cars = [];
while ($row = $result->fetch_assoc()) {
    $cars[] = $row;
}

echo json_encode([
    "status" => "success",
    "total_cars" => count($cars),
    "cars" => $cars
]);

$conn->close();
?>