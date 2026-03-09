<?php
include "../include/db.php";

$car_id = $_POST['car_id'];
$user_id = $_POST['user_id'];
$rating = $_POST['rating'];
$review = $_POST['review'] ?? null;

// Prevent duplicate rating from same user
$check = $conn->prepare("SELECT * FROM car_ratings WHERE car_id=? AND user_id=?");
$check->bind_param("ii", $car_id, $user_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "You already rated this car"]);
    exit;
}

// Insert rating
$stmt = $conn->prepare("INSERT INTO car_ratings (car_id, user_id, rating, review) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $car_id, $user_id, $rating, $review);
$stmt->execute();

// Update average rating in cars table
$conn->query("
    UPDATE cars SET rating = (
        SELECT AVG(rating) FROM car_ratings WHERE car_id = $car_id
    )
    WHERE id = $car_id
");

echo json_encode(["status" => "success", "message" => "Rating submitted successfully"]);
