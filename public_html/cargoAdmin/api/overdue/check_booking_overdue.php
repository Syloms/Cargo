<?php
/**
 * Check Specific Booking Overdue Status
 * Real-time check for a single booking - doesn't rely on cron job
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../include/db.php';

// Get booking ID
$bookingId = $_GET['booking_id'] ?? null;

if (!$bookingId) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

// Query booking with real-time overdue calculation
$sql = "SELECT 
    b.id as booking_id,
    b.user_id,
    b.owner_id,
    b.car_id,
    b.vehicle_type,
    b.status,
    b.pickup_date,
    b.pickup_time,
    b.return_date,
    b.return_time,
    b.total_amount,
    b.overdue_status as stored_overdue_status,
    b.overdue_days as stored_overdue_days,
    b.late_fee_amount as stored_late_fee,
    b.late_fee_charged,
    b.late_fee_payment_status,
    b.payment_status,
    b.overdue_detected_at,
    b.trip_started,
    (b.status = 'completed') AS trip_ended,
    
    -- Real-time calculations
    CONCAT(b.return_date, ' ', b.return_time) as scheduled_return,
    NOW() as current_time,
    TIMESTAMPDIFF(HOUR, CONCAT(b.return_date, ' ', b.return_time), NOW()) as hours_overdue_now,
    FLOOR(TIMESTAMPDIFF(HOUR, CONCAT(b.return_date, ' ', b.return_time), NOW()) / 24) as days_overdue_now,
    
    -- User details
    u.fullname as renter_name,
    u.email as renter_email,
    u.phone as renter_contact,
    o.fullname as owner_name,
    o.email as owner_email,
    o.phone as owner_contact,
    
    -- Vehicle details
    CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
    COALESCE(c.image, m.image) as vehicle_image
    
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN users o ON b.owner_id = o.id
LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
WHERE b.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $bookingId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    
    // Calculate real-time overdue status
    $hoursOverdue = max(0, (int)$row['hours_overdue_now']);
    $daysOverdue = max(0, (int)$row['days_overdue_now']);
    
    // Determine if actually overdue (past return time)
    $isOverdue = $hoursOverdue > 0;
    
    // Calculate late fee if overdue
    $lateFee = 0;
    if ($isOverdue && $hoursOverdue > 2) { // 2 hour grace period
        // Tier 1: Hours 3-6 = ₱300/hour
        // Tier 2: Hours 7-24 = ₱500/hour
        // Tier 3: 24+ hours = ₱2000/day
        
        $graceHours = 2;
        $billableHours = $hoursOverdue - $graceHours;
        
        if ($billableHours <= 4) { // Hours 3-6
            $lateFee = $billableHours * 300;
        } elseif ($billableHours <= 22) { // Hours 7-24
            $lateFee = (4 * 300) + (($billableHours - 4) * 500);
        } else { // Over 24 hours total
            // First 24h: tier1 (4h × ₱300) + tier2 (18h × ₱500) = ₱10,200
            // Beyond 24h: ₱2,000 per full day, partial hours at tier1/2 rates
            $additionalHours = $hoursOverdue - 24;
            $additionalFullDays = floor($additionalHours / 24);
            $partialHours = $additionalHours % 24;

            $lateFee = (4 * 300) + (18 * 500) + ($additionalFullDays * 2000);

            if ($partialHours > 0) {
                if ($partialHours <= 4) {
                    $lateFee += $partialHours * 300;
                } else {
                    $lateFee += (4 * 300) + (($partialHours - 4) * 500);
                }
            }
        }
    }
    
    // Always use the higher of stored vs real-time calculated fee
    // (stored may be stale if booking has been overdue longer since last cron run)
    $finalLateFee = max((float)$row['stored_late_fee'], $lateFee);
    
    // Determine severity
    $overdueStatus = 'on_time';
    if ($isOverdue) {
        $overdueStatus = $hoursOverdue > 72 ? 'severely_overdue' : 'overdue';
    }
    
    // Check if rental payment has been made
    // NOTE: In CarGO system, rental fee MUST be paid before booking approval
    // Payment flow: Create booking → Pay rental → Admin verifies → Owner approves
    // So approved bookings should always have rental paid (payment_status = verified/paid)
    $isRentalPaid = ($row['payment_status'] === 'verified' || $row['payment_status'] === 'paid');
    
    $response = [
        'success' => true,
        'is_overdue' => $isOverdue,
        'data' => [
            'booking_id' => (int)$row['booking_id'],
            'user_id' => (int)$row['user_id'],
            'owner_id' => (int)$row['owner_id'],
            'status' => $row['status'],
            'trip_started' => (bool)$row['trip_started'],
            'trip_ended' => (bool)$row['trip_ended'],
            
            // Renter details
            'renter_name' => $row['renter_name'],
            'renter_email' => $row['renter_email'],
            'renter_contact' => $row['renter_contact'],
            
            // Owner details
            'owner_name' => $row['owner_name'],
            'owner_contact' => $row['owner_contact'],
            
            // Vehicle details
            'vehicle_name' => $row['vehicle_name'],
            'vehicle_image' => $row['vehicle_image'],
            
            // Dates
            'pickup_date' => $row['pickup_date'],
            'pickup_time' => $row['pickup_time'],
            'return_date' => $row['return_date'],
            'return_time' => $row['return_time'],
            'scheduled_return' => $row['scheduled_return'],
            'current_time' => $row['current_time'],
            
            // Overdue info (real-time)
            'overdue_status' => $overdueStatus,
            'hours_overdue' => $hoursOverdue,
            'days_overdue' => $daysOverdue,
            'late_fee_amount' => $finalLateFee,
            'late_fee_charged' => (bool)$row['late_fee_charged'],
            'late_fee_payment_status' => $row['late_fee_payment_status'] ?? 'not_submitted',
            
            // Payment info
            'rental_fee' => (float)$row['total_amount'],
            'rental_payment_status' => $row['payment_status'],
            'is_rental_paid' => $isRentalPaid,
            
            // Amount due calculation:
            // - If rental is already paid (verified/paid), only late fee is due
            // - If rental is not paid (edge case), include both
            'amount_due' => $isRentalPaid ? $finalLateFee : ((float)$row['total_amount'] + $finalLateFee),
            
            // Audit trail
            'detected_at' => $row['overdue_detected_at'],
            'stored_overdue_status' => $row['stored_overdue_status'],
            'stored_overdue_days' => (int)$row['stored_overdue_days'],
            'stored_late_fee' => (float)$row['stored_late_fee'],
        ]
    ];
    
    echo json_encode($response);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
