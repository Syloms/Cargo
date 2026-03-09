<?php
// api/bookings/start_trip.php
// Allows owner to mark rental as started when renter picks up vehicle

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$booking_id = $_POST['booking_id'] ?? null;
$owner_id = $_POST['owner_id'] ?? null;

if (!$booking_id || !$owner_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Verify booking exists and owner owns it
$checkSql = "SELECT id, user_id, owner_id, status, pickup_date, pickup_time, trip_started_at 
             FROM bookings 
             WHERE id = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Booking not found',
        'error_code' => 'BOOKING_NOT_FOUND'
    ]);
    exit;
}

$booking = $result->fetch_assoc();

// Verify ownership
if ($booking['owner_id'] != $owner_id) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized: You do not own this booking',
        'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
}

// Check if booking is approved
if ($booking['status'] !== 'approved') {
    echo json_encode([
        'success' => false, 
        'message' => 'Booking must be approved to start trip',
        'error_code' => 'INVALID_STATUS',
        'current_status' => $booking['status']
    ]);
    exit;
}

// Check if trip already started
if (!empty($booking['trip_started_at'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Trip has already been started',
        'error_code' => 'ALREADY_STARTED',
        'started_at' => $booking['trip_started_at']
    ]);
    exit;
}

// ✅ Strict validation: owner can only start the trip AT or AFTER the scheduled pickup date+time.
// This prevents early starts that can break booking flow integrity.
//
// NOTE: Uses server time (not device time). Ensure server timezone is set correctly.
date_default_timezone_set('Asia/Manila');

$pickupDateStr = $booking['pickup_date'] ?? '';
$pickupTimeStr = $booking['pickup_time'] ?? '';

// Fallback: if pickup_time is missing, assume 00:00:00 (still blocks early starts before that time)
$scheduledPickupTs = strtotime(trim($pickupDateStr . ' ' . $pickupTimeStr));
$nowTs = time();

if ($scheduledPickupTs !== false && $nowTs < $scheduledPickupTs) {
    $secondsUntil = $scheduledPickupTs - $nowTs;
    echo json_encode([
        'success' => false,
        'message' => 'You can only start the pickup at the scheduled pickup time.',
        'error_code' => 'TOO_EARLY',
        'pickup_date' => $pickupDateStr,
        'pickup_time' => $pickupTimeStr,
        'seconds_until_pickup' => $secondsUntil,
        'scheduled_pickup' => trim($pickupDateStr . ' ' . $pickupTimeStr)
    ]);
    exit;
}

$userId = $booking['user_id'];

// Update booking to mark trip as started
$updateSql = "UPDATE bookings 
              SET trip_started_at = NOW(), 
                  status = 'ongoing',
                  updated_at = NOW() 
              WHERE id = ?";
$stmt = $conn->prepare($updateSql);
$stmt->bind_param("s", $booking_id);

if ($stmt->execute()) {
    // Send notification to renter
    $notifSql = "INSERT INTO notifications (user_id, title, message, created_at) 
                 VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($notifSql);
    $title = "Trip Started! 🚗";
    $message = "Your rental for booking #{$booking_id} has started. The owner has confirmed vehicle pickup. Enjoy your trip!";
    
    $stmt->bind_param("sss", $userId, $title, $message);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Trip started successfully! Vehicle has been picked up.',
        'started_at' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to start trip. Please try again.',
        'error_code' => 'UPDATE_FAILED'
    ]);
}

$stmt->close();
$conn->close();
?>
