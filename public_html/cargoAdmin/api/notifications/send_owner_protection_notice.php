<?php
/**
 * Send Owner Protection Notifications
 * Automatically notify owners about their payment protection status
 */

require_once '../../include/db.php';
require_once '../../include/notification_helper.php';

/**
 * Send daily protection status update to owners with overdue bookings
 */
function sendOwnerProtectionUpdates($conn) {
    $results = [
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'messages' => []
    ];

    // Get all overdue bookings with escrow held
    $sql = "
        SELECT 
            b.id as booking_id,
            b.owner_id,
            b.user_id as renter_id,
            b.total_amount as rental_amount,
            b.late_fee_amount,
            b.late_fee_charged,
            b.payment_status,
            b.escrow_status,
            b.overdue_days,
            b.overdue_status,
            b.return_date,
            b.return_time,
            TIMESTAMPDIFF(HOUR, CONCAT(b.return_date, ' ', b.return_time), NOW()) as hours_overdue,
            CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
            u_renter.fullname as renter_name,
            u_owner.fullname as owner_name,
            u_owner.email as owner_email
        FROM bookings b
        JOIN users u_renter ON b.user_id = u_renter.id
        JOIN users u_owner ON b.owner_id = u_owner.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.status = 'approved'
        AND CONCAT(b.return_date, ' ', b.return_time) < NOW()
        AND b.escrow_status = 'held'
        AND b.overdue_days >= 1
        ORDER BY b.overdue_days DESC
    ";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        $results['messages'][] = "Query error: " . mysqli_error($conn);
        return $results;
    }

    while ($booking = mysqli_fetch_assoc($result)) {
        // Check if we already sent notification today
        $checkSql = "SELECT id FROM notifications 
                     WHERE user_id = ? 
                     AND title LIKE '%Payment Protected%'
                     AND DATE(created_at) = CURDATE()
                     AND JSON_EXTRACT(metadata, '$.booking_id') = ?";
        
        $stmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($stmt, "ii", $booking['owner_id'], $booking['booking_id']);
        mysqli_stmt_execute($stmt);
        $checkResult = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($checkResult) > 0) {
            $results['skipped']++;
            continue; // Already sent today
        }

        // Determine notification message based on severity
        $message = buildProtectionMessage($booking);
        $title = "Payment Protected: Day " . $booking['overdue_days'];

        // Send notification
        $notifSql = "INSERT INTO notifications 
                     (user_id, title, message, type, metadata, created_at) 
                     VALUES (?, ?, ?, 'payment_protection', ?, NOW())";
        
        $metadata = json_encode([
            'booking_id' => $booking['booking_id'],
            'rental_amount' => $booking['rental_amount'],
            'late_fee' => $booking['late_fee_amount'],
            'days_overdue' => $booking['overdue_days'],
            'escrow_status' => $booking['escrow_status']
        ]);

        $stmt = mysqli_prepare($conn, $notifSql);
        mysqli_stmt_bind_param($stmt, "isss", $booking['owner_id'], $title, $message, $metadata);
        
        if (mysqli_stmt_execute($stmt)) {
            $results['success']++;
            $results['messages'][] = "Sent protection notice to owner #{$booking['owner_id']} for booking #{$booking['booking_id']}";
        } else {
            $results['failed']++;
            $results['messages'][] = "Failed to send to owner #{$booking['owner_id']}: " . mysqli_error($conn);
        }
    }

    return $results;
}

/**
 * Build protection message based on booking status
 */
function buildProtectionMessage($booking) {
    $rentalAmount = number_format($booking['rental_amount'], 2);
    $lateFee = number_format($booking['late_fee_amount'], 2);
    $totalOwed = number_format($booking['rental_amount'] + $booking['late_fee_amount'], 2);
    $days = $booking['overdue_days'];
    
    // Base message - always include escrow protection
    $message = "🛡️ Your Payment is Protected\n\n";
    $message .= "Booking: {$booking['vehicle_name']}\n";
    $message .= "Days Overdue: {$days} day(s)\n\n";
    
    $message .= "💰 Payment Status:\n";
    $message .= "• Rental Amount: ₱{$rentalAmount} ✓ (Secured in Escrow)\n";
    $message .= "• Late Fees Owed: ₱{$lateFee}\n";
    
    if ($booking['late_fee_charged']) {
        $message .= "  └─ Status: Paid by renter ✓\n";
    } else if ($booking['payment_status'] === 'pending') {
        $message .= "  └─ Status: Payment submitted, awaiting verification\n";
    } else {
        $message .= "  └─ Status: Awaiting renter payment\n";
    }
    
    $message .= "\nTotal You're Owed: ₱{$totalOwed}\n\n";
    
    // Severity-based messaging
    if ($days >= 7) {
        $message .= "⚠️ CRITICAL: Vehicle overdue for {$days} days!\n";
        $message .= "Admin has been notified for intervention.\n";
        $message .= "Your rental payment (₱{$rentalAmount}) remains secure in escrow.\n";
        $message .= "Contact admin if you need assistance.";
    } else if ($days >= 3) {
        $message .= "⚠️ Vehicle overdue for {$days} days.\n";
        $message .= "Your rental payment (₱{$rentalAmount}) is protected in escrow.\n";
        $message .= "Late fees continue to accumulate. We're monitoring this booking.";
    } else {
        $message .= "ℹ️ Your rental payment is secure in escrow.\n";
        $message .= "We're tracking this overdue booking and will notify you of any updates.";
    }
    
    return $message;
}

/**
 * Send critical escalation notice
 */
function sendCriticalEscalation($conn, $bookingId) {
    $sql = "
        SELECT 
            b.owner_id,
            b.total_amount,
            b.late_fee_amount,
            b.overdue_days,
            CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name
        FROM bookings b
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) return false;
    
    $title = "🚨 Critical: Admin Intervention Required";
    $message = "Your vehicle ({$booking['vehicle_name']}) has been overdue for {$booking['overdue_days']} days.\n\n";
    $message .= "✓ Your rental payment (₱" . number_format($booking['total_amount'], 2) . ") is PROTECTED in escrow.\n";
    $message .= "✓ Admin team has been alerted and will contact you shortly.\n";
    $message .= "✓ We will assist with vehicle recovery if needed.\n\n";
    $message .= "Your payment is guaranteed. Please contact admin for immediate assistance.";
    
    $notifSql = "INSERT INTO notifications 
                 (user_id, title, message, type, created_at) 
                 VALUES (?, ?, ?, 'critical_alert', NOW())";
    
    $stmt = mysqli_prepare($conn, $notifSql);
    mysqli_stmt_bind_param($stmt, "iss", $booking['owner_id'], $title, $message);
    
    return mysqli_stmt_execute($stmt);
}

// Run if called directly (for cron)
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    echo "=== Owner Protection Notification System ===\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
    
    $results = sendOwnerProtectionUpdates($conn);
    
    echo "Results:\n";
    echo "- Sent: {$results['success']}\n";
    echo "- Failed: {$results['failed']}\n";
    echo "- Skipped: {$results['skipped']}\n\n";
    
    if (!empty($results['messages'])) {
        echo "Messages:\n";
        foreach ($results['messages'] as $msg) {
            echo "  - $msg\n";
        }
    }
    
    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
    
    mysqli_close($conn);
}
?>
