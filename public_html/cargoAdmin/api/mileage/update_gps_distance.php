<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

include "../../include/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit;
}

$booking_id = $_POST['booking_id'] ?? null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

if (empty($booking_id) || empty($latitude) || empty($longitude)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: booking_id, latitude, longitude"
    ]);
    exit;
}

// Get current GPS tracking data
$stmt = $conn->prepare("
    SELECT total_distance_km, last_latitude, last_longitude, waypoints_count 
    FROM gps_distance_tracking 
    WHERE booking_id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Initialize GPS tracking
    $insert_stmt = $conn->prepare("
        INSERT INTO gps_distance_tracking 
        (booking_id, total_distance_km, last_latitude, last_longitude, last_updated, waypoints_count)
        VALUES (?, 0.00, ?, ?, NOW(), 1)
    ");
    $insert_stmt->bind_param("idd", $booking_id, $latitude, $longitude);
    $insert_stmt->execute();
    
    echo json_encode([
        "status" => "success",
        "message" => "GPS tracking initialized",
        "total_distance" => 0.00
    ]);
    exit;
}

$gps_data = $result->fetch_assoc();
$last_lat = $gps_data['last_latitude'];
$last_lon = $gps_data['last_longitude'];
$current_distance = $gps_data['total_distance_km'];
$waypoints_count = $gps_data['waypoints_count'];

// Calculate distance using Haversine formula
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

// Calculate distance from last point
$distance_increment = haversineDistance($last_lat, $last_lon, $latitude, $longitude);

// Only update if movement is significant (> 0.01 km = 10 meters)
if ($distance_increment > 0.01) {
    $new_total_distance = $current_distance + $distance_increment;
    $new_waypoints_count = $waypoints_count + 1;
    
    // Update GPS tracking
    $update_stmt = $conn->prepare("
        UPDATE gps_distance_tracking 
        SET total_distance_km = ?,
            last_latitude = ?,
            last_longitude = ?,
            last_updated = NOW(),
            waypoints_count = ?
        WHERE booking_id = ?
    ");
    $update_stmt->bind_param("dddii", $new_total_distance, $latitude, $longitude, $new_waypoints_count, $booking_id);
    $update_stmt->execute();
    
    // Also update the booking's gps_distance field
    $booking_update = $conn->prepare("
        UPDATE bookings 
        SET gps_distance = ?
        WHERE id = ?
    ");
    $booking_update->bind_param("di", $new_total_distance, $booking_id);
    $booking_update->execute();
    
    echo json_encode([
        "status" => "success",
        "message" => "GPS distance updated",
        "data" => [
            "total_distance" => round($new_total_distance, 2),
            "increment" => round($distance_increment, 2),
            "waypoints" => $new_waypoints_count,
            "last_updated" => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "message" => "Position updated (no significant movement)",
        "data" => [
            "total_distance" => round($current_distance, 2),
            "waypoints" => $waypoints_count
        ]
    ]);
}

$conn->close();
?>
