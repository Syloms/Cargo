<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include "../../include/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit;
}

// Get POST data
$booking_id = $_POST['booking_id'] ?? null;
$odometer_reading = $_POST['odometer_reading'] ?? null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$recorded_by = $_POST['recorded_by'] ?? null;
$recorded_by_type = $_POST['recorded_by_type'] ?? 'renter';

// Validate required fields
if (empty($booking_id) || empty($odometer_reading) || empty($recorded_by)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: booking_id, odometer_reading, recorded_by"
    ]);
    exit;
}

// Validate odometer reading
if (!is_numeric($odometer_reading) || $odometer_reading < 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid odometer reading"
    ]);
    exit;
}

// Handle photo upload with validation
$photo_path = null;
$photo_url = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = "../../uploads/odometer/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $mime = $_FILES['photo']['type'] ?? '';
    $size = $_FILES['photo']['size'] ?? 0;
    $allowed = defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg','image/jpg','image/png','image/gif'];
    $maxSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 5242880;
    if (!in_array($mime, $allowed, true)) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid image type"
        ]);
        exit;
    }
    if ($size > $maxSize) {
        echo json_encode([
            "status" => "error",
            "message" => "Image too large"
        ]);
        exit;
    }
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif'], true)) {
        echo json_encode([
            "status" => "error",
            "message" => "Unsupported image extension"
        ]);
        exit;
    }
    $file_name = "odometer_end_{$booking_id}_" . time() . "." . $ext;
    $target_file = $upload_dir . $file_name;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $photo_path = "uploads/odometer/" . $file_name;
        if (defined('BASE_URL')) {
            $photo_url = BASE_URL . '/' . $photo_path;
        }
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to upload photo"
        ]);
        exit;
    }
}

// Get booking details
$check_stmt = $conn->prepare("
    SELECT 
        b.id, b.status, b.odometer_start, b.odometer_end, 
        b.vehicle_type, b.car_id, b.pickup_date, b.return_date,
        b.user_id, b.owner_id,
        COALESCE(c.has_unlimited_mileage, m.has_unlimited_mileage) AS has_unlimited_mileage,
        DATEDIFF(b.return_date, b.pickup_date) + 1 AS rental_days
    FROM bookings b
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    WHERE b.id = ?
");
$check_stmt->bind_param("i", $booking_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Booking not found"
    ]);
    exit;
}

$booking = $result->fetch_assoc();

// If vehicle has unlimited mileage, odometer tracking is not required
$isUnlimited = !empty($booking['has_unlimited_mileage']) && intval($booking['has_unlimited_mileage']) === 1;
if ($isUnlimited) {
    echo json_encode([
        "status" => "success",
        "message" => "Odometer tracking not required for unlimited mileage vehicle",
        "data" => [
            "booking_id" => $booking_id,
            "odometer_required" => false
        ]
    ]);
    exit;
}

// Check if starting odometer recorded
if (empty($booking['odometer_start'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Starting odometer must be recorded first"
    ]);
    exit;
}

// Check if ending odometer already recorded AND trip is completed
// Allow re-recording if trip is still in 'approved' or 'ongoing' status
if (!empty($booking['odometer_end']) && $booking['status'] === 'completed') {
    echo json_encode([
        "status" => "error",
        "message" => "Ending odometer already recorded for this completed trip",
        "existing_reading" => $booking['odometer_end']
    ]);
    exit;
}

// If odometer_end exists but trip not completed, allow update (user might be correcting)
if (!empty($booking['odometer_end']) && in_array($booking['status'], ['approved', 'ongoing'])) {
    // Log that we're updating existing odometer reading
    error_log("Updating existing odometer_end for booking {$booking_id}: old={$booking['odometer_end']}, new={$odometer_reading}");
}

// Validate odometer reading is greater than start
if ($odometer_reading <= $booking['odometer_start']) {
    echo json_encode([
        "status" => "error",
        "message" => "Ending odometer must be greater than starting odometer",
        "odometer_start" => $booking['odometer_start'],
        "odometer_end_provided" => $odometer_reading
    ]);
    exit;
}

// Calculate actual mileage
$actual_mileage = $odometer_reading - $booking['odometer_start'];

// Get vehicle mileage settings
if ($booking['vehicle_type'] === 'car') {
    $vehicle_stmt = $conn->prepare("
        SELECT brand, model, has_unlimited_mileage, daily_mileage_limit, excess_mileage_rate 
        FROM cars WHERE id = ?
    ");
} else {
    $vehicle_stmt = $conn->prepare("
        SELECT brand, model, has_unlimited_mileage, daily_mileage_limit, excess_mileage_rate 
        FROM motorcycles WHERE id = ?
    ");
}
$vehicle_stmt->bind_param("i", $booking['car_id']);
$vehicle_stmt->execute();
$vehicle_result = $vehicle_stmt->get_result();
$vehicle = $vehicle_result->fetch_assoc();

// Calculate allowed mileage and excess
$allowed_mileage = null;
$excess_mileage = 0;
$excess_mileage_fee = 0.00;

if ($vehicle['has_unlimited_mileage'] == 0 && $vehicle['daily_mileage_limit'] !== null) {
    $allowed_mileage = $vehicle['daily_mileage_limit'] * $booking['rental_days'];
    
    if ($actual_mileage > $allowed_mileage) {
        $excess_mileage = $actual_mileage - $allowed_mileage;
        $excess_mileage_fee = $excess_mileage * $vehicle['excess_mileage_rate'];
    }
}

// Get GPS-tracked distance if available
$gps_distance = null;
$gps_stmt = $conn->prepare("SELECT total_distance_km FROM gps_distance_tracking WHERE booking_id = ?");
$gps_stmt->bind_param("i", $booking_id);
$gps_stmt->execute();
$gps_result = $gps_stmt->get_result();
if ($gps_result->num_rows > 0) {
    $gps_data = $gps_result->fetch_assoc();
    $gps_distance = $gps_data['total_distance_km'];
}

// Calculate discrepancy between GPS and odometer
$discrepancy_percentage = null;
$needs_verification = false;
if ($gps_distance !== null && $gps_distance > 0) {
    $discrepancy = abs($actual_mileage - $gps_distance);
    $discrepancy_percentage = ($discrepancy / $actual_mileage) * 100;
    
    // Flag for verification if discrepancy > 20%
    if ($discrepancy_percentage > 20) {
        $needs_verification = true;
    }
}

// Update booking with ending odometer and calculations
$update_stmt = $conn->prepare("
    UPDATE bookings 
    SET odometer_end = ?,
        odometer_end_photo = ?,
        odometer_end_timestamp = NOW(),
        actual_mileage = ?,
        allowed_mileage = ?,
        excess_mileage = ?,
        excess_mileage_fee = ?,
        gps_distance = ?
    WHERE id = ?
");
$update_stmt->bind_param("isiiiddi", 
    $odometer_reading, 
    $photo_path, 
    $actual_mileage, 
    $allowed_mileage, 
    $excess_mileage, 
    $excess_mileage_fee,
    $gps_distance,
    $booking_id
);

if (!$update_stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update booking: " . $update_stmt->error
    ]);
    exit;
}

// Log the mileage recording
$log_stmt = $conn->prepare("
    INSERT INTO mileage_logs 
    (booking_id, log_type, recorded_by, recorded_by_type, odometer_value, photo_path, gps_latitude, gps_longitude, notes, created_at)
    VALUES (?, 'end_recorded', ?, ?, ?, ?, ?, ?, ?, NOW())
");
$notes = json_encode([
    "actual_mileage" => $actual_mileage,
    "excess_mileage" => $excess_mileage,
    "excess_fee" => $excess_mileage_fee,
    "gps_distance" => $gps_distance,
    "discrepancy_percentage" => $discrepancy_percentage
]);
$log_stmt->bind_param("iisisddss", $booking_id, $recorded_by, $recorded_by_type, $odometer_reading, $photo_path, $latitude, $longitude, $notes);
$log_stmt->execute();

// Log excess calculation if applicable
if ($excess_mileage > 0) {
    $excess_log_stmt = $conn->prepare("
        INSERT INTO mileage_logs 
        (booking_id, log_type, recorded_by, recorded_by_type, notes, created_at)
        VALUES (?, 'excess_calculated', ?, 'admin', ?, NOW())
    ");
    $excess_notes = "Excess mileage: {$excess_mileage} km, Fee: ₱{$excess_mileage_fee}";
    $excess_log_stmt->bind_param("iis", $booking_id, $recorded_by, $excess_notes);
    $excess_log_stmt->execute();
}

// Send notifications
$notify_user_id = null;
$notify_title = "";
$notify_message = "";

if ($recorded_by_type === 'renter') {
    $notify_user_id = $booking['owner_id'];
    $notify_title = "Trip Ended 🏁";
    $notify_message = "Renter ended trip. Distance: {$actual_mileage} km";
    if ($excess_mileage > 0) {
        $notify_message .= " | Excess: {$excess_mileage} km (₱{$excess_mileage_fee})";
    }
} else {
    $notify_user_id = $booking['user_id'];
    $notify_title = "Odometer Recorded 📸";
    $notify_message = "Final odometer: {$odometer_reading} km | Distance: {$actual_mileage} km";
    if ($excess_mileage > 0) {
        $notify_message .= " | You have excess mileage charges: ₱{$excess_mileage_fee}";
    }
}

if ($notify_user_id) {
    $notify_stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, ?, ?, 'info', NOW())
    ");
    $notify_stmt->bind_param("iss", $notify_user_id, $notify_title, $notify_message);
    $notify_stmt->execute();
}

echo json_encode([
    "status" => "success",
    "message" => "Ending odometer recorded successfully",
    "data" => [
        "booking_id" => $booking_id,
        "odometer_start" => $booking['odometer_start'],
        "odometer_end" => $odometer_reading,
        "actual_mileage" => $actual_mileage,
        "allowed_mileage" => $allowed_mileage,
        "excess_mileage" => $excess_mileage,
        "excess_mileage_fee" => $excess_mileage_fee,
        "gps_distance" => $gps_distance,
        "discrepancy_percentage" => $discrepancy_percentage,
        "needs_verification" => $needs_verification,
        "photo_path" => $photo_path,
        "photo_url" => $photo_url,
        "timestamp" => date('Y-m-d H:i:s'),
        "vehicle" => [
            "brand" => $vehicle['brand'],
            "model" => $vehicle['model'],
            "has_unlimited_mileage" => $vehicle['has_unlimited_mileage'] == 1,
            "daily_limit" => $vehicle['daily_mileage_limit'],
            "excess_rate" => $vehicle['excess_mileage_rate']
        ]
    ]
]);

$conn->close();
?>
