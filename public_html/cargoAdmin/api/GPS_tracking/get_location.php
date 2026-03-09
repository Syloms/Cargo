<?php
/**
 * Get Location
 * Wrapper endpoint - redirects to get_current_location.php logic
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
    $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : (isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0);
    
    if ($bookingId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid booking ID'
        ]);
        exit;
    }

    // Get the latest GPS location for this booking
    $query = "
        SELECT 
            g.*,
            b.user_id,
            b.owner_id,
            b.status as booking_status,
            u.fullname as renter_name
        FROM gps_tracking g
        JOIN bookings b ON g.booking_id = b.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE g.booking_id = ?
        ORDER BY g.timestamp DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'location' => $row,
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'last_updated' => $row['timestamp']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No GPS data found for this booking'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
