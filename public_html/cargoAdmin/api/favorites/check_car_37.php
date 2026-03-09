<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once '../../include/db.php';

$response = [
    'checking_car_id' => 37,
];

// Check if car 37 exists
$car_query = $conn->query("SELECT * FROM cars WHERE id = 37");
if ($car_query && $car_query->num_rows > 0) {
    $response['car_exists'] = true;
    $response['car_details'] = $car_query->fetch_assoc();
} else {
    $response['car_exists'] = false;
}

// Test the exact query that get_favorites.php uses
$user_id = 15;
$test_sql = "
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
    WHERE f.user_id = ? AND f.vehicle_type = 'car' AND c.status = 'approved'
    ORDER BY f.created_at DESC
";

$stmt = $conn->prepare($test_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$response['join_query_results'] = [];
while ($row = $result->fetch_assoc()) {
    $response['join_query_results'][] = $row;
}

$response['join_query_count'] = count($response['join_query_results']);

// Also test without the status filter
$test_sql_no_filter = "
    SELECT 
        f.id as favorite_id,
        f.vehicle_type,
        f.vehicle_id,
        f.created_at,
        c.id,
        c.brand,
        c.model,
        c.status
    FROM favorites f
    INNER JOIN cars c ON f.vehicle_id = c.id
    WHERE f.user_id = ? AND f.vehicle_type = 'car'
    ORDER BY f.created_at DESC
";

$stmt2 = $conn->prepare($test_sql_no_filter);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

$response['without_status_filter'] = [];
while ($row = $result2->fetch_assoc()) {
    $response['without_status_filter'][] = $row;
}

echo json_encode($response, JSON_PRETTY_PRINT);
$conn->close();
?>
