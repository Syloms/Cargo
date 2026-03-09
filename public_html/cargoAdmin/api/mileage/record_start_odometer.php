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
$recorded_by = $_POST['recorded_by'] ?? null; // user_id or owner_id
$recorded_by_type = $_POST['recorded_by_type'] ?? 'renter'; // 'renter' or 'owner'

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

// Handle photo upload
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = "../../uploads/odometer/";
    
    // Create directory if it doesn't exist
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
    $file_name = "odometer_start_{$booking_id}_" . time() . "." . $ext;
    $target_file = $upload_dir . $file_name;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $photo_path = "uploads/odometer/" . $file_name;
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to upload photo"
        ]);
        exit;
    }
}

// Check if booking exists and is in valid state
$check_stmt = $conn->prepare("
    SELECT 
        b.id, b.status, b.odometer_start, b.vehicle_type, b.car_id,
        COALESCE(c.has_unlimited_mileage, m.has_unlimited_mileage) AS has_unlimited_mileage
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

// Check if odometer already recorded
if (!empty($booking['odometer_start'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Starting odometer already recorded for this booking",
        "existing_reading" => $booking['odometer_start']
    ]);
    exit;
}

// Check if booking is in appropriate status
if (!in_array($booking['status'], ['approved', 'active'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Booking must be approved or active to record odometer"
    ]);
    exit;
}

// Update booking with starting odometer
$update_stmt = $conn->prepare("
    UPDATE bookings 
    SET odometer_start = ?,
        odometer_start_photo = ?,
        odometer_start_timestamp = NOW()
    WHERE id = ?
");
$update_stmt->bind_param("isi", $odometer_reading, $photo_path, $booking_id);

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
    (booking_id, log_type, recorded_by, recorded_by_type, odometer_value, photo_path, gps_latitude, gps_longitude, created_at)
    VALUES (?, 'start_recorded', ?, ?, ?, ?, ?, ?, NOW())
");
$log_stmt->bind_param("iisisdd", $booking_id, $recorded_by, $recorded_by_type, $odometer_reading, $photo_path, $latitude, $longitude);
$log_stmt->execute();

// Initialize GPS tracking if enabled
if ($latitude && $longitude) {
    $gps_stmt = $conn->prepare("
        INSERT INTO gps_distance_tracking 
        (booking_id, total_distance_km, last_latitude, last_longitude, last_updated, waypoints_count)
        VALUES (?, 0.00, ?, ?, NOW(), 1)
        ON DUPLICATE KEY UPDATE 
        last_latitude = ?,
        last_longitude = ?,
        last_updated = NOW()
    ");
    $gps_stmt->bind_param("idddd", $booking_id, $latitude, $longitude, $latitude, $longitude);
    $gps_stmt->execute();
}

// Send notification to other party
$notify_user_id = null;
$notify_title = "";
$notify_message = "";

if ($recorded_by_type === 'renter') {
    // Notify owner
    $notify_user_id = $booking['owner_id'] ?? null;
    $notify_title = "Trip Started 🚗";
    $notify_message = "Renter has started the trip. Starting odometer: {$odometer_reading} km";
} else {
    // Notify renter
    $notify_user_id = $booking['user_id'] ?? null;
    $notify_title = "Odometer Recorded 📸";
    $notify_message = "Vehicle owner recorded starting odometer: {$odometer_reading} km";
}

if ($notify_user_id) {
    $notify_stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, ?, ?, 'info', NOW())
    ");
    $notify_stmt->bind_param("iss", $notify_user_id, $notify_title, $notify_message);
    $notify_stmt->execute();
}

// Get vehicle info for response
$vehicle_info = [];
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
if ($vehicle_result->num_rows > 0) {
    $vehicle_info = $vehicle_result->fetch_assoc();
}

echo json_encode([
    "status" => "success",
    "message" => "Starting odometer recorded successfully",
    "data" => [
        "booking_id" => $booking_id,
        "odometer_reading" => $odometer_reading,
        "photo_path" => $photo_path,
        "timestamp" => date('Y-m-d H:i:s'),
        "vehicle_info" => $vehicle_info
    ]
]);

$conn->close();
?>
