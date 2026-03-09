<?php
// ========================================
// UPDATED VERSION - Forces trip_status based on trip_started_at
// ========================================
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../../include/db.php';

$owner_id = $_GET['owner_id'] ?? null;

if (!$owner_id) {
    echo json_encode(['success' => false, 'message' => 'Owner ID required']);
    exit;
}

// Show ALL approved bookings (both in-progress AND upcoming)
$sql = "
SELECT 
    b.id AS booking_id,
    b.pickup_date,
    b.return_date,
    b.pickup_time,
    b.return_time,
    b.total_amount,
    b.price_per_day,
    b.security_deposit_amount,
    b.status,
    b.rental_period,
    b.created_at,
    b.trip_started_at,
    
    -- Refund fields
    b.refund_status,
    b.refund_requested,
    b.refund_amount,
    
    -- Odometer fields
    b.odometer_start,
    b.odometer_end,
    b.odometer_start_photo,
    b.odometer_end_photo,
    b.trip_started,
    
    -- Car Details
    COALESCE(c.brand, m.brand) AS brand,
    COALESCE(c.model, m.model) AS model,
    COALESCE(c.car_year, m.motorcycle_year) AS car_year,
    COALESCE(c.image, m.image) AS car_image,
    COALESCE(c.location, m.location) AS location,
    COALESCE(c.latitude, m.latitude) AS latitude,
    COALESCE(c.longitude, m.longitude) AS longitude,
    COALESCE(c.price_per_day, m.price_per_day) AS price_per_day,
    COALESCE(c.has_unlimited_mileage, m.has_unlimited_mileage) AS has_unlimited_mileage,
    COALESCE(c.daily_mileage_limit, m.daily_mileage_limit) AS daily_mileage_limit,
    
    -- Renter Details
    u.fullname AS renter_name,
    u.email AS renter_email,
    u.phone AS renter_contact,
    
    -- Calculate days remaining
    DATEDIFF(b.return_date, CURDATE()) AS days_remaining,
    DATEDIFF(CURDATE(), b.pickup_date) AS days_elapsed
    
FROM bookings b
LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
LEFT JOIN users u ON b.user_id = u.id
WHERE b.owner_id = ?
AND b.status = 'approved'
AND (
    b.return_date >= CURDATE() 
    OR DATEDIFF(CURDATE(), b.return_date) <= 7
)
ORDER BY 
    CASE 
        WHEN b.pickup_date <= CURDATE() THEN 0
        ELSE 1
    END,
    b.pickup_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $carName = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''));
    
    $carImage = $row['car_image'] ?? '';
    if (!empty($carImage) && strpos($carImage, 'http') !== 0) {
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../../include/config.php';
        }
        $carImage = BASE_URL . '/' . $carImage;
    }
    
    // Calculate total rental days
    $totalDays = max(1, (strtotime($row['return_date']) - strtotime($row['pickup_date'])) / 86400);
    
    // Determine trip status with return time awareness
    $tripStatus = 'past';
    $nowTs = time();
    $pickupTs = strtotime(trim(($row['pickup_date'] ?? '') . ' ' . ($row['pickup_time'] ?? '00:00:00')));
    $returnTs = strtotime(trim(($row['return_date'] ?? '') . ' ' . ($row['return_time'] ?? '23:59:59')));
    $hasStarted = ($row['trip_started_at'] !== null);
    $hasEndedReading = (!empty($row['odometer_end']));
    if ($hasStarted && $returnTs !== false && $nowTs >= $returnTs && !$hasEndedReading) {
        $tripStatus = 'overdue';
    } elseif ($hasStarted) {
        $tripStatus = 'in_progress';
    } elseif ($pickupTs !== false && $nowTs < $pickupTs) {
        $tripStatus = 'upcoming';
    }
    
    // Calculate progress based on actual trip start
    $progress = 0;
    $daysElapsed = 0;

    if ($row['trip_started_at'] !== null) {
        // Trip has started - calculate from trip_started_at to actual return datetime
        $tripStartTime = strtotime($row['trip_started_at']);
        // Use actual return_time (not hardcoded 23:59:59) so same-day trips progress correctly
        $actualReturnTime = !empty($row['return_time']) ? $row['return_time'] : '23:59:59';
        $tripEndTime = strtotime($row['return_date'] . ' ' . $actualReturnTime);
        $currentTime = time();

        $totalTripSeconds = max(1, $tripEndTime - $tripStartTime);
        $elapsedSeconds = max(0, $currentTime - $tripStartTime);

        $progress = min(100, ($elapsedSeconds / $totalTripSeconds) * 100);
        $daysElapsed = max(0, floor($elapsedSeconds / 86400));
    } else {
        $progress = 0;
        $daysElapsed = 0;
    }

    // Compute a human-readable "time remaining" label
    $timeRemainingLabel = '';
    if ($returnTs !== false && $nowTs < $returnTs) {
        $secsLeft = $returnTs - $nowTs;
        if ($secsLeft < 3600) {
            $timeRemainingLabel = max(1, (int)ceil($secsLeft / 60)) . ' min left';
        } elseif ($secsLeft < 86400) {
            $timeRemainingLabel = max(1, (int)ceil($secsLeft / 3600)) . ' hr left';
        } else {
            $daysLeft = (int)ceil($secsLeft / 86400);
            $timeRemainingLabel = $daysLeft . ' day' . ($daysLeft !== 1 ? 's' : '') . ' left';
        }
    } elseif ($tripStatus === 'overdue') {
        $timeRemainingLabel = 'Overdue';
    }
    
    $bookings[] = [
        'booking_id' => $row['booking_id'],
        'car_name' => $carName,
        'car_full_name' => $carName . ' ' . $row['car_year'],
        'car_image' => $carImage,
        'location' => $row['location'] ?? '',
        'latitude' => !empty($row['latitude']) ? (float)$row['latitude'] : null,
        'longitude' => !empty($row['longitude']) ? (float)$row['longitude'] : null,
        'price_per_day' => $row['price_per_day'] ?? 0,
        'renter_name' => $row['renter_name'] ?? 'Unknown',
        'renter_email' => $row['renter_email'] ?? '',
        'renter_contact' => $row['renter_contact'] ?? '',
        'pickup_date' => date('M d, Y', strtotime($row['pickup_date'])),
        'return_date' => date('M d, Y', strtotime($row['return_date'])),
        'pickup_time' => date('h:i A', strtotime($row['pickup_time'])),
        'return_time' => date('h:i A', strtotime($row['return_time'])),

        // Raw values (for strict client-side validation / parsing)
        'pickup_date_raw' => $row['pickup_date'],
        'pickup_time_raw' => $row['pickup_time'],
        'return_date_raw' => $row['return_date'],
        'return_time_raw' => $row['return_time'],
        
        // Individual time fields for display
        'pickup_time_display' => date('h:i A', strtotime($row['pickup_time'])),
        'return_time_display' => date('h:i A', strtotime($row['return_time'])),
        'total_amount' => number_format($row['total_amount'], 0),
        'price_per_day' => (float)($row['price_per_day'] ?? 0),
        'security_deposit' => (float)($row['security_deposit_amount'] ?? 0),
        'rental_period' => $row['rental_period'],
        'days_remaining' => max(0, intval($row['days_remaining'])),
        'time_remaining_label' => $timeRemainingLabel,
        'days_elapsed' => $daysElapsed,
        'trip_progress' => round($progress, 1),
        'trip_status' => $tripStatus,
        'has_unlimited_mileage' => isset($row['has_unlimited_mileage']) ? (int)$row['has_unlimited_mileage'] : 0,
        'daily_mileage_limit' => $row['daily_mileage_limit'] ?? null,
        'is_overdue' => ($tripStatus === 'overdue'),
        'hours_overdue' => ($tripStatus === 'overdue' && $returnTs !== false) ? max(0, floor(($nowTs - $returnTs) / 3600)) : 0,
        'trip_started_at' => $row['trip_started_at'] ?? null,
        'status' => 'active',
        
        // Odometer fields
        'odometer_start' => $row['odometer_start'],
        'odometer_end' => $row['odometer_end'],
        'odometer_start_photo' => $row['odometer_start_photo'],
        'odometer_end_photo' => $row['odometer_end_photo'],
        'trip_started' => $row['trip_started'],
        
        // Refund status fields
        'refund_status' => $row['refund_status'] ?? 'not_requested',
        'refund_requested' => (int)($row['refund_requested'] ?? 0),
        'refund_amount' => (float)($row['refund_amount'] ?? 0),
    ];
}

echo json_encode([
    'success' => true,
    'bookings' => $bookings
]);

$stmt->close();
$conn->close();
?>
