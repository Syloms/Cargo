<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../../include/db.php";

$bookingId = intval($_GET['booking_id'] ?? 0);
$ownerId   = intval($_GET['owner_id'] ?? 0);

if ($bookingId <= 0 || $ownerId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT dr.*,
           u.fullname AS renter_name
    FROM damage_reports dr
    LEFT JOIN users u ON dr.renter_id = u.id
    WHERE dr.booking_id = ? AND dr.owner_id = ?
    ORDER BY dr.created_at DESC
    LIMIT 1
");
$stmt->bind_param("ii", $bookingId, $ownerId);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$report) {
    echo json_encode(["success" => true, "report" => null]);
    exit;
}

echo json_encode(["success" => true, "report" => $report]);
?>
