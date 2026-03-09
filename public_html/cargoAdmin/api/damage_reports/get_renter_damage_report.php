<?php
/**
 * Get Damage Report for Renter
 * Called by the renter's app to check if a damage report exists for their booking
 * and what the current status/outcome is.
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../../include/db.php";

$bookingId = intval($_GET['booking_id'] ?? 0);
$renterId  = intval($_GET['renter_id'] ?? 0);

if ($bookingId <= 0 || $renterId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        dr.id,
        dr.booking_id,
        dr.damage_types,
        dr.description,
        dr.estimated_cost,
        dr.status,
        dr.approved_amount,
        dr.admin_notes,
        dr.image_1,
        dr.image_2,
        dr.image_3,
        dr.image_4,
        dr.created_at,
        dr.reviewed_at,
        u.fullname AS owner_name
    FROM damage_reports dr
    LEFT JOIN users u ON dr.owner_id = u.id
    WHERE dr.booking_id = ? AND dr.renter_id = ?
    ORDER BY dr.created_at DESC
    LIMIT 1
");
$stmt->bind_param("ii", $bookingId, $renterId);
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
