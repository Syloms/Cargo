<?php
/**
 * ============================================================================
 * AUTOMATED OVERDUE RENTAL DETECTION
 * ============================================================================
 * Run every hour via cron job:
 * 0 * * * * php /path/to/cargoAdmin/cron/detect_overdue_rentals.php
 * 
 * Or manually: php detect_overdue_rentals.php
 * ============================================================================
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/../include/db.php';

echo "=================================================\n";
echo "Overdue Rental Detection System\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n\n";

// Get system settings
$settings = getSystemSettings($conn);

if (!$settings['late_fee_enabled']) {
    echo "❌ Late fee system is disabled in settings.\n";
    exit;
}

// Find overdue bookings
// ⚠️ CRITICAL FIX: Only adetect overdue for ONGOING trips, not completed ones
// Completed trips should have late fees calculated when they END, not by cron
$sql = "SELECT 
    b.*,
    TIMESTAMPDIFF(HOUR, CONCAT(b.return_date, ' ', b.return_time), NOW()) as hours_overdue,
    DATEDIFF(NOW(), b.return_date) as days_overdue_calc,
    u.fullname as renter_name,
    u.email as renter_email,
    u.phone as renter_contact,
    o.fullname as owner_name,
    o.email as owner_email,
    o.phone as owner_contact,
    CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN users o ON b.owner_id = o.id
LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
WHERE b.status IN ('approved', 'ongoing')
AND b.trip_started = 1
AND CONCAT(b.return_date, ' ', b.return_time) < NOW()
ORDER BY hours_overdue DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo "❌ Database error: " . mysqli_error($conn) . "\n";
    exit;
}

$totalProcessed = 0;
$totalFees = 0;

while ($booking = mysqli_fetch_assoc($result)) {
    $hoursOverdue = (int)$booking['hours_overdue'];
    $daysOverdue = floor($hoursOverdue / 24);
    
    // Skip if within grace period
    if ($hoursOverdue <= $settings['grace_hours']) {
        continue;
    }
    
    echo "\n📋 Processing Booking #{$booking['id']}\n";
    echo "   Vehicle: {$booking['vehicle_name']}\n";
    echo "   Renter: {$booking['renter_name']}\n";
    echo "   Return was: {$booking['return_date']} {$booking['return_time']}\n";
    echo "   Hours overdue: {$hoursOverdue}\n";
    
    // Calculate late fee
    $lateFee = calculateLateFee($hoursOverdue, $settings);
    echo "   Late fee: ₱" . number_format($lateFee, 2) . "\n";
    
    // Determine overdue severity
    $overdueStatus = determineOverdueStatus($hoursOverdue);
    echo "   Status: {$overdueStatus}\n";
    
    // Update booking with overdue info
    $updateSuccess = updateBookingOverdueStatus(
        $conn, 
        $booking['id'], 
        $overdueStatus, 
        $daysOverdue, 
        $lateFee
    );
    
    if ($updateSuccess) {
        echo "   ✅ Updated booking status\n";
        
        // Log the overdue event
        logOverdueEvent($conn, $booking['id'], $daysOverdue, $hoursOverdue, $lateFee, 'notification');
        
        // Send notifications based on overdue duration
        if ($settings['notification_enabled']) {
            sendOverdueNotifications($conn, $booking, $hoursOverdue, $daysOverdue, $lateFee);
        }
        
        $totalProcessed++;
        $totalFees += $lateFee;
    } else {
        echo "   ❌ Failed to update booking\n";
    }
}

echo "\n=================================================\n";
echo "Summary:\n";
echo "Total bookings processed: {$totalProcessed}\n";
echo "Total late fees calculated: ₱" . number_format($totalFees, 2) . "\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n";

mysqli_close($conn);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function getSystemSettings($conn) {
    $defaults = [
        'late_fee_enabled' => true,
        'grace_hours' => 2,
        'tier1_rate' => 300,
        'tier2_rate' => 500,
        'tier3_rate' => 2000,
        'notification_enabled' => true
    ];
    
    $sql = "SELECT setting_key, setting_value FROM settings 
            WHERE setting_key IN ('late_fee_enabled', 'late_fee_grace_hours', 
                                  'late_fee_tier1_rate', 'late_fee_tier2_rate', 
                                  'late_fee_tier3_rate', 'overdue_notification_enabled')";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $key = str_replace(['late_fee_', 'overdue_notification_'], ['', 'notification_'], $row['setting_key']);
            $key = str_replace('_hours', '', $key);
            $defaults[$key] = $row['setting_value'];
        }
    }
    
    return $defaults;
}

function calculateLateFee($hoursLate, $settings) {
    $graceHours = (int)$settings['grace_hours'];
    
    if ($hoursLate <= $graceHours) {
        return 0;
    }
    
    $tier1Rate = (float)$settings['tier1_rate'];
    $tier2Rate = (float)$settings['tier2_rate'];
    $tier3Rate = (float)$settings['tier3_rate'];
    
    $fee = 0;
    
    // Tier 1: Grace+1 to 6 hours (e.g., 3-6 hours if grace is 2)
    if ($hoursLate > $graceHours && $hoursLate <= 6) {
        $fee = ($hoursLate - $graceHours) * $tier1Rate;
    }
    // Tier 2: 6-24 hours
    elseif ($hoursLate > 6 && $hoursLate < 24) {
        $tier1Hours = 6 - $graceHours;
        $fee = ($tier1Hours * $tier1Rate) + (($hoursLate - 6) * $tier2Rate);
    }
    // Tier 3: 24+ hours
    // First 24h: tier1 (4h) + tier2 (18h) = ₱10,200
    // Beyond 24h: ₱2,000 per additional full day + partial hours at tier1/2 rates
    else {
        $tier1Hours = 6 - $graceHours;
        $additionalHours = $hoursLate - 24;
        $additionalFullDays = floor($additionalHours / 24);
        $partialHours = $additionalHours % 24;

        $fee = ($tier1Hours * $tier1Rate) + (18 * $tier2Rate) + ($additionalFullDays * $tier3Rate);

        if ($partialHours > 0) {
            if ($partialHours <= $tier1Hours) {
                $fee += $partialHours * $tier1Rate;
            } else {
                $fee += ($tier1Hours * $tier1Rate) + (($partialHours - $tier1Hours) * $tier2Rate);
            }
        }
    }
    
    return round($fee, 2);
}

function determineOverdueStatus($hoursOverdue) {
    if ($hoursOverdue < 48) {
        return 'overdue';
    } else {
        return 'severely_overdue';
    }
}

function updateBookingOverdueStatus($conn, $bookingId, $status, $days, $fee) {
    $sql = "UPDATE bookings 
            SET overdue_status = ?,
                overdue_days = ?,
                late_fee_amount = ?,
                overdue_detected_at = COALESCE(overdue_detected_at, NOW())
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "sidi", $status, $days, $fee, $bookingId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

function logOverdueEvent($conn, $bookingId, $days, $hours, $fee, $action) {
    $sql = "INSERT INTO overdue_logs 
            (booking_id, days_overdue, hours_overdue, late_fee_charged, action_taken, notification_sent, notes, created_at)
            VALUES (?, ?, ?, ?, ?, 1, 'Automated detection', NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "iiids", $bookingId, $days, $hours, $fee, $action);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

function sendOverdueNotifications($conn, $booking, $hours, $days, $fee) {
    $notifications = [];
    
    // Initial notification (first time overdue detected - 3 hours)
    if ($hours >= 3 && $hours < 4) {
        $notifications[] = [
            'recipient' => 'renter',
            'severity' => 'initial',
            'title' => '⏰ Vehicle Return Overdue',
            'message' => "Your rental for booking #{$booking['id']} ({$booking['vehicle_name']}) is now overdue. Late fee: ₱" . number_format($fee, 2) . ". Please return the vehicle immediately to avoid additional charges."
        ];
        
        $notifications[] = [
            'recipient' => 'owner',
            'severity' => 'initial',
            'title' => '⏰ Renter Overdue',
            'message' => "Booking #{$booking['id']} ({$booking['vehicle_name']}) is overdue. Renter: {$booking['renter_name']}. Current late fee: ₱" . number_format($fee, 2) . "."
        ];
    }
    
    // Daily reminders (at exactly 24, 48, 72 hours, etc.)
    if ($hours % 24 == 0 && $days >= 1) {
        $notifications[] = [
            'recipient' => 'renter',
            'severity' => 'daily',
            'title' => "⚠️ Day {$days} Overdue Reminder",
            'message' => "URGENT: Booking #{$booking['id']} is {$days} day(s) overdue. Late fee has reached ₱" . number_format($fee, 2) . ". Contact owner immediately: {$booking['owner_contact']}."
        ];
        
        $notifications[] = [
            'recipient' => 'owner',
            'severity' => 'daily',
            'title' => "⚠️ Overdue Day {$days} Update",
            'message' => "Booking #{$booking['id']} remains {$days} day(s) overdue. Late fee: ₱" . number_format($fee, 2) . ". Contact renter: {$booking['renter_contact']}."
        ];
    }
    
    // Severe notification (3+ days)
    if ($days == 3 && $hours >= 72 && $hours < 73) {
        $notifications[] = [
            'recipient' => 'renter',
            'severity' => 'severe',
            'title' => '🚨 URGENT: Severely Overdue',
            'message' => "FINAL WARNING: Booking #{$booking['id']} is severely overdue ({$days} days). Late fee: ₱" . number_format($fee, 2) . ". Failure to return may result in police report and legal action."
        ];
        
        $notifications[] = [
            'recipient' => 'owner',
            'severity' => 'severe',
            'title' => '🚨 Vehicle Severely Overdue',
            'message' => "Booking #{$booking['id']} is severely overdue ({$days} days). You may now file a police report for the missing vehicle. Contact support for assistance."
        ];
        
        // Notify admin
        $notifications[] = [
            'recipient' => 'admin',
            'severity' => 'severe',
            'title' => '🚨 Severely Overdue Case',
            'message' => "Booking #{$booking['id']} is {$days} days overdue. Manual intervention may be required. Late fee: ₱" . number_format($fee, 2) . "."
        ];
    }
    
    // Send all notifications
    foreach ($notifications as $notif) {
        sendNotification($conn, $booking, $notif);
    }
    
    if (count($notifications) > 0) {
        echo "   📧 Sent " . count($notifications) . " notification(s)\n";
    }
}

function sendNotification($conn, $booking, $notif) {
    // Determine user ID
    if ($notif['recipient'] == 'renter') {
        $userId = $booking['user_id'];
    } elseif ($notif['recipient'] == 'owner') {
        $userId = $booking['owner_id'];
    } else {
        // Send to all admins
        $adminSql = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
        $adminResult = mysqli_query($GLOBALS['conn'], $adminSql);
        if ($adminRow = mysqli_fetch_assoc($adminResult)) {
            $userId = $adminRow['id'];
        } else {
            return; // No admin found
        }
    }
    
    $sql = "INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, ?, ?, 'alert', NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "iss", $userId, $notif['title'], $notif['message']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return true;
}

?>
