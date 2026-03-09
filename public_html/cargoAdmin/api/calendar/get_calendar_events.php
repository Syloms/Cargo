<?php
/**
 * ============================================================================
 * ADMIN CALENDAR - Get Events
 * Fetches all events for the calendar (bookings, payments, verifications, etc.)
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
$event_type = $_GET['event_type'] ?? 'all'; // all, bookings, payments, verifications, etc.

// Calculate date range for the month
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

$events = [];

try {
    // 1. BOOKINGS (Pickups, Returns, Active)
    if ($event_type === 'all' || $event_type === 'bookings') {
        $booking_query = "
            SELECT 
                b.id,
                b.pickup_date,
                b.pickup_time,
                b.return_date,
                b.return_time,
                b.status,
                b.total_amount,
                COALESCE(u.fullname, 'Unknown User') as user_name,
                COALESCE(c.brand, m.brand, 'Unknown') as vehicle_brand,
                COALESCE(c.model, m.model, '') as vehicle_model,
                b.vehicle_type
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
            LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
            WHERE (
                (b.pickup_date BETWEEN ? AND ?)
                OR (b.return_date BETWEEN ? AND ?)
                OR (b.pickup_date <= ? AND b.return_date >= ?)
            )
            ORDER BY b.pickup_date, b.pickup_time
        ";
        
        $stmt = $conn->prepare($booking_query);
        if (!$stmt) {
            throw new Exception("Booking query preparation failed: " . $conn->error);
        }
        $stmt->bind_param('ssssss', $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        if (!$stmt->execute()) {
            throw new Exception("Booking query execution failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Pickup event
            $events[] = [
                'id' => 'booking_pickup_' . $row['id'],
                'type' => 'booking_pickup',
                'title' => 'Pickup: ' . $row['vehicle_brand'] . ' ' . $row['vehicle_model'],
                'description' => 'Customer: ' . $row['user_name'],
                'date' => $row['pickup_date'],
                'time' => $row['pickup_time'],
                'status' => $row['status'],
                'color' => $row['status'] === 'approved' ? '#28a745' : '#ffc107',
                'icon' => 'bi-box-arrow-up-right',
                'booking_id' => $row['id'],
                'amount' => $row['total_amount']
            ];
            
            // Return event
            $events[] = [
                'id' => 'booking_return_' . $row['id'],
                'type' => 'booking_return',
                'title' => 'Return: ' . $row['vehicle_brand'] . ' ' . $row['vehicle_model'],
                'description' => 'Customer: ' . $row['user_name'],
                'date' => $row['return_date'],
                'time' => $row['return_time'],
                'status' => $row['status'],
                'color' => $row['status'] === 'completed' ? '#6c757d' : '#17a2b8',
                'icon' => 'bi-box-arrow-in-left',
                'booking_id' => $row['id'],
                'amount' => $row['total_amount']
            ];
        }
    }
    
    // 2. PAYMENTS
    if ($event_type === 'all' || $event_type === 'payments') {
        $payment_query = "
            SELECT 
                p.id,
                p.booking_id,
                p.amount,
                p.payment_status,
                p.payment_method,
                DATE(p.created_at) as payment_date,
                TIME(p.created_at) as payment_time,
                COALESCE(u.fullname, 'Unknown User') as user_name
            FROM payments p
            LEFT JOIN bookings b ON p.booking_id = b.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
            ORDER BY p.created_at
        ";
        
        $stmt = $conn->prepare($payment_query);
        if (!$stmt) {
            throw new Exception("Payment query preparation failed: " . $conn->error);
        }
        $stmt->bind_param('ss', $start_date, $end_date);
        if (!$stmt->execute()) {
            throw new Exception("Payment query execution failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => 'payment_' . $row['id'],
                'type' => 'payment',
                'title' => 'Payment: ₱' . number_format($row['amount'], 2),
                'description' => 'Customer: ' . $row['user_name'] . ' - ' . $row['payment_method'],
                'date' => $row['payment_date'],
                'time' => $row['payment_time'],
                'status' => $row['payment_status'],
                'color' => $row['payment_status'] === 'verified' ? '#28a745' : '#ffc107',
                'icon' => 'bi-credit-card',
                'payment_id' => $row['id'],
                'amount' => $row['amount']
            ];
        }
    }
    
    // 3. USER VERIFICATIONS
    if ($event_type === 'all' || $event_type === 'verifications') {
        $verification_query = "
            SELECT 
                uv.id,
                uv.status,
                DATE(uv.created_at) as verification_date,
                TIME(uv.created_at) as verification_time,
                COALESCE(u.fullname, 'Unknown User') as user_name
            FROM user_verifications uv
            LEFT JOIN users u ON uv.user_id = u.id
            WHERE DATE(uv.created_at) BETWEEN ? AND ?
            ORDER BY uv.created_at
        ";
        
        $stmt = $conn->prepare($verification_query);
        if (!$stmt) {
            throw new Exception("Verification query preparation failed: " . $conn->error);
        }
        $stmt->bind_param('ss', $start_date, $end_date);
        if (!$stmt->execute()) {
            throw new Exception("Verification query execution failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => 'verification_' . $row['id'],
                'type' => 'verification',
                'title' => 'Verification Request',
                'description' => 'User: ' . $row['user_name'],
                'date' => $row['verification_date'],
                'time' => $row['verification_time'],
                'status' => $row['status'],
                'color' => $row['status'] === 'verified' ? '#28a745' : '#ffc107',
                'icon' => 'bi-shield-check',
                'verification_id' => $row['id']
            ];
        }
    }
    
    // 4. VEHICLE LISTINGS (Cars/Motorcycles)
    if ($event_type === 'all' || $event_type === 'vehicles') {
        $vehicle_query = "
            SELECT 
                c.id,
                c.brand,
                c.model,
                c.status,
                DATE(c.created_at) as listing_date,
                TIME(c.created_at) as listing_time,
                COALESCE(u.fullname, 'Unknown User') as owner_name,
                'car' as vehicle_type
            FROM cars c
            LEFT JOIN users u ON c.owner_id = u.id
            WHERE DATE(c.created_at) BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT 
                m.id,
                m.brand,
                m.model,
                m.status,
                DATE(m.created_at) as listing_date,
                TIME(m.created_at) as listing_time,
                COALESCE(u.fullname, 'Unknown User') as owner_name,
                'motorcycle' as vehicle_type
            FROM motorcycles m
            LEFT JOIN users u ON m.owner_id = u.id
            WHERE DATE(m.created_at) BETWEEN ? AND ?
            
            ORDER BY listing_date, listing_time
        ";
        
        $stmt = $conn->prepare($vehicle_query);
        if (!$stmt) {
            throw new Exception("Vehicle query preparation failed: " . $conn->error);
        }
        $stmt->bind_param('ssss', $start_date, $end_date, $start_date, $end_date);
        if (!$stmt->execute()) {
            throw new Exception("Vehicle query execution failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => 'vehicle_' . $row['vehicle_type'] . '_' . $row['id'],
                'type' => 'vehicle_listing',
                'title' => 'New Listing: ' . $row['brand'] . ' ' . $row['model'],
                'description' => 'Owner: ' . $row['owner_name'] . ' (' . ucfirst($row['vehicle_type']) . ')',
                'date' => $row['listing_date'],
                'time' => $row['listing_time'],
                'status' => $row['status'],
                'color' => $row['status'] === 'approved' ? '#28a745' : '#6c757d',
                'icon' => $row['vehicle_type'] === 'car' ? 'bi-car-front' : 'bi-bicycle',
                'vehicle_id' => $row['id'],
                'vehicle_type' => $row['vehicle_type']
            ];
        }
    }
    
    // 5. REPORTS
    if ($event_type === 'all' || $event_type === 'reports') {
        $report_query = "
            SELECT 
                r.id,
                r.report_type,
                r.status,
                DATE(r.created_at) as report_date,
                TIME(r.created_at) as report_time,
                COALESCE(u.fullname, 'Unknown User') as reporter_name
            FROM reports r
            LEFT JOIN users u ON r.reporter_id = u.id
            WHERE DATE(r.created_at) BETWEEN ? AND ?
            ORDER BY r.created_at
        ";
        
        $stmt = $conn->prepare($report_query);
        if (!$stmt) {
            throw new Exception("Report query preparation failed: " . $conn->error);
        }
        $stmt->bind_param('ss', $start_date, $end_date);
        if (!$stmt->execute()) {
            throw new Exception("Report query execution failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => 'report_' . $row['id'],
                'type' => 'report',
                'title' => 'Report: ' . ucwords(str_replace('_', ' ', $row['report_type'])),
                'description' => 'By: ' . $row['reporter_name'],
                'date' => $row['report_date'],
                'time' => $row['report_time'],
                'status' => $row['status'],
                'color' => '#dc3545',
                'icon' => 'bi-flag',
                'report_id' => $row['id']
            ];
        }
    }
    
    // 6. REFUNDS (only if table exists)
    if ($event_type === 'all' || $event_type === 'refunds') {
        $refunds_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'refunds'");
        if ($refunds_table_check && mysqli_num_rows($refunds_table_check) > 0) {
            $refund_query = "
                SELECT 
                    r.id,
                    r.booking_id,
                    r.refund_amount as amount,
                    r.status,
                    DATE(r.created_at) as refund_date,
                    TIME(r.created_at) as refund_time,
                    COALESCE(u.fullname, 'Unknown User') as user_name
                FROM refunds r
                LEFT JOIN bookings b ON r.booking_id = b.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE DATE(r.created_at) BETWEEN ? AND ?
                ORDER BY r.created_at
            ";
            
            $stmt = $conn->prepare($refund_query);
            if ($stmt) {
                $stmt->bind_param('ss', $start_date, $end_date);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
        
                    while ($row = $result->fetch_assoc()) {
                        $events[] = [
                            'id' => 'refund_' . $row['id'],
                            'type' => 'refund',
                            'title' => 'Refund: ₱' . number_format($row['amount'], 2),
                            'description' => 'Customer: ' . $row['user_name'],
                            'date' => $row['refund_date'],
                            'time' => $row['refund_time'],
                            'status' => $row['status'],
                            'color' => '#ff6b6b',
                            'icon' => 'bi-arrow-counterclockwise',
                            'refund_id' => $row['id'],
                            'amount' => $row['amount']
                        ];
                    }
                }
            }
        }
    }
    
    // Group events by date
    $calendar_data = [];
    foreach ($events as $event) {
        $date = $event['date'];
        if (!isset($calendar_data[$date])) {
            $calendar_data[$date] = [];
        }
        $calendar_data[$date][] = $event;
    }
    
    // Calculate statistics - PHP 7.4 compatible
    $stats = [
        'total_events' => count($events),
        'bookings' => count(array_filter($events, function($e) { return strpos($e['type'], 'booking') === 0; })),
        'payments' => count(array_filter($events, function($e) { return $e['type'] === 'payment'; })),
        'verifications' => count(array_filter($events, function($e) { return $e['type'] === 'verification'; })),
        'vehicles' => count(array_filter($events, function($e) { return $e['type'] === 'vehicle_listing'; })),
        'reports' => count(array_filter($events, function($e) { return $e['type'] === 'report'; })),
        'refunds' => count(array_filter($events, function($e) { return $e['type'] === 'refund'; }))
    ];
    
    echo json_encode([
        'success' => true,
        'calendar_data' => $calendar_data,
        'events' => $events,
        'stats' => $stats,
        'month' => $month,
        'year' => $year
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching calendar events: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
