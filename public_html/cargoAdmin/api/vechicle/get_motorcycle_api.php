<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include "include/db.php";

// Query to get approved motorcycles with owner info
$query = $conn->query("
    SELECT 
        motorcycles.id,
        motorcycles.owner_id,
        motorcycles.brand,
        motorcycles.model,
        motorcycles.motorcycle_year as year,
        motorcycles.daily_rate AS price,
        motorcycles.image,
        motorcycles.engine_displacement,
        motorcycles.body_style,
        motorcycles.has_unlimited_mileage,
        users.fullname AS owner_name,
        users.address AS location,
        COALESCE(motorcycles.rating, 0) AS rating
    FROM motorcycles
    JOIN users ON users.id = motorcycles.owner_id
    WHERE motorcycles.status = 'approved'
    ORDER BY motorcycles.created_at DESC
");

$motorcycles = [];

while ($row = $query->fetch_assoc()) {
    // Handle missing image
    if (empty($row['image'])) {
        $row['image'] = "https://via.placeholder.com/400x250?text=No+Image";
    }

    $row['rating'] = round(floatval($row['rating']), 1);
    $motorcycles[] = $row;
}

echo json_encode([
    "status" => "success",
    "count" => count($motorcycles),
    "motorcycles" => $motorcycles
]);

$conn->close();
?>