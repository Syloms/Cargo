<?php
/**
 * ============================================================================
 * UNBLOCK DATES - Vehicle Availability Calendar
 * Owner can unblock previously blocked dates
 * ============================================================================
 */

// Disable error display to prevent HTML in JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Debug logging
error_log("Unblock Dates Request - Raw data: " . print_r($data, true));

// Validate required fields
$owner_id = $data['owner_id'] ?? null;
$vehicle_id = $data['vehicle_id'] ?? null;
$vehicle_type = $data['vehicle_type'] ?? 'car';
$dates = $data['dates'] ?? [];

// Debug logging
error_log("Unblock Dates - owner_id: $owner_id, vehicle_id: $vehicle_id, vehicle_type: $vehicle_type");

if (!$owner_id || !$vehicle_id || empty($dates)) {
    $missing = [];
    if (!$owner_id) $missing[] = 'owner_id';
    if (!$vehicle_id) $missing[] = 'vehicle_id';
    if (empty($dates)) $missing[] = 'dates';
    
    error_log("Unblock Dates Error - Missing fields: " . implode(', ', $missing));
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: ' . implode(', ', $missing)
    ]);
    exit;
}

// Verify ownership
$table = ($vehicle_type === 'car' ? 'cars' : 'motorcycles');
$verify_query = "SELECT id FROM $table WHERE id = ? AND owner_id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $vehicle_id, $owner_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();

if ($result->num_rows === 0) {
    // Check if vehicle exists at all
    $check_query = "SELECT id, owner_id FROM $table WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $vehicle_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        error_log("Unblock Dates Error - Vehicle ID $vehicle_id not found in $table");
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
    } else {
        $vehicle = $check_result->fetch_assoc();
        error_log("Unblock Dates Error - Owner mismatch. Vehicle owner: {$vehicle['owner_id']}, Request owner: $owner_id");
        echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not own this vehicle']);
    }
    exit;
}

// Validate date format
foreach ($dates as $date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        error_log("Unblock Dates Error - Invalid date format: $date");
        echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
        exit;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    $unblocked_count = 0;
    $skipped_booked = 0;
    $skipped_not_blocked = 0;
    
    foreach ($dates as $date) {
        // Check if date is actually blocked
        $check_query = "SELECT id FROM vehicle_availability 
                        WHERE owner_id = ? AND vehicle_id = ? AND vehicle_type = ? AND blocked_date = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("iiss", $owner_id, $vehicle_id, $vehicle_type, $date);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows === 0) {
            $skipped_not_blocked++;
            continue;
        }
        
        // Check if date has active bookings (prevent unblocking booked dates)
        $booking_check = "SELECT id FROM bookings 
                         WHERE car_id = ? 
                         AND vehicle_type = ? 
                         AND status IN ('pending', 'approved', 'ongoing')
                         AND payment_status = 'verified'
                         AND ? BETWEEN pickup_date AND return_date";
        $booking_stmt = $conn->prepare($booking_check);
        $booking_stmt->bind_param("iss", $vehicle_id, $vehicle_type, $date);
        $booking_stmt->execute();
        
        if ($booking_stmt->get_result()->num_rows > 0) {
            // Skip dates with active bookings
            error_log("Unblock Dates - Skipped $date: has active booking");
            $skipped_booked++;
            continue;
        }
        
        // Delete the blocked date
        $delete_query = "DELETE FROM vehicle_availability 
                        WHERE owner_id = ? AND vehicle_id = ? AND vehicle_type = ? AND blocked_date = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("iiss", $owner_id, $vehicle_id, $vehicle_type, $date);
        
        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            $unblocked_count++;
        }
    }
    
    $conn->commit();
    
    $message = "$unblocked_count date(s) unblocked successfully";
    if ($skipped_booked > 0) {
        $message .= ". $skipped_booked date(s) skipped (has active bookings)";
    }
    if ($skipped_not_blocked > 0) {
        $message .= ". $skipped_not_blocked date(s) were not blocked";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'unblocked_count' => $unblocked_count,
        'skipped_booked' => $skipped_booked,
        'skipped_not_blocked' => $skipped_not_blocked
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Unblock Dates Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error unblocking dates: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
