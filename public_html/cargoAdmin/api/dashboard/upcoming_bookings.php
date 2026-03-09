<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ✅ FIXED: Changed from '../config.php' to '../../include/db.php'
require_once '../../include/db.php';

$owner_id = $_GET['owner_id'] ?? null;

if (!$owner_id) {
    echo json_encode(['success' => false, 'message' => 'Owner ID required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            b.id as booking_id,
            b.vehicle_type,
            COALESCE(c.image, m.image) as car_image,
            CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as car_full_name,
            b.full_name as renter_name,
            b.pickup_date,
            b.return_date,
            b.status,
            b.total_amount,
            b.rental_period
        FROM bookings b
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.owner_id = ? 
        AND b.status = 'approved'
        AND b.pickup_date >= CURDATE()
        ORDER BY b.pickup_date ASC
        LIMIT 10
    ");
    $stmt->bind_param("s", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        // Process car_image to remove 'uploads/' prefix if present
        // Flutter's getCarImageUrl() will add it back
        $carImage = $row['car_image'] ?? '';
        if (!empty($carImage)) {
            // Remove 'uploads/' prefix if present
            if (strpos($carImage, 'uploads/') === 0) {
                $carImage = substr($carImage, 8); // Remove 'uploads/'
            }
        } else {
            $carImage = 'default_car.png';
        }
        
        $row['car_image'] = $carImage;
        $bookings[] = $row;
    }

    echo json_encode([
        'success' => true,
        'bookings' => $bookings
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>