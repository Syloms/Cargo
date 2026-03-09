<?php
/**
 * Get Owner Analytics
 * Provides comprehensive analytics data for vehicle owners
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
    $period = isset($_POST['period']) ? $_POST['period'] : 'month'; // month, quarter, year
    
    if ($ownerId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid owner ID'
        ]);
        exit;
    }

    // Determine date range based on period
    $dateFilter = "";
    switch($period) {
        case 'week':
            $dateFilter = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $dateFilter = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'quarter':
            $dateFilter = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case 'year':
            $dateFilter = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            break;
        default:
            $dateFilter = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }

    // Total earnings — include escrow-held and payout-completed (matches dashboard logic)
    $earningsQuery = "
        SELECT
            COALESCE(SUM(
                CASE
                    WHEN payout_status = 'completed' THEN owner_payout
                    WHEN escrow_status IN ('held', 'released_to_owner') THEN owner_payout
                    WHEN status = 'completed' AND payment_verified_at IS NOT NULL THEN owner_payout
                    ELSE 0
                END
            ), 0) as total_earnings,
            SUM(CASE
                WHEN payout_status = 'completed'
                    OR escrow_status IN ('held', 'released_to_owner')
                    OR (status = 'completed' AND payment_verified_at IS NOT NULL)
                THEN 1 ELSE 0
            END) as completed_bookings
        FROM bookings
        WHERE owner_id = ? $dateFilter
    ";
    $stmt = $conn->prepare($earningsQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $earnings = $stmt->get_result()->fetch_assoc();

    // Booking statistics
    $bookingStatsQuery = "
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM bookings 
        WHERE owner_id = ? $dateFilter
    ";
    $stmt = $conn->prepare($bookingStatsQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $bookingStats = $stmt->get_result()->fetch_assoc();

    // Revenue trend (last 7 periods) — include escrow-held bookings
    $trendQuery = "
        SELECT
            DATE(created_at) as date,
            COALESCE(SUM(
                CASE
                    WHEN payout_status = 'completed' THEN owner_payout
                    WHEN escrow_status IN ('held', 'released_to_owner') THEN owner_payout
                    WHEN status = 'completed' AND payment_verified_at IS NOT NULL THEN owner_payout
                    ELSE 0
                END
            ), 0) as daily_earnings,
            COUNT(*) as daily_bookings
        FROM bookings
        WHERE owner_id = ? $dateFilter
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 7
    ";
    $stmt = $conn->prepare($trendQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $trendResult = $stmt->get_result();
    $revenueTrend = [];
    while ($row = $trendResult->fetch_assoc()) {
        $revenueTrend[] = $row;
    }

    // Vehicle performance
    $vehiclePerformanceQuery = "
        SELECT 
            COALESCE(c.brand, m.brand) as brand,
            COALESCE(c.model, m.model) as model,
            b.vehicle_type,
            COUNT(*) as bookings,
            COALESCE(SUM(b.owner_payout), 0) as earnings,
            AVG(DATEDIFF(b.return_date, b.pickup_date)) as avg_rental_days
        FROM bookings b
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.owner_id = ? $dateFilter
        AND (
            b.payout_status = 'completed'
            OR b.escrow_status IN ('held', 'released_to_owner')
            OR (b.status = 'completed' AND b.payment_verified_at IS NOT NULL)
        )
        GROUP BY b.car_id, b.vehicle_type
        ORDER BY earnings DESC
    ";
    $stmt = $conn->prepare($vehiclePerformanceQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $vehicleResult = $stmt->get_result();
    $vehiclePerformance = [];
    while ($row = $vehicleResult->fetch_assoc()) {
        $vehiclePerformance[] = $row;
    }

    // Average rating
    $ratingQuery = "
        SELECT 
            AVG(rating) as average_rating,
            COUNT(*) as total_reviews
        FROM reviews
        WHERE owner_id = ?
    ";
    $stmt = $conn->prepare($ratingQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $ratingData = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'period' => $period,
        'earnings' => [
            'total' => floatval($earnings['total_earnings']),
            'completed_bookings' => intval($earnings['completed_bookings'])
        ],
        'booking_stats' => [
            'total' => intval($bookingStats['total_bookings']),
            'pending' => intval($bookingStats['pending']),
            'confirmed' => intval($bookingStats['confirmed']),
            'ongoing' => intval($bookingStats['ongoing']),
            'completed' => intval($bookingStats['completed']),
            'cancelled' => intval($bookingStats['cancelled'])
        ],
        'revenue_trend' => array_reverse($revenueTrend),
        'vehicle_performance' => $vehiclePerformance,
        'rating' => [
            'average' => floatval($ratingData['average_rating']),
            'total_reviews' => intval($ratingData['total_reviews'])
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
