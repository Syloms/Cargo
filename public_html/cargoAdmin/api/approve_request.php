<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../include/db.php";

$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

if ($booking_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing booking_id"]);
    exit;
}

// UPDATE STATUS (only if pending)
$update = $conn->prepare("
    UPDATE bookings 
    SET status = 'approved' 
    WHERE id = ? AND status = 'pending'
    LIMIT 1
");
$update->bind_param("i", $booking_id);
$update->execute();

if ($update->affected_rows <= 0) {
    echo json_encode(["success" => false, "message" => "Booking not found or already processed"]);
    exit;
}
$update->close();

// FETCH BOOKING DATA - Support both cars and motorcycles
$q = $conn->prepare("
    SELECT b.*, 
           b.vehicle_type,
           b.insurance_policy_id,
           COALESCE(c.brand, m.brand) AS car_name,
           COALESCE(c.model, m.model) AS model
    FROM bookings b
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    WHERE b.id = ?
    LIMIT 1
");
$q->bind_param("i", $booking_id);
$q->execute();
$res = $q->get_result();
$booking = $res->fetch_assoc();
$q->close();

if (!$booking) {
    echo json_encode(["success" => false, "message" => "Booking details missing"]);
    exit;
}

$renter_id = $booking['user_id'];
$owner_id  = $booking['owner_id'];
$vehicle_name = trim($booking['car_name'] . ' ' . $booking['model']);
$vehicle_type = ucfirst($booking['vehicle_type'] ?? 'vehicle');

// CREATE INSURANCE POLICY (if not already exists)
$insurance_created = false;
$insurance_error = null;

if (empty($booking['insurance_policy_id'])) {
    try {
        // Check if insurance tables exist
        $table_check = $conn->query("SHOW TABLES LIKE 'insurance_providers'");
        if (!$table_check || $table_check->num_rows === 0) {
            error_log("Insurance tables not found - skipping insurance creation for booking {$booking_id}");
            $insurance_error = "Insurance system not configured";
        } else {
            // Get default insurance provider
            $provider_check = $conn->query("SELECT id FROM insurance_providers WHERE status = 'active' ORDER BY id ASC LIMIT 1");
            
            if ($provider_check && $provider_check->num_rows > 0) {
                $provider = $provider_check->fetch_assoc();
                $providerId = $provider['id'];
                
                // Get basic coverage type
                $coverage_check = $conn->query("SELECT * FROM insurance_coverage_types WHERE coverage_code = 'BASIC' AND is_active = 1 LIMIT 1");
                
                if ($coverage_check && $coverage_check->num_rows > 0) {
                    $coverage = $coverage_check->fetch_assoc();
                    
                    // Calculate premium (12% of booking amount for basic coverage)
                    $rentalAmount = floatval($booking['total_amount']);
                    $premiumAmount = $rentalAmount * 0.12; // 12% for basic coverage
                    
                    // Generate policy number
                    $policyNumber = 'POL-' . date('Ymd') . '-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
                    
                    // Prepare vehicle_type as lowercase string for ENUM
                    $vehicle_type_lower = strtolower($booking['vehicle_type']);
                    $vehicle_id = intval($booking['car_id']);
                    
                    // Create insurance policy
                    $policy_stmt = $conn->prepare("
                        INSERT INTO insurance_policies (
                            policy_number, provider_id, booking_id, vehicle_type, vehicle_id,
                            user_id, owner_id, coverage_type, policy_start, policy_end,
                            premium_amount, coverage_limit, deductible,
                            collision_coverage, liability_coverage, theft_coverage,
                            personal_injury_coverage, roadside_assistance,
                            status, terms_accepted, issued_at
                        ) VALUES (
                            ?, ?, ?, ?, ?,
                            ?, ?, 'basic', ?, ?,
                            ?, 100000.00, 5000.00,
                            50000.00, 100000.00, 0.00,
                            0.00, 0,
                            'active', 1, NOW()
                        )
                    ");
                    
                    $policy_stmt->bind_param(
                        "siiisiissd",
                        $policyNumber,        // s - string
                        $providerId,          // i - int
                        $booking_id,          // i - int
                        $vehicle_type_lower,  // s - string (ENUM)
                        $vehicle_id,          // i - int
                        $renter_id,           // i - int
                        $owner_id,            // i - int
                        $booking['pickup_date'],   // s - date string
                        $booking['return_date'],   // s - date string
                        $premiumAmount        // d - decimal
                    );
                    
                    if ($policy_stmt->execute()) {
                        $policy_id = $conn->insert_id;
                        
                        // Update booking with insurance info
                        $update_booking = $conn->prepare("
                            UPDATE bookings 
                            SET insurance_policy_id = ?,
                                insurance_premium = ?,
                                insurance_coverage_type = 'basic',
                                insurance_verified = 1
                            WHERE id = ?
                        ");
                        $update_booking->bind_param("idi", $policy_id, $premiumAmount, $booking_id);
                        $update_booking->execute();
                        $update_booking->close();
                        
                        $insurance_created = true;
                    } else {
                        $insurance_error = $policy_stmt->error;
                        error_log("Insurance policy insert failed for booking {$booking_id}: " . $policy_stmt->error);
                    }
                    $policy_stmt->close();
                } else {
                    $insurance_error = "No BASIC coverage type found";
                    error_log("No BASIC coverage type found for booking {$booking_id}");
                }
            } else {
                $insurance_error = "No active insurance provider";
                error_log("No active insurance provider found for booking {$booking_id}");
            }
        }
    } catch (Exception $e) {
        $insurance_error = $e->getMessage();
        error_log("Insurance policy creation exception for booking {$booking_id}: " . $e->getMessage());
    }
}

// SAVE NOTIFICATION FOR RENTER
$title_renter = "Booking Approved";
$body_renter  = "Your booking for {$vehicle_name} has been approved by the owner.";
if ($insurance_created) {
    $body_renter .= " An insurance policy has been automatically created.";
}
$type_renter  = "booking"; // Standardized type

$notif_r = $conn->prepare("
    INSERT INTO notifications (user_id, title, message, type, read_status, created_at)
    VALUES (?, ?, ?, ?, 'unread', NOW())
");
$notif_r->bind_param("isss", $renter_id, $title_renter, $body_renter, $type_renter);
$notif_r->execute();
$notif_r->close();

// SAVE NOTIFICATION FOR OWNER
$title_owner = "Booking Approved";
$body_owner  = "You approved booking #{$booking_id} for {$vehicle_name}.";
$type_owner  = "booking"; // Standardized type

$notif_o = $conn->prepare("
    INSERT INTO notifications (user_id, title, message, type, read_status, created_at)
    VALUES (?, ?, ?, ?, 'unread', NOW())
");
$notif_o->bind_param("isss", $owner_id, $title_owner, $body_owner, $type_owner);
$notif_o->execute();
$notif_o->close();

echo json_encode([
    "success" => true,
    "message" => "Booking approved successfully.",
    "booking_id" => $booking_id,
    "insurance_created" => $insurance_created,
    "insurance_error" => $insurance_error
]);
?>