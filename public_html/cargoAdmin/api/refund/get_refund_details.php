<?php
declare(strict_types=1);
header('Content-Type: application/json');

session_start();
require_once '../../include/db.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid refund ID']);
    exit;
}

$sql = "
SELECT 
    r.*,

    -- Renter
    u_renter.fullname AS renter_name,
    u_renter.email AS renter_email,
    u_renter.phone AS renter_phone,

    -- Owner
    u_owner.fullname AS owner_name,

    -- Booking
    b.pickup_date,
    b.return_date,

    -- Vehicle
    CONCAT(
        COALESCE(c.brand, m.brand), ' ',
        COALESCE(c.model, m.model), ' ',
        COALESCE(c.car_year, m.motorcycle_year)
    ) AS car_full_name

FROM refunds r
LEFT JOIN users u_renter ON r.user_id = u_renter.id
LEFT JOIN bookings b ON r.booking_id = b.id
LEFT JOIN users u_owner ON b.owner_id = u_owner.id
LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id

WHERE r.id = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Refund not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'refund' => $res->fetch_assoc()
]);
