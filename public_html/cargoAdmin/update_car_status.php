<?php
require_once __DIR__ . "/include/db.php";

// Validate input
if (!isset($_POST['id']) || !isset($_POST['status'])) {
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

$car_id = intval($_POST['id']);
$status = strtolower($_POST['status']); 
$remarks = $_POST['remarks'] ?? "";

// Fetch owner details
$user_q = $conn->prepare("SELECT owner_id, brand, model FROM cars WHERE id=?");
$user_q->bind_param("i", $car_id);
$user_q->execute();
$user_data = $user_q->get_result()->fetch_assoc();

if (!$user_data) {
    echo json_encode(["error" => "Car not found"]);
    exit;
}

$owner_id = $user_data['owner_id'];
$car_name = $user_data['brand'] . " " . $user_data['model'];

// Update car status
$stmt = $conn->prepare("UPDATE cars SET status=?, remarks=? WHERE id=?");
$stmt->bind_param("ssi", $status, $remarks, $car_id);
$stmt->execute();


if ($status === "approved") {
    $title = "Car Approved ✔️";
    $msg = "Your vehicle '$car_name' has been approved and is now visible to renters.";
} elseif ($status === "rejected") {
    $title = "Car Rejected ❌";
    $msg = "Your vehicle '$car_name' was rejected. Reason: $remarks";
} elseif ($status === "pending") {
    $title = "Car Disabled ⚠️";
    $msg = "Your vehicle '$car_name' is pending.";
} else {
    $title = "Car Status Updated";
    $msg = "Your vehicle '$car_name' has been updated.";
}

// Send notification to user
$noti = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
$noti->bind_param("iss", $owner_id, $title, $msg);
$noti->execute();

// Redirect back to admin page
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;

?>
