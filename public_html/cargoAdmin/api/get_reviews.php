<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "../include/db.php";

$car_id = intval($_GET['car_id'] ?? 0);

if ($car_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid car ID"
    ]);
    exit;
}

$query = $conn->prepare("
    SELECT
        r.rating,
        r.review AS review,
        r.created_at,
        u.fullname AS reviewer_name,
        IFNULL(u.profile_image, '') AS reviewer_image
    FROM reviews r
    JOIN users u ON u.id = r.renter_id
    WHERE r.car_id = ?
    ORDER BY r.created_at DESC
");

$query->bind_param("i", $car_id);
$query->execute();
$result = $query->get_result();

$reviews = [];

while ($row = $result->fetch_assoc()) {
    $row['rating'] = floatval($row['rating']);

    // avatar fallback
    if ($row['reviewer_image'] !== '') {
        // Load config if not already loaded
        if (!defined('UPLOADS_URL')) {
            require_once __DIR__ . '/../include/config.php';
        }
        // ✅ FIX: Profile images are stored in profile_images subdirectory
        $row['reviewer_image'] = UPLOADS_URL . "/profile_images/" . $row['reviewer_image'];
    } else {
        $row['reviewer_image'] = "https://ui-avatars.com/api/?name=" . urlencode($row['reviewer_name']);
    }

    $reviews[] = $row;
}

echo json_encode([
    "success" => true,
    "reviews" => $reviews
]);

$conn->close();
