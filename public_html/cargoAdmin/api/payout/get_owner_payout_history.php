<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../include/db.php';

if (!isset($_GET['owner_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Owner ID is required'
    ]);
    exit;
}

$ownerId = intval($_GET['owner_id']);

try {
    $query = "
        SELECT 
            p.id as payout_id,
            p.booking_id,
            p.amount,
            p.platform_fee,
            p.net_amount,
            p.payout_method,
            p.payout_account,
            p.status,
            p.scheduled_at,
            p.processed_at,
            p.completion_reference,
            p.transfer_proof,
            p.created_at,
            b.car_id,
            b.pickup_date,
            b.return_date,
            CONCAT(c.brand, ' ', c.model, ' ', c.car_year) as car_full_name,
            c.image as car_image,
            CONCAT(u.fullname) as renter_name
        FROM payouts p
        INNER JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN cars c ON b.car_id = c.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE p.owner_id = ?
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payouts = [];
    while ($row = $result->fetch_assoc()) {
        $payouts[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'payouts' => $payouts,
        'total' => count($payouts)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>