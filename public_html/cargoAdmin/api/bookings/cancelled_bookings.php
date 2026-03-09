<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . "/../../include/db.php";

$response = ["success" => false, "bookings" => [], "count" => 0, "message" => ""];

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response["message"] = "Invalid request method";
    echo json_encode($response);
    exit;
}

if (!isset($_GET['owner_id'])) {
    $response["message"] = "Owner ID is required";
    echo json_encode($response);
    exit;
}

$owner_id = intval($_GET['owner_id']);

// SQL Query - Support both cars and motorcycles
$sql = "
SELECT 
    b.id AS booking_id,
    b.total_amount,
    b.pickup_date,
    b.return_date,
    b.status,
    b.vehicle_type,
    b.cancellation_reason,
    b.cancelled_at,
    b.refund_status,
    b.refund_requested,
    b.refund_amount,
    u.fullname AS renter_name,
    u.phone AS renter_contact,
    COALESCE(c.brand, m.brand) AS brand,
    COALESCE(c.model, m.model) AS model,
    COALESCE(c.image, m.image) AS car_image,
    CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) AS car_full_name
FROM bookings b
INNER JOIN users u ON b.user_id = u.id
LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
WHERE b.owner_id = ?
AND b.status = 'cancelled'
ORDER BY b.cancelled_at DESC
";

# Prepare and execute
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];

// Fetch rows using get_result() for better compatibility
while ($row = $result->fetch_assoc()) {
    $imagePath = !empty($row['car_image'])
        ? $row['car_image']
        : "uploads/default_car.png";

    $bookings[] = [
        "booking_id" => $row['booking_id'],
        "total_amount" => $row['total_amount'],
        "pickup_date" => $row['pickup_date'],
        "return_date" => $row['return_date'],
        "status" => $row['status'],
        "vehicle_type" => $row['vehicle_type'],
        "cancellation_reason" => $row['cancellation_reason'],
        "cancelled_at" => $row['cancelled_at'],
        "renter_name" => $row['renter_name'],
        "renter_contact" => $row['renter_contact'],
        "car_image" => $imagePath,
        "car_full_name" => $row['car_full_name'],
        "refund_status" => $row['refund_status'] ?? 'not_requested',
        "refund_requested" => (int)($row['refund_requested'] ?? 0),
        "refund_amount" => (float)($row['refund_amount'] ?? 0)
    ];
}

// Response
$response["success"] = true;
$response["bookings"] = $bookings;
$response["count"] = count($bookings);

echo json_encode($response);

$stmt->close();
$conn->close();
exit;
