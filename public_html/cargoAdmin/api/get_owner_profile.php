<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../include/db.php';

// Accept BOTH parameters
$car_id   = $_GET['car_id'] ?? null;
$owner_id = $_GET['owner_id'] ?? null;

// --------------------------------------------------
// Resolve owner
// --------------------------------------------------
if ($car_id && is_numeric($car_id)) {
    // Get owner from car
    $ownerQuery = $conn->prepare("
        SELECT u.id, u.fullname, u.email, u.profile_image, u.created_at
        FROM cars c
        INNER JOIN users u ON c.owner_id = u.id
        WHERE c.id = ?
    ");
    $ownerQuery->bind_param("i", $car_id);
    $ownerQuery->execute();
    $ownerResult = $ownerQuery->get_result();

} elseif ($owner_id && is_numeric($owner_id)) {
    // Get owner directly
    $ownerQuery = $conn->prepare("
        SELECT id, fullname, email, profile_image, created_at
        FROM users
        WHERE id = ?
    ");
    $ownerQuery->bind_param("i", $owner_id);
    $ownerQuery->execute();
    $ownerResult = $ownerQuery->get_result();

} else {
    echo json_encode([
        "status" => "error",
        "message" => "car_id or owner_id required"
    ]);
    exit;
}

// --------------------------------------------------
// Validate owner found
// --------------------------------------------------
if ($ownerResult->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Owner not found"
    ]);
    exit;
}

$owner = $ownerResult->fetch_assoc();
$owner_id = $owner['id']; // normalize for queries below

// Build full URL for profile_image
if (!empty($owner['profile_image'])) {
    if (!filter_var($owner['profile_image'], FILTER_VALIDATE_URL)) {
        if (!defined('UPLOADS_URL')) {
            require_once __DIR__ . '/../include/config.php';
        }
        $owner['profile_image'] = UPLOADS_URL . '/profile_images/' . $owner['profile_image'];
    }
} else {
    $owner['profile_image'] = '';
}

// --------------------------------------------------
// Count approved cars
// --------------------------------------------------
$carsQuery = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM cars
    WHERE owner_id = ?
      AND status = 'approved'
");
$carsQuery->bind_param("i", $owner_id);
$carsQuery->execute();
$totalCars = $carsQuery->get_result()->fetch_assoc()['total'] ?? 0;

// --------------------------------------------------
// Reviews + rating
// --------------------------------------------------
$reviewsQuery = $conn->prepare("
    SELECT 
        COUNT(*) AS total_reviews,
        COALESCE(AVG(r.rating), 0) AS avg_rating
    FROM reviews r
    INNER JOIN cars c ON r.car_id = c.id
    WHERE c.owner_id = ?
");
$reviewsQuery->bind_param("i", $owner_id);
$reviewsQuery->execute();
$reviewStats = $reviewsQuery->get_result()->fetch_assoc();

// --------------------------------------------------
// Response
// --------------------------------------------------
echo json_encode([
    "status" => "success",
    "owner" => $owner,
    "total_cars" => (int) $totalCars,
    "total_reviews" => (int) ($reviewStats['total_reviews'] ?? 0),
    "average_rating" => round((float) ($reviewStats['avg_rating'] ?? 0), 1)
]);

$conn->close();
?>
