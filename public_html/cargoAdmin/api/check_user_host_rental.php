<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../include/db.php";

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$car_id  = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;

if ($user_id === 0 || $car_id === 0) {
    echo json_encode([
        "has_rented" => false,
        "message" => "Missing user_id or car_id"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT b.id
         FROM bookings b
         WHERE b.user_id = ?
           AND b.car_id = ?
           AND b.status IN ('approved', 'returned', 'completed')
           AND b.return_date IS NOT NULL
           AND (b.is_reviewed = 0 OR b.is_reviewed IS NULL)
         LIMIT 1"
    );

    $stmt->bind_param("ii", $user_id, $car_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo json_encode([
        "has_rented" => $result->num_rows > 0
    ]);

} catch (Exception $e) {
    echo json_encode([
        "has_rented" => false,
        "error" => "Server error"
    ]);
}
?>
