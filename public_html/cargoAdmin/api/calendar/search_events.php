<?php
/**
 * ============================================================================
 * CALENDAR SEARCH - Search Events
 * Search for events across all time periods
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

$search_query = $_GET['q'] ?? '';
$event_type = $_GET['event_type'] ?? 'all';
$limit = $_GET['limit'] ?? 50;

if (empty($search_query) || strlen($search_query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search query too short']);
    exit;
}

$results = [];

try {
    // Search in bookings
    if ($event_type === 'all' || $event_type === 'bookings') {
        $booking_query = "
            SELECT 
                b.id,
                'booking' as type,
                CONCAT('Booking #', b.id, ' - ', c.brand, ' ', c.model) as title,
                u.fullname as description,
                b.pickup_date as date,
                b.pickup_time as time,
                b.status,
                b.total_amount as amount,
                'bi-box-arrow-up-right' as icon,
                '#667eea' as color
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
            WHERE (
                u.fullname LIKE ?
                OR CONCAT(c.brand, ' ', c.model) LIKE ?
                OR b.id LIKE ?
                OR b.status LIKE ?
            )
            ORDER BY b.pickup_date DESC
            LIMIT ?
        ";
        
        $search_param = "%$search_query%";
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param('ssssi', $search_param, $search_param, $search_param, $search_param, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    
    // Search in payments
    if ($event_type === 'all' || $event_type === 'payments') {
        $payment_query = "
            SELECT 
                p.id,
                'payment' as type,
                CONCAT('Payment ₱', FORMAT(p.amount, 2)) as title,
                CONCAT(u.fullname, ' - ', p.payment_method) as description,
                DATE(p.created_at) as date,
                TIME(p.created_at) as time,
                p.payment_status as status,
                p.amount,
                'bi-credit-card' as icon,
                '#28a745' as color
            FROM payments p
            LEFT JOIN bookings b ON p.booking_id = b.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE (
                u.fullname LIKE ?
                OR p.payment_reference LIKE ?
                OR p.payment_method LIKE ?
            )
            ORDER BY p.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($payment_query);
        $stmt->bind_param('sssi', $search_param, $search_param, $search_param, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    
    // Search in verifications
    if ($event_type === 'all' || $event_type === 'verifications') {
        $verification_query = "
            SELECT 
                uv.id,
                'verification' as type,
                'User Verification Request' as title,
                u.fullname as description,
                DATE(uv.created_at) as date,
                TIME(uv.created_at) as time,
                uv.status,
                NULL as amount,
                'bi-shield-check' as icon,
                '#17a2b8' as color
            FROM user_verifications uv
            LEFT JOIN users u ON uv.user_id = u.id
            WHERE u.fullname LIKE ?
            ORDER BY uv.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($verification_query);
        $stmt->bind_param('si', $search_param, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    
    // Search in vehicles
    if ($event_type === 'all' || $event_type === 'vehicles') {
        $vehicle_query = "
            SELECT 
                c.id,
                'vehicle' as type,
                CONCAT(c.brand, ' ', c.model) as title,
                CONCAT('Owner: ', u.fullname) as description,
                DATE(c.created_at) as date,
                TIME(c.created_at) as time,
                c.status,
                NULL as amount,
                'bi-car-front' as icon,
                '#ffc107' as color
            FROM cars c
            LEFT JOIN users u ON c.owner_id = u.id
            WHERE (
                CONCAT(c.brand, ' ', c.model) LIKE ?
                OR u.fullname LIKE ?
                OR c.plate_number LIKE ?
            )
            
            UNION ALL
            
            SELECT 
                m.id,
                'vehicle' as type,
                CONCAT(m.brand, ' ', m.model) as title,
                CONCAT('Owner: ', u.fullname) as description,
                DATE(m.created_at) as date,
                TIME(m.created_at) as time,
                m.status,
                NULL as amount,
                'bi-bicycle' as icon,
                '#ffc107' as color
            FROM motorcycles m
            LEFT JOIN users u ON m.owner_id = u.id
            WHERE (
                CONCAT(m.brand, ' ', m.model) LIKE ?
                OR u.fullname LIKE ?
                OR m.plate_number LIKE ?
            )
            
            ORDER BY date DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($vehicle_query);
        $stmt->bind_param('ssssssi', $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    
    // Sort results by date
    usort($results, function($a, $b) {
        return strcmp($b['date'] . ' ' . $b['time'], $a['date'] . ' ' . $a['time']);
    });
    
    echo json_encode([
        'success' => true,
        'results' => array_slice($results, 0, $limit),
        'total' => count($results),
        'query' => $search_query
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Search error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
