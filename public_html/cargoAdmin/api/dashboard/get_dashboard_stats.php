<?php
/**
 * Get Dashboard Statistics
 * Provides quick stats for owner dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../include/db.php';

try {
    $ownerId = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : (isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0);
    
    if ($ownerId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid owner ID'
        ]);
        exit;
    }

    // Active bookings
    $activeQuery = "
        SELECT COUNT(*) as count 
        FROM bookings 
        WHERE owner_id = ? AND status IN ('confirmed', 'ongoing')
    ";
    $stmt = $conn->prepare($activeQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $activeBookings = $stmt->get_result()->fetch_assoc()['count'];

    // Pending requests
    $pendingQuery = "
        SELECT COUNT(*) as count 
        FROM bookings 
        WHERE owner_id = ? AND status = 'pending'
    ";
    $stmt = $conn->prepare($pendingQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $pendingRequests = $stmt->get_result()->fetch_assoc()['count'];

    // Total vehicles
    $vehiclesQuery = "
        SELECT 
            (SELECT COUNT(*) FROM cars WHERE owner_id = ?) +
            (SELECT COUNT(*) FROM motorcycles WHERE owner_id = ?) as total
    ";
    $stmt = $conn->prepare($vehiclesQuery);
    $stmt->bind_param("ii", $ownerId, $ownerId);
    $stmt->execute();
    $totalVehicles = $stmt->get_result()->fetch_assoc()['total'];

    // This month earnings
    $earningsQuery = "
        SELECT COALESCE(SUM(owner_payout), 0) as total
        FROM bookings
        WHERE owner_id = ? 
        AND status = 'completed'
        AND MONTH(return_date) = MONTH(NOW())
        AND YEAR(return_date) = YEAR(NOW())
    ";
    $stmt = $conn->prepare($earningsQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $monthlyEarnings = $stmt->get_result()->fetch_assoc()['total'];

    // Pending payouts
    $payoutsQuery = "
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(owner_payout), 0) as amount
        FROM bookings
        WHERE owner_id = ?
        AND escrow_status = 'released_to_owner'
        AND payout_status = 'pending'
    ";
    $stmt = $conn->prepare($payoutsQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $payoutData = $stmt->get_result()->fetch_assoc();

    // Unread notifications
    $notificationsQuery = "
        SELECT COUNT(*) as count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ";
    $stmt = $conn->prepare($notificationsQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $unreadNotifications = $stmt->get_result()->fetch_assoc()['count'];

    // Average rating
    $ratingQuery = "
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
        FROM reviews
        WHERE owner_id = ?
    ";
    $stmt = $conn->prepare($ratingQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $ratingData = $stmt->get_result()->fetch_assoc();

    // Recent activity
    $activityQuery = "
        SELECT 
            b.id,
            b.status,
            b.created_at,
            b.pickup_date,
            b.return_date,
            b.total_amount,
            COALESCE(c.brand, m.brand) as vehicle_brand,
            COALESCE(c.model, m.model) as vehicle_model,
            u.fullname as renter_name
        FROM bookings b
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.owner_id = ?
        ORDER BY b.created_at DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($activityQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $activityResult = $stmt->get_result();
    $recentActivity = [];
    while ($row = $activityResult->fetch_assoc()) {
        $recentActivity[] = $row;
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'active_bookings' => intval($activeBookings),
            'pending_requests' => intval($pendingRequests),
            'total_vehicles' => intval($totalVehicles),
            'monthly_earnings' => floatval($monthlyEarnings),
            'pending_payouts' => [
                'count' => intval($payoutData['count']),
                'amount' => floatval($payoutData['amount'])
            ],
            'unread_notifications' => intval($unreadNotifications),
            'average_rating' => floatval($ratingData['avg_rating']),
            'total_reviews' => intval($ratingData['total_reviews'])
        ],
        'recent_activity' => $recentActivity
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
