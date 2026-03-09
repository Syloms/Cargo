<?php
/**
 * Get Overdue Bookings API
 * Returns list of overdue rentals with details
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../include/db.php';

// Check if owner_id is provided (for owner-specific view)
$ownerId = $_GET['owner_id'] ?? null;
$severity = $_GET['severity'] ?? 'all'; // all, overdue, severely_overdue

// Build query
$sql = "SELECT 
    b.id as booking_id,
    b.user_id,
    b.owner_id,
    b.car_id,
    b.vehicle_type,
    b.pickup_date,
    b.return_date,
    b.return_time,
    b.total_amount,
    b.overdue_status,
    b.overdue_days,
    b.late_fee_amount,
    b.late_fee_charged,
    b.payment_status,
    b.overdue_detected_at,
    TIMESTAMPDIFF(HOUR, CONCAT(b.return_date, ' ', b.return_time), NOW()) as hours_overdue,
    u.fullname as renter_name,
    u.email as renter_email,
    u.phone as renter_contact,
    o.fullname as owner_name,
    o.email as owner_email,
    o.phone as owner_contact,
    CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
    COALESCE(c.image, m.image) as vehicle_image
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN users o ON b.owner_id = o.id
LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
WHERE b.status = 'approved'
AND CONCAT(b.return_date, ' ', b.return_time) < NOW()";

// Add owner filter if provided
if ($ownerId) {
    $sql .= " AND b.owner_id = " . intval($ownerId);
}

// Add severity filter
if ($severity != 'all') {
    $sql .= " AND b.overdue_status = '" . mysqli_real_escape_string($conn, $severity) . "'";
}

$sql .= " ORDER BY hours_overdue DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . mysqli_error($conn)
    ]);
    exit;
}

$overdueBookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $overdueBookings[] = [
        'booking_id' => $row['booking_id'],
        'user_id' => $row['user_id'],
        'owner_id' => $row['owner_id'],
        'renter_name' => $row['renter_name'],
        'renter_email' => $row['renter_email'],
        'renter_contact' => $row['renter_contact'],
        'owner_name' => $row['owner_name'],
        'owner_contact' => $row['owner_contact'],
        'vehicle_name' => $row['vehicle_name'],
        'vehicle_image' => $row['vehicle_image'],
        'return_date' => $row['return_date'],
        'return_time' => $row['return_time'],
        'overdue_status' => $row['overdue_status'],
        'overdue_days' => (int)$row['overdue_days'],
        'hours_overdue' => (int)$row['hours_overdue'],
        'late_fee_amount' => (float)$row['late_fee_amount'],
        'late_fee_charged' => (bool)$row['late_fee_charged'],
        'payment_status' => $row['payment_status'],
        'total_amount' => (float)$row['total_amount'],
        'total_due' => (float)$row['total_amount'] + (float)$row['late_fee_amount'],
        'detected_at' => $row['overdue_detected_at']
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($overdueBookings),
    'data' => $overdueBookings
]);

mysqli_close($conn);
?>
