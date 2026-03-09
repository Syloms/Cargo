<?php
/**
 * Get Late Fee Payments API
 * Retrieve late fee payments for admin review
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit();
}

$status = $_GET['status'] ?? 'pending'; // pending, verified, rejected, all
$bookingId = $_GET['booking_id'] ?? null;
$userId = $_GET['user_id'] ?? null;

try {
    $query = "SELECT lfp.*, 
              b.owner_id, b.user_id as renter_id, b.status as booking_status,
              u.fullname as renter_name, u.email as renter_email,
              CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
              admin.fullname as verified_by_name
              FROM late_fee_payments lfp
              JOIN bookings b ON lfp.booking_id = b.id
              JOIN users u ON lfp.user_id = u.id
              LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
              LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
              LEFT JOIN users admin ON lfp.verified_by = admin.id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($status !== 'all') {
        $query .= " AND lfp.payment_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($bookingId) {
        $query .= " AND lfp.booking_id = ?";
        $params[] = $bookingId;
        $types .= "i";
    }
    
    if ($userId) {
        $query .= " AND lfp.user_id = ?";
        $params[] = $userId;
        $types .= "i";
    }
    
    $query .= " ORDER BY lfp.created_at DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $payments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $payments,
        'count' => count($payments)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
