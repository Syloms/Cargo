<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../include/db.php";

$owner_id = isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0;

if ($owner_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing owner_id"]);
    exit;
}

// Load config for UPLOADS_URL
if (!defined('UPLOADS_URL')) {
    require_once __DIR__ . '/../include/config.php';
}
$baseURL = UPLOADS_URL . "/";

// SQL query - Support both cars and motorcycles
$sql = "SELECT 
          b.id AS booking_id,
          b.car_id,
          b.vehicle_type,
          COALESCE(c.brand, m.brand) AS car_name,
          COALESCE(c.image, m.image) AS car_image,
          COALESCE(c.model, m.model) AS model,
          COALESCE(c.car_year, m.motorcycle_year) AS year,
          b.user_id,
          b.full_name,
          b.email,
          b.contact AS contact,
          b.pickup_date,
          b.return_date,
          b.pickup_time,
          b.return_time,
          b.rental_period,
          b.needs_delivery,
          b.total_amount,
          b.status,
          b.created_at
        FROM bookings b
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.owner_id = ? AND b.status = 'pending' AND b.payment_status = 'paid'
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

$requests = [];

while ($row = $result->fetch_assoc()) {

    // FIX IMAGE URL HERE
    if (!empty($row["car_image"])) {
        $row["car_image"] = $baseURL . basename($row["car_image"]);

    }

    $requests[] = $row;
}

echo json_encode(["success" => true, "requests" => $requests]);

$stmt->close();
$conn->close();
?>
