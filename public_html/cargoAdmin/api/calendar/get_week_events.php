<?php
/**
 * ============================================================================
 * ADMIN CALENDAR - Get Week Events
 * Fetches events for a specific week view
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

$start_date = $_GET['start'] ?? date('Y-m-d');
$end_date = $_GET['end'] ?? date('Y-m-d', strtotime('+6 days'));

$events = [];

try {
    // 1. BOOKINGS (Pickups and Returns)
    $booking_query = "
        SELECT 
            b.id,
            b.pickup_date,
            b.pickup_time,
            b.return_date,
            b.return_time,
            b.status,
            b.total_amount,
            b.vehicle_type,
            COALESCE(u.fullname, 'Unknown User') as user_name,
            COALESCE(c.brand, m.brand, 'Unknown') as vehicle_brand,
            COALESCE(c.model, m.model, '') as vehicle_model,
            c.image as car_image,
            m.image as motorcycle_image
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
        WHERE (
            (b.pickup_date BETWEEN ? AND ?)
            OR (b.return_date BETWEEN ? AND ?)
            OR (b.pickup_date <= ? AND b.return_date >= ?)
        )
        AND b.status NOT IN ('cancelled', 'rejected')
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
            'id' => (int)$row['id'] * 2 - 1, // Unique ID for pickup
            'booking_id' => (int)$row['id'],
            'type' => 'booking',
            'event_type' => 'pickup',
            'title' => '🚗 Pickup: ' . $row['vehicle_brand'] . ' ' . $row['vehicle_model'],
            'description' => 'Renter: ' . $row['user_name'],
            'date' => $row['pickup_date'],
            'time' => date('H:i', strtotime($row['pickup_time'])),
            'status' => $row['status'],
            'amount' => $row['total_amount'],
            'vehicle_type' => $row['vehicle_type'],
            'image' => $row['vehicle_type'] === 'car' ? $row['car_image'] : $row['motorcycle_image'],
            'participants' => [
                ['name' => $row['user_name'], 'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($row['user_name']) . '&background=6366f1&color=fff']
            ]
        ];
        
        // Return event
        $events[] = [
            'id' => (int)$row['id'] * 2, // Unique ID for return
            'booking_id' => (int)$row['id'],
            'type' => 'booking',
            'event_type' => 'return',
            'title' => '🔙 Return: ' . $row['vehicle_brand'] . ' ' . $row['vehicle_model'],
            'description' => 'Renter: ' . $row['user_name'],
            'date' => $row['return_date'],
            'time' => date('H:i', strtotime($row['return_time'])),
            'status' => $row['status'],
            'amount' => $row['total_amount'],
            'vehicle_type' => $row['vehicle_type'],
            'image' => $row['vehicle_type'] === 'car' ? $row['car_image'] : $row['motorcycle_image'],
            'participants' => [
                ['name' => $row['user_name'], 'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($row['user_name']) . '&background=6366f1&color=fff']
            ]
        ];
    }
    
    // 2. PAYMENTS
    $payment_query = "
        SELECT 
            p.id,
            p.booking_id,
            p.amount,
            p.payment_status,
            p.payment_method,
            DATE(p.created_at) as payment_date,
            TIME_FORMAT(p.created_at, '%H:%i') as payment_time,
            COALESCE(u.fullname, 'Unknown User') as user_name,
            COALESCE(c.brand, m.brand, 'Vehicle') as vehicle_brand,
            COALESCE(c.model, m.model, '') as vehicle_model
        FROM payments p
        LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
        WHERE DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.created_at
    ";
    
    $stmt = $conn->prepare($payment_query);
    if ($stmt) {
        $stmt->bind_param('ss', $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'id' => 10000 + (int)$row['id'], // Offset to avoid ID conflicts
                    'payment_id' => (int)$row['id'],
                    'type' => 'payment',
                    'title' => '💳 Payment: ₱' . number_format($row['amount'], 2),
                    'description' => $row['user_name'] . ' - ' . $row['vehicle_brand'] . ' ' . $row['vehicle_model'],
                    'date' => $row['payment_date'],
                    'time' => $row['payment_time'],
                    'status' => $row['payment_status'],
                    'amount' => $row['amount'],
                    'payment_method' => $row['payment_method'],
                    'participants' => [
                        ['name' => $row['user_name'], 'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($row['user_name']) . '&background=3b82f6&color=fff']
                    ]
                ];
            }
        }
    }
    
    // 3. VERIFICATIONS
    $verification_query = "
        SELECT 
            uv.id,
            uv.status,
            DATE(uv.created_at) as verification_date,
            TIME_FORMAT(uv.created_at, '%H:%i') as verification_time,
            COALESCE(u.fullname, 'Unknown User') as user_name
        FROM user_verifications uv
        LEFT JOIN users u ON uv.user_id = u.id
        WHERE DATE(uv.created_at) BETWEEN ? AND ?
        ORDER BY uv.created_at
    ";
    
    $stmt = $conn->prepare($verification_query);
    if ($stmt) {
        $stmt->bind_param('ss', $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'id' => 20000 + (int)$row['id'],
                    'verification_id' => (int)$row['id'],
                    'type' => 'verification',
                    'title' => '✓ Verification Request',
                    'description' => 'User: ' . $row['user_name'],
                    'date' => $row['verification_date'],
                    'time' => $row['verification_time'],
                    'status' => $row['status'],
                    'participants' => [
                        ['name' => $row['user_name'], 'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($row['user_name']) . '&background=8b5cf6&color=fff']
                    ]
                ];
            }
        }
    }
    
    // 4. VEHICLE LISTINGS
    $vehicle_query = "
        SELECT 
            'car' as vehicle_type,
            c.id,
            c.brand,
            c.model,
            c.status,
            DATE(c.created_at) as listing_date,
            TIME_FORMAT(c.created_at, '%H:%i') as listing_time,
            COALESCE(u.fullname, 'Unknown Owner') as owner_name
        FROM cars c
        LEFT JOIN users u ON c.owner_id = u.id
        WHERE DATE(c.created_at) BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 
            'motorcycle' as vehicle_type,
            m.id,
            m.brand,
            m.model,
            m.status,
            DATE(m.created_at) as listing_date,
            TIME_FORMAT(m.created_at, '%H:%i') as listing_time,
            COALESCE(u.fullname, 'Unknown Owner') as owner_name
        FROM motorcycles m
        LEFT JOIN users u ON m.owner_id = u.id
        WHERE DATE(m.created_at) BETWEEN ? AND ?
        
        ORDER BY listing_date, listing_time
    ";
    
    $stmt = $conn->prepare($vehicle_query);
    if ($stmt) {
        $stmt->bind_param('ssss', $start_date, $end_date, $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $icon = $row['vehicle_type'] === 'car' ? '🚗' : '🏍️';
                $events[] = [
                    'id' => 30000 + (int)$row['id'],
                    'vehicle_id' => (int)$row['id'],
                    'type' => 'vehicle',
                    'title' => $icon . ' New Listing: ' . $row['brand'] . ' ' . $row['model'],
                    'description' => 'Owner: ' . $row['owner_name'],
                    'date' => $row['listing_date'],
                    'time' => $row['listing_time'],
                    'status' => $row['status'],
                    'vehicle_type' => $row['vehicle_type'],
                    'participants' => [
                        ['name' => $row['owner_name'], 'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($row['owner_name']) . '&background=f59e0b&color=fff']
                    ]
                ];
            }
        }
    }
    
    // 5. REPORTS
    $report_query = "
        SELECT 
            r.id,
            r.report_type,
            r.status,
            DATE(r.created_at) as report_date,
            TIME_FORMAT(r.created_at, '%H:%i') as report_time,
            COALESCE(u.fullname, 'Anonymous') as reporter_name
        FROM reports r
        LEFT JOIN users u ON r.reporter_id = u.id
        WHERE DATE(r.created_at) BETWEEN ? AND ?
        ORDER BY r.created_at
    ";
    
    $stmt = $conn->prepare($report_query);
    if ($stmt) {
        $stmt->bind_param('ss', $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'id' => 40000 + (int)$row['id'],
                    'report_id' => (int)$row['id'],
                    'type' => 'report',
                    'title' => '⚠️ Report: ' . ucfirst($row['report_type']),
                    'description' => 'Reporter: ' . $row['reporter_name'],
                    'date' => $row['report_date'],
                    'time' => $row['report_time'],
                    'status' => $row['status'],
                    'participants' => [
                        ['name' => $row['reporter_name'], 'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($row['reporter_name']) . '&background=ef4444&color=fff']
                    ]
                ];
            }
        }
    }
    
    // 6. REFUNDS
    $refunds_check = mysqli_query($conn, "SHOW TABLES LIKE 'refunds'");
    if ($refunds_check && mysqli_num_rows($refunds_check) > 0) {
        $refund_query = "
            SELECT 
                r.id,
                r.booking_id,
                r.refund_amount as amount,
                r.status,
                DATE(r.created_at) as refund_date,
                TIME_FORMAT(r.created_at, '%H:%i') as refund_time,
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
                        'id' => 50000 + (int)$row['id'],
                        'refund_id' => (int)$row['id'],
                        'type' => 'refund',
                        'title' => '💰 Refund: ₱' . number_format($row['amount'], 2),
                        'description' => $row['user_name'] . ' - Refund request',
                        'date' => $row['refund_date'],
                        'time' => $row['refund_time'],
                        'status' => $row['status'],
                        'amount' => $row['amount'],
                        'participants' => [
                            ['name' => $row['user_name'], 'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($row['user_name']) . '&background=ec4899&color=fff']
                        ]
                    ];
                }
            }
        }
    }
    
    // Calculate statistics
    $stats = [
        'bookings' => 0,
        'payments' => 0,
        'verifications' => 0,
        'vehicles' => 0,
        'reports' => 0,
        'refunds' => 0
    ];
    
    foreach ($events as $event) {
        if ($event['type'] === 'booking') $stats['bookings']++;
        if ($event['type'] === 'payment') $stats['payments']++;
        if ($event['type'] === 'verification') $stats['verifications']++;
        if ($event['type'] === 'vehicle') $stats['vehicles']++;
        if ($event['type'] === 'report') $stats['reports']++;
        if ($event['type'] === 'refund') $stats['refunds']++;
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'stats' => $stats,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'total_events' => count($events)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching week events: ' . $e->getMessage(),
        'events' => [],
        'stats' => [
            'bookings' => 0,
            'payments' => 0,
            'verifications' => 0,
            'vehicles' => 0,
            'reports' => 0,
            'refunds' => 0
        ]
    ]);
}

$conn->close();
?>
