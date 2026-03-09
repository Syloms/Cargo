<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "../include/db.php";

/* --------------------------------------------
   Validate Car ID
-------------------------------------------- */
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Car ID missing"
    ]);
    exit;
}

$carId = intval($_GET['id']);
// Load config for BASE_URL
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../include/config.php';
}
$baseUrl = BASE_URL . "/";

/* --------------------------------------------
   Fetch Car + Owner Information
-------------------------------------------- */
$query = $conn->query("
    SELECT cars.*, 
           users.id AS owner_id,              
           users.fullname AS owner_name, 
           users.profile_image AS owner_image,
           users.phone AS owner_phone
    FROM cars
    JOIN users ON users.id = cars.owner_id
    WHERE cars.id = $carId
");

if (!$query) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error (car query)"
    ]);
    exit;
}

if ($query->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Car not found"
    ]);
    exit;
}

$car = $query->fetch_assoc();

/* --------------------------------------------
   Fix Images (NO INTERNET PLACEHOLDERS)
-------------------------------------------- */
$cleanImage = str_replace(["uploads/uploads/", "uploads//"], "uploads/", $car["image"]);
$car["image"] = !empty($cleanImage) ? $baseUrl . $cleanImage : "";

/* Owner image */
$car["owner_image"] = !empty($car["owner_image"])
    ? $baseUrl . "uploads/profile_images/" . $car["owner_image"]
    : "";


/* --------------------------------------------
   Fix Location
-------------------------------------------- */
$car["location"] = (!empty($car["location"]) && $car["location"] !== "0")
    ? $car["location"]
    : "Location not set";

/* Expose Phone */
$car["phone"] = $car["owner_phone"] ?? "";

/* --------------------------------------------
   Fetch Reviews
-------------------------------------------- */
/* --------------------------------------------
   Fetch Reviews (FIXED)
-------------------------------------------- */
$reviews = [];
$totalRating = 0;

$reviewStmt = $conn->prepare("
    SELECT 
        r.id,
        r.rating,
        r.review,
        r.categories,
        r.created_at,
        u.fullname AS reviewer_name,
        u.profile_image AS reviewer_image
    FROM reviews r
    JOIN users u ON u.id = r.renter_id
    WHERE r.car_id = ?
    ORDER BY r.created_at DESC
");

$reviewStmt->bind_param("i", $carId);
$reviewStmt->execute();
$reviewResult = $reviewStmt->get_result();

while ($row = $reviewResult->fetch_assoc()) {
    $row['rating'] = floatval($row['rating']);
    // ✅ FIX: Profile images are stored in profile_images subdirectory
    $row['reviewer_image'] = !empty($row['reviewer_image'])
        ? $baseUrl . "uploads/profile_images/" . $row['reviewer_image']
        : "https://ui-avatars.com/api/?name=" . urlencode($row['reviewer_name'] ?? 'User');

    $totalRating += floatval($row['rating']);
    $reviews[] = $row;
}


/* Rating Summary */
$car["review_count"] = count($reviews);
$car["average_rating"] = count($reviews) > 0
    ? round($totalRating / count($reviews), 1)
    : 0.0;

/* --------------------------------------------
   Final Output (ALWAYS JSON)
-------------------------------------------- */
echo json_encode([
    "status" => "success",
    "car" => $car,
    "reviews" => $reviews
]);

exit;
?>