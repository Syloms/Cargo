<?php
/** @var mysqli $conn */
require_once __DIR__ . "/include/db.php";

if (!isset($_POST['id']) || !isset($_POST['status'])) {
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

$moto_id = intval($_POST['id']);
$status = strtolower($_POST['status']); 
$remarks = $_POST['remarks'] ?? "";

// Fetch owner details
$user_q = $conn->prepare("SELECT owner_id, brand, model FROM motorcycles WHERE id=?");
$user_q->bind_param("i", $moto_id);
$user_q->execute();
$user_data = $user_q->get_result()->fetch_assoc();

if (!$user_data) {
    echo json_encode(["error" => "Motorcycle not found"]);
    exit;
}

$owner_id = $user_data['owner_id'];
$moto_name = $user_data['brand'] . " " . $user_data['model'];

// Update motorcycle status
$stmt = $conn->prepare("UPDATE motorcycles SET status=?, remarks=? WHERE id=?");
$stmt->bind_param("ssi", $status, $remarks, $moto_id);
$stmt->execute();

// Send notification
if ($status === "approved") {
    $title = "Motorcycle Approved ✔️";
    $msg = "Your motorcycle '$moto_name' has been approved and is now visible to renters.";
} elseif ($status === "rejected") {
    $title = "Motorcycle Rejected ❌";
    $msg = "Your motorcycle '$moto_name' was rejected. Reason: $remarks";
} else {
    $title = "Motorcycle Status Updated";
    $msg = "Your motorcycle '$moto_name' has been updated.";
}

$noti = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
$noti->bind_param("iss", $owner_id, $title, $msg);
$noti->execute();

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>