<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . "/../../include/db.php";

$response = [
    "success" => false,
    "bookings" => [],
    "count" => 0,
    "message" => ""
];

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
    b.rejection_reason,
    b.rejected_at,
    b.refund_status,
    b.refund_requested,
    b.refund_amount,
    b.escrow_status,
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
AND b.status = 'rejected'
ORDER BY b.rejected_at DESC
";

// Prepare
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();

// Bind results
$stmt->bind_result(
    $booking_id,
    $total_amount,
    $pickup_date,
    $return_date,
    $status,
    $vehicle_type,
    $rejection_reason,
    $rejected_at,
    $refund_status,
    $refund_requested,
    $refund_amount,
    $escrow_status,
    $renter_name,
    $renter_contact,
    $brand,
    $model,
    $car_image,
    $car_full_name
);

$bookings = [];

// Fetch rows
while ($stmt->fetch()) {
    // DB stores path like "uploads/car_xxx.jpg"
    $imagePath = !empty($car_image)
        ? $car_image
        : "uploads/default_car.png";

    $bookings[] = [
        "booking_id" => $booking_id,
        "total_amount" => $total_amount,
        "pickup_date" => $pickup_date,
        "return_date" => $return_date,
        "status" => $status,
        "vehicle_type" => $vehicle_type,
        "rejection_reason" => $rejection_reason,
        "rejected_at" => $rejected_at,
        "renter_name" => $renter_name,
        "renter_contact" => $renter_contact,
        "car_image" => $imagePath,
        "car_full_name" => $car_full_name,
        "refund_status" => $refund_status ?? 'not_requested',
        "refund_requested" => (int)($refund_requested ?? 0),
        "refund_amount" => (float)($refund_amount ?? 0),
        "escrow_status" => $escrow_status ?? null
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
