<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "../include/db.php";

/* ---------- VALIDATE OWNER ID ---------- */
if (!isset($_GET['owner_id']) || empty($_GET['owner_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Owner ID is required"
    ]);
    exit;
}

$owner_id = intval($_GET['owner_id']);

/* ---------- FETCH OWNER REVIEWS ---------- */
$query = $conn->prepare("
    SELECT 
        r.id,
        r.rating,
        r.review,
        r.categories,
        r.created_at,

        u.fullname,
        c.brand,
        c.model,
        c.car_year

    FROM reviews r
    JOIN users u ON u.id = r.renter_id
    JOIN cars c ON c.id = r.car_id
    WHERE r.owner_id = ?
    ORDER BY r.created_at DESC
");

$query->bind_param("i", $owner_id);
$query->execute();
$result = $query->get_result();

$reviews = [];
$totalRating = 0;

while ($row = $result->fetch_assoc()) {
    $totalRating += floatval($row['rating']);

    $reviews[] = [
        "fullname"    => $row["fullname"],
        "rating"      => (float)$row["rating"],
        "comment"     => $row["review"],
        "categories"  => json_decode($row["categories"], true),
        "created_at"  => $row["created_at"],
        "brand"       => $row["brand"],
        "model"       => $row["model"],
        "car_year"    => $row["car_year"]
    ];
}

/* ---------- RESPONSE ---------- */
echo json_encode([
    "status" => "success",
    "total_reviews" => count($reviews),
    "average_rating" => count($reviews) > 0 
        ? round($totalRating / count($reviews), 1) 
        : 0,
    "reviews" => $reviews
]);

$conn->close();
