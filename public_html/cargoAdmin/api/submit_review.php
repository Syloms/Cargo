<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

error_reporting(0);
require_once "../include/db.php";
require_once __DIR__ . "/security/suspension_guard.php";

/* ---------- INPUT ---------- */
$user_id    = (int)($_POST['user_id'] ?? 0);
$booking_id = (int)($_POST['booking_id'] ?? 0);
$car_id     = (int)($_POST['car_id'] ?? 0);
$owner_id   = (int)($_POST['owner_id'] ?? 0);

$car_rating   = (float)($_POST['car_rating'] ?? 0);
$owner_rating = (float)($_POST['owner_rating'] ?? 0);

$car_review   = trim($_POST['car_review'] ?? '');
$owner_review = trim($_POST['owner_review'] ?? '');

$car_categories   = json_decode($_POST['car_categories'] ?? '[]', true);
$owner_categories = json_decode($_POST['owner_categories'] ?? '[]', true);

/* ---------- BASIC VALIDATION ---------- */
if ($user_id <= 0 || $booking_id <= 0 || $car_id <= 0 || $owner_id <= 0) {
    echo json_encode(["success"=>false,"message"=>"Invalid IDs"]);
    exit;
}

// Block suspended users
require_not_suspended($conn, $user_id);

if ($car_rating <= 0 || $owner_rating <= 0) {
    echo json_encode(["success"=>false,"message"=>"Rating required"]);
    exit;
}

if ($car_review === '' || $owner_review === '') {
    echo json_encode(["success"=>false,"message"=>"Review required"]);
    exit;
}

/* ---------- ELIGIBILITY CHECK ---------- */
// Check if booking exists and belongs to user
$check = $conn->prepare("
    SELECT id, status, IFNULL(is_reviewed,0) as is_reviewed
    FROM bookings
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");
$check->bind_param("ii", $booking_id, $user_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success"=>false,
        "message"=>"Booking not found or you don't have permission"
    ]);
    exit;
}

$booking = $result->fetch_assoc();

// Check if already reviewed
if ($booking['is_reviewed'] == 1) {
    echo json_encode([
        "success"=>false,
        "message"=>"You have already reviewed this booking"
    ]);
    exit;
}

// Allow reviews for completed bookings (and any other status if needed)
// Remove status restriction to allow reviews anytime after booking
$check->close();

/* ---------- PREPARE DATA ---------- */

// Final rating = average of car + owner
$rating = round(($car_rating + $owner_rating) / 2, 1);

// Combine reviews
$review = "CAR REVIEW:\n$car_review\n\nOWNER REVIEW:\n$owner_review";

// Store breakdown
$categories = json_encode([
    "car_rating"   => $car_rating,
    "owner_rating" => $owner_rating,
    "car"          => $car_categories,
    "owner"        => $owner_categories
]);

/* ---------- INSERT ---------- */
$stmt = $conn->prepare("
    INSERT INTO reviews
    (booking_id, car_id, renter_id, owner_id, rating, review, categories)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iiiidss",
    $booking_id,
    $car_id,
    $user_id,
    $owner_id,
    $rating,
    $review,
    $categories
);

if ($stmt->execute()) {

    $upd = $conn->prepare("UPDATE bookings SET is_reviewed = 1 WHERE id = ?");
    $upd->bind_param("i", $booking_id);
    $upd->execute();
    $upd->close();

    echo json_encode([
        "success"=>true,
        "message"=>"Review submitted successfully"
    ]);
} else {
    echo json_encode([
        "success"=>false,
        "message"=>"Database insert failed"
    ]);
}

$stmt->close();
$conn->close();