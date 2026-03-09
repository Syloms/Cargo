<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production, log them instead

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once '../../include/db.php';
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection error',
        'debug' => $e->getMessage()
    ]);
    exit;
}

try {
    // Get user_id from query parameters
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $vehicle_type = isset($_GET['vehicle_type']) ? $_GET['vehicle_type'] : ''; // Optional filter

    if ($user_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid user ID'
        ]);
        exit;
    }

    // Build query based on filters
    $favorites = [];
    
    // Get car favorites
    if (empty($vehicle_type) || $vehicle_type === 'car') {
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
                c.price_per_day as price,
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
        
        $car_stmt = $conn->prepare($car_sql);
        $car_stmt->bind_param("i", $user_id);
        $car_stmt->execute();
        $car_result = $car_stmt->get_result();
        
        while ($row = $car_result->fetch_assoc()) {
            $favorites[] = [
                'favorite_id' => $row['favorite_id'],
                'vehicle_type' => 'car',
                'vehicle_id' => $row['id'],
                'id' => $row['id'],
                'brand' => $row['brand'],
                'model' => $row['model'],
                'year' => $row['car_year'],
                'price' => $row['price'],
                'location' => $row['location'],
                'seats' => $row['seat'],
                'transmission' => $row['transmission'],
                'body_style' => $row['body_style'],
                'rating' => $row['rating'],
                'image' => $row['image'],
                'has_unlimited_mileage' => $row['has_unlimited_mileage'],
                'status' => $row['status'],
                'added_at' => $row['created_at']
            ];
        }
        $car_stmt->close();
    }
    
    // Get motorcycle favorites
    if (empty($vehicle_type) || $vehicle_type === 'motorcycle') {
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
                m.price_per_day as price,
                m.location,
                m.body_style,
                m.transmission_type,
                m.rating,
                m.image,
                m.has_unlimited_mileage,
                m.status
            FROM favorites f
            INNER JOIN motorcycles m ON f.vehicle_id = m.id
            WHERE f.user_id = ? AND f.vehicle_type = 'motorcycle' AND m.status = 'approved'
            ORDER BY f.created_at DESC
        ";
        
        $moto_stmt = $conn->prepare($moto_sql);
        $moto_stmt->bind_param("i", $user_id);
        $moto_stmt->execute();
        $moto_result = $moto_stmt->get_result();
        
        while ($row = $moto_result->fetch_assoc()) {
            $favorites[] = [
                'favorite_id' => $row['favorite_id'],
                'vehicle_type' => 'motorcycle',
                'vehicle_id' => $row['id'],
                'id' => $row['id'],
                'brand' => $row['brand'],
                'model' => $row['model'],
                'year' => $row['motorcycle_year'],
                'price' => $row['price'],
                'location' => $row['location'],
                'body_style' => $row['body_style'],
                'transmission' => $row['transmission_type'],
                'rating' => $row['rating'],
                'image' => $row['image'],
                'has_unlimited_mileage' => $row['has_unlimited_mileage'],
                'status' => $row['status'],
                'added_at' => $row['created_at']
            ];
        }
        $moto_stmt->close();
    }

    echo json_encode([
        'status' => 'success',
        'favorites' => $favorites,
        'count' => count($favorites)
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
