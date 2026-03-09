<?php
// api/end_trip.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$booking_id = $_POST['booking_id'] ?? null;
$owner_id = $_POST['owner_id'] ?? null;

if (!$booking_id || !$owner_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // First, check if booking exists and get its current status for detailed error reporting
    $detailCheckSql = "SELECT id, user_id, owner_id, status, pickup_date, return_date 
                       FROM bookings 
                       WHERE id = ?";
    $stmt = $conn->prepare($detailCheckSql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $detailResult = $stmt->get_result();

    if ($detailResult->num_rows === 0) {
        $stmt->close();
        echo json_encode([
            'success' => false, 
            'message' => 'Booking not found',
            'error_code' => 'BOOKING_NOT_FOUND',
            'booking_id' => $booking_id
        ]);
        exit;
    }

    $bookingDetail = $detailResult->fetch_assoc();
    $stmt->close();

    // Verify ownership
    if ($bookingDetail['owner_id'] != $owner_id) {
        echo json_encode([
            'success' => false, 
            'message' => 'You do not own this booking',
            'error_code' => 'UNAUTHORIZED',
            'booking_id' => $booking_id,
            'expected_owner_id' => $bookingDetail['owner_id'],
            'provided_owner_id' => $owner_id
        ]);
        exit;
    }

    // Check if pickup date has started
    if (strtotime($bookingDetail['pickup_date']) > strtotime(date('Y-m-d'))) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot end trip that has not started yet',
            'error_code' => 'TRIP_NOT_STARTED',
            'booking_id' => $booking_id,
            'pickup_date' => $bookingDetail['pickup_date'],
            'current_date' => date('Y-m-d')
        ]);
        exit;
    }

    // Check booking status - allow 'approved' or 'ongoing' status to be completed
    $allowedStatuses = ['approved', 'ongoing'];
    if (!in_array($bookingDetail['status'], $allowedStatuses)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Booking cannot be completed with current status',
            'error_code' => 'INVALID_STATUS',
            'booking_id' => $booking_id,
            'current_status' => $bookingDetail['status'],
            'allowed_statuses' => $allowedStatuses,
            'hint' => $bookingDetail['status'] === 'completed' ? 'This trip has already been completed' : 
                      ($bookingDetail['status'] === 'cancelled' ? 'This booking was cancelled' :
                      ($bookingDetail['status'] === 'rejected' ? 'This booking was rejected' : 
                      'Status must be approved or ongoing to complete trip'))
        ]);
        exit;
    }

    // Verify ownership and that booking can be completed
    // IMPORTANT: Also check that odometer_end is recorded before completing trip
    $checkSql = "SELECT 
                    b.id, b.user_id, b.return_date, b.status, 
                    b.odometer_start, b.odometer_end, b.odometer_end_photo,
                    COALESCE(c.has_unlimited_mileage, m.has_unlimited_mileage) AS has_unlimited_mileage
                 FROM bookings b
                 LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
                 LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
                 WHERE b.id = ? AND b.owner_id = ? 
                 AND b.status IN ('approved', 'ongoing') 
                 AND b.pickup_date <= CURDATE()";
                 
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ii", $booking_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode([
            'success' => false, 
            'message' => 'Booking validation failed unexpectedly',
            'error_code' => 'VALIDATION_ERROR',
            'booking_id' => $booking_id
        ]);
        exit;
    }

    $booking = $result->fetch_assoc();
    $userId = $booking['user_id'];
    $returnDateTime = $booking['return_date'];
    $stmt->close();

    // ✅ Validate ending odometer only for LIMITED mileage vehicles
    $isUnlimitedMileage = !empty($booking['has_unlimited_mileage']) && intval($booking['has_unlimited_mileage']) === 1;
    if (!$isUnlimitedMileage && empty($booking['odometer_end'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot complete trip without recording ending odometer reading',
            'error_code' => 'ODOMETER_END_REQUIRED',
            'booking_id' => $booking_id,
            'odometer_start' => $booking['odometer_start'],
            'hint' => 'Please record the ending odometer reading before completing the trip'
        ]);
        exit;
    }

    // Check if rental was overdue and calculate late fees
    $hoursOverdue = 0;
    $lateFee = 0;
    $isOverdue = false;

    $returnTimestamp = strtotime($returnDateTime);
    $currentTimestamp = time();

    if ($currentTimestamp > $returnTimestamp) {
        $hoursOverdue = floor(($currentTimestamp - $returnTimestamp) / 3600);
        $isOverdue = true;
        
        // Calculate late fee (same logic as cron job)
        $lateFee = calculateLateFee($hoursOverdue, $conn);
    }

    // Update booking to completed with late fee info
    $updateSql = "UPDATE bookings 
                  SET status = 'completed', 
                      late_fee_amount = ?,
                      late_fee_charged = 1,
                      updated_at = NOW() 
                  WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("di", $lateFee, $booking_id);

    if ($stmt->execute()) {
        $stmt->close();
        
        // Log if late fee was charged
        if ($isOverdue && $lateFee > 0) {
            // Check if overdue_logs table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'overdue_logs'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $logSql = "INSERT INTO overdue_logs 
                          (booking_id, days_overdue, hours_overdue, late_fee_charged, action_taken, notes, created_at)
                          VALUES (?, ?, ?, ?, 'resolved', 'Trip completed with late fee', NOW())";
                $logStmt = $conn->prepare($logSql);
                $daysOverdue = floor($hoursOverdue / 24);
                $logStmt->bind_param("iiid", $booking_id, $daysOverdue, $hoursOverdue, $lateFee);
                $logStmt->execute();
                $logStmt->close();
            }
        }
        
        // Send notification to renter
        $notifSql = "INSERT INTO notifications (user_id, title, message, created_at) 
                     VALUES (?, ?, ?, NOW())";
        $notifStmt = $conn->prepare($notifSql);
        $title = "Trip Completed";
        
        if ($isOverdue && $lateFee > 0) {
            $message = "Your rental for booking #{$booking_id} has been completed. Late fee charged: " . number_format($lateFee, 2) . " PHP (returned {$hoursOverdue} hours late).";
        } else {
            $message = "Your rental for booking #{$booking_id} has been completed. Thank you!";
        }
        
        $notifStmt->bind_param("sss", $userId, $title, $message);
        $notifStmt->execute();
        $notifStmt->close();
        
        $response = [
            'success' => true,
            'message' => 'Trip marked as completed successfully',
            'was_overdue' => $isOverdue,
            'hours_overdue' => $hoursOverdue,
            'late_fee' => $lateFee,
            'odometer_end_photo_missing' => empty($booking['odometer_end_photo'])
        ];
        
        if ($isOverdue && $lateFee > 0) {
            $response['warning'] = "Vehicle was returned {$hoursOverdue} hours late. Late fee of " . number_format($lateFee, 2) . " PHP has been added to the booking.";
        }
        
        echo json_encode($response);
    } else {
        $stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to complete trip: ' . $conn->error
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

// Helper function for late fee calculation
function calculateLateFee($hoursLate, $conn) {
    // Get system settings for late fee rates
    $settings = getLateFeeSettings($conn);
    
    $graceHours = (int)$settings['grace_hours'];
    
    if ($hoursLate <= $graceHours) {
        return 0;
    }
    
    $tier1Rate = (float)$settings['tier1_rate'];
    $tier2Rate = (float)$settings['tier2_rate'];
    $tier3Rate = (float)$settings['tier3_rate'];
    
    $fee = 0;
    
    if ($hoursLate > $graceHours && $hoursLate <= 6) {
        $fee = ($hoursLate - $graceHours) * $tier1Rate;
    } elseif ($hoursLate > 6 && $hoursLate < 24) {
        $tier1Hours = 6 - $graceHours;
        $fee = ($tier1Hours * $tier1Rate) + (($hoursLate - 6) * $tier2Rate);
    } else {
        $daysLate = floor($hoursLate / 24);
        $remainingHours = $hoursLate % 24;
        
        $tier1Hours = 6 - $graceHours;
        $fee = ($tier1Hours * $tier1Rate) + (18 * $tier2Rate) + ($daysLate * $tier3Rate);
        
        if ($remainingHours > $graceHours) {
            if ($remainingHours <= 6) {
                $fee += ($remainingHours - $graceHours) * $tier1Rate;
            } else {
                $fee += ($tier1Hours * $tier1Rate) + (($remainingHours - 6) * $tier2Rate);
            }
        }
    }
    
    return round($fee, 2);
}

// Get late fee settings from database
function getLateFeeSettings($conn) {
    $defaults = [
        'grace_hours' => 2,
        'tier1_rate' => 300,
        'tier2_rate' => 500,
        'tier3_rate' => 2000
    ];
    
    // Check if settings table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return $defaults;
    }
    
    $sql = "SELECT setting_key, setting_value FROM settings 
            WHERE setting_key IN ('late_fee_grace_hours', 'late_fee_tier1_rate', 
                                  'late_fee_tier2_rate', 'late_fee_tier3_rate')";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $key = str_replace('late_fee_', '', $row['setting_key']);
            $key = str_replace('_hours', '', $key);
            $defaults[$key] = $row['setting_value'];
        }
    }
    
    return $defaults;
}
?>
