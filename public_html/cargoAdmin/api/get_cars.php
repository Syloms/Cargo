<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include "../include/db.php";

// Updated query WITH owner info
$query = $conn->query("
    SELECT 
        cars.id,
        cars.owner_id,
        cars.brand,
        cars.model,
        cars.car_year,
        cars.price_per_day AS price,
        cars.image,
        cars.seat,
        cars.has_unlimited_mileage,
        users.fullname AS owner_name,
        users.address AS location,
        COALESCE(cars.rating, 0) AS rating,
        COALESCE(cars.seat, 4) AS seats
    FROM cars
    JOIN users ON users.id = cars.owner_id
    WHERE cars.status = 'approved'
    ORDER BY cars.created_at DESC
");

$cars = [];

while ($row = $query->fetch_assoc()) {

    if (empty($row['image'])) {
        $row['image'] = "https://via.placeholder.com/400x250?text=No+Image";
    }

    $row['rating'] = round(floatval($row['rating']), 1);
    $cars[] = $row;
}

echo json_encode([
    "status" => "success",
    "count" => count($cars),
    "cars" => $cars
]);

$conn->close();
?>