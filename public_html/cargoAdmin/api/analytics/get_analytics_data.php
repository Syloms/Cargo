<?php
/**
 * ============================================================================
 * ANALYTICS DATA - Enhanced Dashboard Analytics
 * Provides comprehensive analytics for admin dashboard
 * ============================================================================
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../include/db.php';

$type = $_GET['type'] ?? 'overview';
$owner_id = isset($_GET['owner_id']) ? (intval($_GET['owner_id']) ?: null) : null;

$response = [];

try {
    switch ($type) {
        case 'overview':
            $response = getOverviewStats($conn, $owner_id);
            break;
        case 'booking_trends':
            $response = getBookingTrends($conn, $owner_id);
            break;
        case 'revenue_breakdown':
            $response = getRevenueBreakdown($conn, $owner_id);
            break;
        case 'popular_vehicles':
            $response = getPopularVehicles($conn, $owner_id);
            break;
        case 'peak_hours':
            $response = getPeakBookingHours($conn, $owner_id);
            break;
        default:
            $response = ['success' => false, 'message' => 'Invalid type'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
$conn->close();

// ============================================================================
// FUNCTIONS
// ============================================================================

function getOverviewStats($conn, $owner_id = null) {
    $where = $owner_id ? "WHERE owner_id = $owner_id" : "";
    $whereAnd = $owner_id ? "WHERE owner_id = $owner_id AND" : "WHERE";
    
    // Total bookings
    $totalBookings = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT COUNT(*) as count FROM bookings $where"))['count'];
    
    // Completed bookings
    $completedBookings = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT COUNT(*) as count FROM bookings $whereAnd status = 'completed'"))['count'];
    
    // Total revenue (owner's earnings from paid/escrow bookings - matches dashboard logic)
    $revenueField = $owner_id ? "owner_payout" : "total_amount";
    $totalRevenue = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT COALESCE(SUM($revenueField), 0) as revenue 
         FROM bookings 
         $whereAnd (
             escrow_status IN ('held', 'released_to_owner')
             OR payout_status = 'completed'
             OR (status = 'completed' AND payment_verified_at IS NOT NULL)
         )"))['revenue'];
    
    // Active vehicles
    $vehicleWhere = $owner_id ? "WHERE owner_id = $owner_id" : "";
    $vehicleWhereAnd = $owner_id ? "WHERE owner_id = $owner_id AND" : "WHERE";
    $activeCars = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT COUNT(*) as count FROM cars $vehicleWhereAnd status = 'approved'"))['count'];
    $activeMotorcycles = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT COUNT(*) as count FROM motorcycles $vehicleWhereAnd status = 'approved'"))['count'];
    
    // Average rating
    $avgRating = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT AVG(rating) as avg_rating FROM reviews WHERE owner_id = " . ($owner_id ?? 0)))['avg_rating'] ?? 0.0;
    
    return [
        'success' => true,
        'total_bookings' => (int)$totalBookings,
        'completed_bookings' => (int)$completedBookings,
        'total_revenue' => (float)$totalRevenue,
        'active_vehicles' => (int)($activeCars + $activeMotorcycles),
        'active_cars' => (int)$activeCars,
        'active_motorcycles' => (int)$activeMotorcycles,
        'average_rating' => round((float)$avgRating, 1),
        'completion_rate' => $totalBookings > 0 ? round(($completedBookings / $totalBookings) * 100, 1) : 0
    ];
}

function getBookingTrends($conn, $owner_id = null) {
    $where = $owner_id ? "WHERE owner_id = $owner_id AND" : "WHERE";
    
    // Last 6 months trend
    // Use owner_payout for owner-specific revenue, total_amount for admin view
    $revenueField = $owner_id ? "owner_payout" : "total_amount";
    
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                COALESCE(SUM(CASE 
                    WHEN (
                        escrow_status IN ('held', 'released_to_owner')
                        OR payout_status = 'completed'
                        OR (status = 'completed' AND payment_verified_at IS NOT NULL)
                    ) THEN $revenueField 
                    ELSE 0 
                END), 0) as revenue
              FROM bookings 
              $where created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month ASC";
    
    $result = mysqli_query($conn, $query);
    $trends = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $trends[] = [
            'month' => $row['month'],
            'month_name' => date('M Y', strtotime($row['month'] . '-01')),
            'total' => (int)$row['count'],
            'completed' => (int)$row['completed'],
            'cancelled' => (int)$row['cancelled'],
            'revenue' => (float)$row['revenue']
        ];
    }
    
    return ['success' => true, 'trends' => $trends];
}

function getRevenueBreakdown($conn, $owner_id = null) {
    $where = $owner_id ? "WHERE owner_id = $owner_id AND" : "WHERE";
    
    // Use owner_payout for owner-specific revenue, total_amount for admin view
    $revenueField = $owner_id ? "owner_payout" : "total_amount";
    
    // Revenue by vehicle type - Include bookings with escrow, completed, or payout
    // (matches dashboard revenue counting logic)
    $query = "SELECT 
                vehicle_type,
                COUNT(*) as bookings,
                COALESCE(SUM($revenueField), 0) as revenue
              FROM bookings 
              $where (
                  escrow_status IN ('held', 'released_to_owner')
                  OR payout_status = 'completed'
                  OR (status = 'completed' AND payment_verified_at IS NOT NULL)
              )
              GROUP BY vehicle_type";
    
    $result = mysqli_query($conn, $query);
    $breakdown = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $breakdown[] = [
            'type' => ucfirst($row['vehicle_type']),
            'bookings' => (int)$row['bookings'],
            'revenue' => (float)$row['revenue']
        ];
    }
    
    // Payment status breakdown - Include all paid bookings with revenue
    $paymentQuery = "SELECT 
                        payment_status,
                        COUNT(*) as count,
                        COALESCE(SUM($revenueField), 0) as amount
                     FROM bookings
                     $where (
                         escrow_status IN ('held', 'released_to_owner')
                         OR payout_status = 'completed'
                         OR (status = 'completed' AND payment_verified_at IS NOT NULL)
                     )
                     GROUP BY payment_status";
    
    $paymentResult = mysqli_query($conn, $paymentQuery);
    $paymentBreakdown = [];
    
    while ($row = mysqli_fetch_assoc($paymentResult)) {
        $paymentBreakdown[] = [
            'status' => $row['payment_status'],
            'count' => (int)$row['count'],
            'amount' => (float)$row['amount']
        ];
    }
    
    return [
        'success' => true,
        'by_vehicle_type' => $breakdown,
        'by_payment_status' => $paymentBreakdown
    ];
}

function getPopularVehicles($conn, $owner_id = null) {
    $ownerWhere = $owner_id ? "AND b.owner_id = $owner_id" : "";
    
    // Use owner_payout for owner-specific revenue, total_amount for admin view
    $revenueField = $owner_id ? "b.owner_payout" : "b.total_amount";
    
    // Top 10 most booked cars
    $carsQuery = "SELECT 
                    c.id,
                    c.brand,
                    c.model,
                    c.plate_number,
                    c.image,
                    COUNT(b.id) as booking_count,
                    COALESCE(AVG(r.rating), 0.0) as avg_rating,
                    COALESCE(SUM($revenueField), 0) as total_revenue
                  FROM cars c
                  LEFT JOIN bookings b ON c.id = b.car_id AND b.vehicle_type = 'car' AND b.status = 'completed' $ownerWhere
                  LEFT JOIN reviews r ON c.id = r.car_id
                  WHERE c.status = 'approved'
                  GROUP BY c.id
                  ORDER BY booking_count DESC, total_revenue DESC
                  LIMIT 10";
    
    $result = mysqli_query($conn, $carsQuery);
    $cars = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $cars[] = [
            'id' => (int)$row['id'],
            'name' => $row['brand'] . ' ' . $row['model'],
            'plate' => $row['plate_number'],
            'image' => $row['image'],
            'bookings' => (int)$row['booking_count'],
            'rating' => round((float)$row['avg_rating'], 1),
            'revenue' => (float)$row['total_revenue']
        ];
    }
    
    // Top 10 motorcycles
    $motorcyclesQuery = "SELECT 
                           m.id,
                           m.brand,
                           m.model,
                           m.plate_number,
                           m.image,
                           COUNT(b.id) as booking_count,
                           COALESCE(AVG(r.rating), 0.0) as avg_rating,
                           COALESCE(SUM($revenueField), 0) as total_revenue
                         FROM motorcycles m
                         LEFT JOIN bookings b ON m.id = b.car_id AND b.vehicle_type = 'motorcycle' AND b.status = 'completed' $ownerWhere
                         LEFT JOIN reviews r ON m.id = r.car_id
                         WHERE m.status = 'approved'
                         GROUP BY m.id
                         ORDER BY booking_count DESC, total_revenue DESC
                         LIMIT 10";
    
    $result = mysqli_query($conn, $motorcyclesQuery);
    $motorcycles = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $motorcycles[] = [
            'id' => (int)$row['id'],
            'name' => $row['brand'] . ' ' . $row['model'],
            'plate' => $row['plate_number'],
            'image' => $row['image'],
            'bookings' => (int)$row['booking_count'],
            'rating' => round((float)$row['avg_rating'], 1),
            'revenue' => (float)$row['total_revenue']
        ];
    }
    
    return [
        'success' => true,
        'cars' => $cars,
        'motorcycles' => $motorcycles
    ];
}

function getPeakBookingHours($conn, $owner_id = null) {
    $where = $owner_id ? "WHERE owner_id = $owner_id" : "";
    
    // Bookings by hour of day
    $query = "SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as count
              FROM bookings
              $where
              GROUP BY HOUR(created_at)
              ORDER BY hour ASC";
    
    $result = mysqli_query($conn, $query);
    $hourly = array_fill(0, 24, 0);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $hourly[(int)$row['hour']] = (int)$row['count'];
    }
    
    // Bookings by day of week
    $dayQuery = "SELECT 
                   DAYOFWEEK(created_at) as day,
                   COUNT(*) as count
                 FROM bookings
                 $where
                 GROUP BY DAYOFWEEK(created_at)
                 ORDER BY day ASC";
    
    $dayResult = mysqli_query($conn, $dayQuery);
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $daily = array_fill(0, 7, 0);
    
    while ($row = mysqli_fetch_assoc($dayResult)) {
        $daily[(int)$row['day'] - 1] = (int)$row['count'];
    }
    
    return [
        'success' => true,
        'hourly' => $hourly,
        'daily' => array_map(function($count, $index) use ($days) {
            return ['day' => $days[$index], 'count' => $count];
        }, $daily, array_keys($daily))
    ];
}
?>
