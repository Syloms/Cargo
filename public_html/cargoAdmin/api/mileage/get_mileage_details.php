<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include "../../include/db.php";

$booking_id = $_GET['booking_id'] ?? null;

if (empty($booking_id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing booking_id parameter"
    ]);
    exit;
}

// Get booking mileage details
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.vehicle_type,
        b.car_id,
        b.pickup_date,
        b.return_date,
        b.odometer_start,
        b.odometer_end,
        b.odometer_start_photo,
        b.odometer_end_photo,
        b.odometer_start_timestamp,
        b.odometer_end_timestamp,
        b.actual_mileage,
        b.allowed_mileage,
        b.excess_mileage,
        b.excess_mileage_fee,
        b.excess_mileage_paid,
        b.gps_distance,
        b.mileage_verified_by,
        b.mileage_verified_at,
        b.mileage_notes,
        DATEDIFF(b.return_date, b.pickup_date) + 1 AS rental_days,
        u.fullname AS renter_name,
        o.fullname AS owner_name
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN users o ON b.owner_id = o.id
    WHERE b.id = ?
");

$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Booking not found"
    ]);
    exit;
}

$booking = $result->fetch_assoc();

// Get vehicle details
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

// Get GPS tracking data
$gps_stmt = $conn->prepare("
    SELECT total_distance_km, waypoints_count, last_updated 
    FROM gps_distance_tracking 
    WHERE booking_id = ?
");
$gps_stmt->bind_param("i", $booking_id);
$gps_stmt->execute();
$gps_result = $gps_stmt->get_result();
$gps_data = $gps_result->num_rows > 0 ? $gps_result->fetch_assoc() : null;

// Get mileage logs
$logs_stmt = $conn->prepare("
    SELECT 
        log_type, 
        recorded_by_type, 
        odometer_value, 
        photo_path, 
        notes,
        created_at 
    FROM mileage_logs 
    WHERE booking_id = ?
    ORDER BY created_at ASC
");
$logs_stmt->bind_param("i", $booking_id);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();
$logs = [];
while ($row = $logs_result->fetch_assoc()) {
    $logs[] = $row;
}

// Check for disputes
$dispute_stmt = $conn->prepare("
    SELECT id, dispute_type, status, description, created_at 
    FROM mileage_disputes 
    WHERE booking_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$dispute_stmt->bind_param("i", $booking_id);
$dispute_stmt->execute();
$dispute_result = $dispute_stmt->get_result();
$dispute = $dispute_result->num_rows > 0 ? $dispute_result->fetch_assoc() : null;

// Calculate discrepancy
$discrepancy = null;
$discrepancy_percentage = null;
if ($booking['actual_mileage'] && $booking['gps_distance']) {
    $discrepancy = abs($booking['actual_mileage'] - $booking['gps_distance']);
    $discrepancy_percentage = ($discrepancy / $booking['actual_mileage']) * 100;
}

// Determine status
$mileage_status = 'not_started';
if ($booking['odometer_start'] && !$booking['odometer_end']) {
    $mileage_status = 'in_progress';
} elseif ($booking['odometer_end']) {
    if ($booking['mileage_verified_by']) {
        $mileage_status = 'verified';
    } elseif ($discrepancy_percentage && $discrepancy_percentage > 20) {
        $mileage_status = 'needs_verification';
    } else {
        $mileage_status = 'completed';
    }
}

echo json_encode([
    "status" => "success",
    "data" => [
        "booking_id" => $booking['id'],
        "vehicle" => [
            "type" => $booking['vehicle_type'],
            "brand" => $vehicle['brand'],
            "model" => $vehicle['model'],
            "has_unlimited_mileage" => $vehicle['has_unlimited_mileage'] == 1,
            "daily_limit" => $vehicle['daily_mileage_limit'],
            "excess_rate" => $vehicle['excess_mileage_rate']
        ],
        "rental" => [
            "pickup_date" => $booking['pickup_date'],
            "return_date" => $booking['return_date'],
            "rental_days" => $booking['rental_days'],
            "renter_name" => $booking['renter_name'],
            "owner_name" => $booking['owner_name']
        ],
        "odometer" => [
            "start" => $booking['odometer_start'],
            "end" => $booking['odometer_end'],
            "start_photo" => $booking['odometer_start_photo'],
            "end_photo" => $booking['odometer_end_photo'],
            "start_timestamp" => $booking['odometer_start_timestamp'],
            "end_timestamp" => $booking['odometer_end_timestamp']
        ],
        "mileage" => [
            "actual" => $booking['actual_mileage'],
            "allowed" => $booking['allowed_mileage'],
            "excess" => $booking['excess_mileage'],
            "excess_fee" => $booking['excess_mileage_fee'],
            "excess_paid" => $booking['excess_mileage_paid'] == 1,
            "status" => $mileage_status
        ],
        "gps" => [
            "distance" => $booking['gps_distance'],
            "waypoints" => $gps_data['waypoints_count'] ?? 0,
            "last_updated" => $gps_data['last_updated'] ?? null,
            "discrepancy" => $discrepancy,
            "discrepancy_percentage" => $discrepancy_percentage ? round($discrepancy_percentage, 2) : null
        ],
        "verification" => [
            "verified" => $booking['mileage_verified_by'] !== null,
            "verified_by" => $booking['mileage_verified_by'],
            "verified_at" => $booking['mileage_verified_at'],
            "notes" => $booking['mileage_notes']
        ],
        "dispute" => $dispute,
        "logs" => $logs
    ]
]);

$conn->close();
?>
