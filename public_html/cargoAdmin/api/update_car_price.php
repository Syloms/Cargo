<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../include/db.php';

$car_id       = isset($_POST['car_id'])       ? intval($_POST['car_id'])           : 0;
$owner_id     = isset($_POST['owner_id'])     ? intval($_POST['owner_id'])         : 0;
$vehicle_type = isset($_POST['vehicle_type']) ? trim($_POST['vehicle_type'])       : 'car';
$new_price    = isset($_POST['new_price'])    ? floatval($_POST['new_price'])      : 0;

// Validate inputs
if ($car_id <= 0 || $owner_id <= 0) {
    echo json_encode(["success" => false, "message" => "car_id and owner_id are required"]);
    exit;
}

if ($new_price < 300) {
    echo json_encode(["success" => false, "message" => "Minimum price is ₱300 per day"]);
    exit;
}

if ($new_price > 50000) {
    echo json_encode(["success" => false, "message" => "Maximum price is ₱50,000 per day"]);
    exit;
}

$table = ($vehicle_type === 'motorcycle') ? 'motorcycles' : 'cars';

// Verify ownership before updating
$check = $conn->prepare("SELECT id FROM $table WHERE id = ? AND owner_id = ?");
$check->bind_param("ii", $car_id, $owner_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Vehicle not found or access denied"]);
    exit;
}

// Update the price
$stmt = $conn->prepare("UPDATE $table SET price_per_day = ? WHERE id = ? AND owner_id = ?");
$stmt->bind_param("dii", $new_price, $car_id, $owner_id);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Failed to update price: " . $stmt->error]);
    exit;
}

echo json_encode([
    "success"       => true,
    "message"       => "Price updated successfully",
    "new_price"     => $new_price,
    "car_id"        => $car_id,
    "vehicle_type"  => $vehicle_type,
]);

$conn->close();
?>
