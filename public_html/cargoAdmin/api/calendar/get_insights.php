<?php
/**
 * ============================================================================
 * CALENDAR INSIGHTS - Analytics & Statistics
 * Provides quick insights and analytics for calendar events
 * ============================================================================
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../include/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

try {
    $insights = [];
    
    // 1. Busiest Day
    $busiest_query = "
        SELECT DATE(pickup_date) as day, COUNT(*) as count
        FROM bookings
        WHERE pickup_date BETWEEN ? AND ?
        GROUP BY DATE(pickup_date)
        ORDER BY count DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($busiest_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $busiest = $result->fetch_assoc();
    
    $insights['busiest_day'] = [
        'date' => $busiest['day'] ?? 'N/A',
        'count' => $busiest['count'] ?? 0,
        'formatted' => $busiest['day'] ? date('l, F j', strtotime($busiest['day'])) : 'No data'
    ];
    
    // 2. Total Revenue
    $revenue_query = "
        SELECT SUM(amount) as total_revenue
        FROM payments
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND payment_status = 'verified'
    ";
    $stmt = $conn->prepare($revenue_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $revenue = $result->fetch_assoc();
    
    $insights['total_revenue'] = [
        'amount' => $revenue['total_revenue'] ?? 0,
        'formatted' => '₱' . number_format($revenue['total_revenue'] ?? 0, 2)
    ];
    
    // 3. Booking Status Distribution
    $status_query = "
        SELECT status, COUNT(*) as count
        FROM bookings
        WHERE pickup_date BETWEEN ? AND ?
        GROUP BY status
    ";
    $stmt = $conn->prepare($status_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $status_distribution = [];
    while ($row = $result->fetch_assoc()) {
        $status_distribution[$row['status']] = $row['count'];
    }
    
    $insights['booking_status'] = $status_distribution;
    
    // 4. Most Active Customer
    $customer_query = "
        SELECT 
            u.id,
            u.fullname as name,
            COUNT(b.id) as booking_count
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.pickup_date BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY booking_count DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($customer_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    
    $insights['top_customer'] = [
        'name' => $customer['name'] ?? 'N/A',
        'bookings' => $customer['booking_count'] ?? 0
    ];
    
    // 5. Most Popular Vehicle
    $vehicle_query = "
        SELECT 
            c.id,
            CONCAT(c.brand, ' ', c.model) as vehicle,
            COUNT(b.id) as rental_count
        FROM bookings b
        JOIN cars c ON b.car_id = c.id
        WHERE b.vehicle_type = 'car'
        AND b.pickup_date BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY rental_count DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($vehicle_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicle = $result->fetch_assoc();
    
    $insights['popular_vehicle'] = [
        'name' => $vehicle['vehicle'] ?? 'N/A',
        'rentals' => $vehicle['rental_count'] ?? 0
    ];
    
    // 6. Average Daily Events
    $days_in_month = date('t', strtotime($start_date));
    $total_events_query = "
        SELECT 
            (SELECT COUNT(*) FROM bookings WHERE pickup_date BETWEEN ? AND ?) +
            (SELECT COUNT(*) FROM payments WHERE DATE(created_at) BETWEEN ? AND ?) +
            (SELECT COUNT(*) FROM user_verifications WHERE DATE(created_at) BETWEEN ? AND ?) +
            (SELECT COUNT(*) FROM reports WHERE DATE(created_at) BETWEEN ? AND ?)
            as total
    ";
    $stmt = $conn->prepare($total_events_query);
    $stmt->bind_param('ssssssss', $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'];
    
    $insights['avg_daily_events'] = [
        'count' => round($total / $days_in_month, 1),
        'total' => $total
    ];
    
    // 7. Pending Items Count
    $pending_query = "
        SELECT 
            (SELECT COUNT(*) FROM bookings WHERE status = 'pending') as pending_bookings,
            (SELECT COUNT(*) FROM user_verifications WHERE status = 'pending') as pending_verifications,
            (SELECT COUNT(*) FROM refunds WHERE status = 'pending') as pending_refunds,
            (SELECT COUNT(*) FROM reports WHERE status != 'resolved') as pending_reports
    ";
    $result = mysqli_query($conn, $pending_query);
    $pending = mysqli_fetch_assoc($result);
    
    $insights['pending_items'] = [
        'bookings' => $pending['pending_bookings'] ?? 0,
        'verifications' => $pending['pending_verifications'] ?? 0,
        'refunds' => $pending['pending_refunds'] ?? 0,
        'reports' => $pending['pending_reports'] ?? 0,
        'total' => ($pending['pending_bookings'] ?? 0) + ($pending['pending_verifications'] ?? 0) + 
                   ($pending['pending_refunds'] ?? 0) + ($pending['pending_reports'] ?? 0)
    ];
    
    // 8. Week-by-week comparison
    $weekly_query = "
        SELECT 
            WEEK(pickup_date, 1) as week_num,
            COUNT(*) as count
        FROM bookings
        WHERE pickup_date BETWEEN ? AND ?
        GROUP BY WEEK(pickup_date, 1)
        ORDER BY week_num
    ";
    $stmt = $conn->prepare($weekly_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $weekly_data = [];
    while ($row = $result->fetch_assoc()) {
        $weekly_data[] = [
            'week' => $row['week_num'],
            'count' => $row['count']
        ];
    }
    
    $insights['weekly_trend'] = $weekly_data;
    
    // 9. Payment method distribution
    $payment_method_query = "
        SELECT 
            payment_method,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM payments
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY count DESC
    ";
    $stmt = $conn->prepare($payment_method_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payment_methods = [];
    while ($row = $result->fetch_assoc()) {
        $payment_methods[] = [
            'method' => $row['payment_method'],
            'count' => $row['count'],
            'amount' => $row['total_amount']
        ];
    }
    
    $insights['payment_methods'] = $payment_methods;
    
    // 10. Comparison with previous month
    $prev_month = date('Y-m', strtotime("$start_date -1 month"));
    $prev_start = "$prev_month-01";
    $prev_end = date('Y-m-t', strtotime($prev_start));
    
    $prev_bookings_query = "SELECT COUNT(*) as count FROM bookings WHERE pickup_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($prev_bookings_query);
    $stmt->bind_param('ss', $prev_start, $prev_end);
    $stmt->execute();
    $prev_bookings = $stmt->get_result()->fetch_assoc()['count'];
    
    $curr_bookings_query = "SELECT COUNT(*) as count FROM bookings WHERE pickup_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($curr_bookings_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $curr_bookings = $stmt->get_result()->fetch_assoc()['count'];
    
    $change = $prev_bookings > 0 ? (($curr_bookings - $prev_bookings) / $prev_bookings) * 100 : 0;
    
    $insights['month_comparison'] = [
        'current' => $curr_bookings,
        'previous' => $prev_bookings,
        'change_percent' => round($change, 1),
        'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable')
    ];
    
    echo json_encode([
        'success' => true,
        'insights' => $insights,
        'month' => $month,
        'year' => $year
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating insights: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
