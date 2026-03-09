<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include '../include/db.php';

$owner_id = $_GET['owner_id'] ?? null;

if (!$owner_id) {
    echo json_encode(['success' => false, 'message' => 'Owner ID required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            b.id,
            b.car_id,
            b.user_id,
            b.pickup_date,
            b.return_date,
            b.pickup_time,
            b.return_time,
            b.total_amount,
            b.rental_period,
            b.full_name as renter_name,
            b.contact as renter_contact,
            b.email as renter_email,
            b.status,
            b.created_at,
            c.brand,
            c.model,
            c.car_year,
            c.image as car_image,
            c.location,
            c.latitude,
            c.longitude,
            c.seat,
            c.transmission,
            c.price_per_day
        FROM bookings b
        LEFT JOIN cars c ON b.car_id = c.id
        WHERE b.owner_id = ? 
        AND b.status = 'approved'
        AND b.pickup_date <= CURDATE()
        AND b.return_date >= CURDATE()
        ORDER BY b.pickup_date ASC
    ");
    
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    echo json_encode([
        'success' => true,
        'bookings' => $bookings,
        'count' => count($bookings)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>