<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../../include/db.php';

// Get user_id from query or show all
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

$debug = [
    'requested_user_id' => $user_id,
    'all_favorites' => [],
    'favorites_with_vehicles' => [],
];

// Get all favorites
$all_sql = "SELECT * FROM favorites ORDER BY created_at DESC";
$all_result = $conn->query($all_sql);
while ($row = $all_result->fetch_assoc()) {
    $debug['all_favorites'][] = $row;
}

if ($user_id > 0) {
    // Get favorites for specific user with vehicle details
    $car_sql = "
        SELECT 
            f.id as favorite_id,
            f.vehicle_type,
            f.vehicle_id,
            f.created_at,
            c.id,
            c.brand,
            c.model,
            c.car_year,
            c.price,
            c.location,
            c.seat,
            c.transmission,
            c.body_style,
            c.rating,
            c.image,
            c.has_unlimited_mileage,
            c.status
        FROM favorites f
        INNER JOIN cars c ON f.vehicle_id = c.id
        WHERE f.user_id = ? AND f.vehicle_type = 'car'
        ORDER BY f.created_at DESC
    ";
    
    $car_stmt = $conn->prepare($car_sql);
    $car_stmt->bind_param("i", $user_id);
    $car_stmt->execute();
    $car_result = $car_stmt->get_result();
    
    while ($row = $car_result->fetch_assoc()) {
        $debug['favorites_with_vehicles'][] = [
            'type' => 'car',
            'data' => $row
        ];
    }
    
    // Get motorcycles
    $moto_sql = "
        SELECT 
            f.id as favorite_id,
            f.vehicle_type,
            f.vehicle_id,
            f.created_at,
            m.id,
            m.brand,
            m.model,
            m.motorcycle_year,
            m.price,
            m.location,
            m.body_style,
            m.transmission_type,
            m.rating,
            m.image,
            m.has_unlimited_mileage,
            m.status
        FROM favorites f
        INNER JOIN motorcycles m ON f.vehicle_id = m.id
        WHERE f.user_id = ? AND f.vehicle_type = 'motorcycle'
        ORDER BY f.created_at DESC
    ";
    
    $moto_stmt = $conn->prepare($moto_sql);
    $moto_stmt->bind_param("i", $user_id);
    $moto_stmt->execute();
    $moto_result = $moto_stmt->get_result();
    
    while ($row = $moto_result->fetch_assoc()) {
        $debug['favorites_with_vehicles'][] = [
            'type' => 'motorcycle',
            'data' => $row
        ];
    }
    
    $debug['total_found'] = count($debug['favorites_with_vehicles']);
}

// Check if car 37 exists
$car_check = $conn->query("SELECT id, brand, model, status FROM cars WHERE id = 37");
if ($car_check && $car_check->num_rows > 0) {
    $debug['car_37_exists'] = true;
    $debug['car_37_details'] = $car_check->fetch_assoc();
} else {
    $debug['car_37_exists'] = false;
}

echo json_encode($debug, JSON_PRETTY_PRINT);
$conn->close();
?>
