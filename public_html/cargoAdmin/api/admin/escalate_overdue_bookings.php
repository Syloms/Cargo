<?php
/**
 * Admin Escalation System for Overdue Bookings
 * Automatically flags critical cases for admin intervention
 */

require_once '../../include/db.php';
require_once '../../include/notification_helper.php';

/**
 * Check and escalate overdue bookings based on severity
 */
function checkEscalations($conn) {
    $results = [
        'day3_escalations' => 0,
        'day7_critical' => 0,
        'day10_guarantee' => 0,
        'messages' => []
    ];

    // Day 3: Admin notification (monitoring)
    $results['day3_escalations'] = escalateDay3Bookings($conn);
    
    // Day 7: Critical intervention required
    $results['day7_critical'] = escalateDay7Bookings($conn);
    
    // Day 10: Guarantee payment triggered
    $results['day10_guarantee'] = escalateDay10Bookings($conn);
    
    return $results;
}

/**
 * Day 3: Escalate to admin for monitoring
 */
function escalateDay3Bookings($conn) {
    $count = 0;
    
    $sql = "
        SELECT 
            b.id,
            b.owner_id,
            b.user_id as renter_id,
            b.total_amount,
            b.late_fee_amount,
            b.overdue_days,
            CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
            u_renter.fullname as renter_name,
            u_renter.phone as renter_phone,
            u_owner.fullname as owner_name
        FROM bookings b
        JOIN users u_renter ON b.user_id = u_renter.id
        JOIN users u_owner ON b.owner_id = u_owner.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.status = 'approved'
        AND b.overdue_days = 3
        AND b.escrow_status = 'held'
        AND NOT EXISTS (
            SELECT 1 FROM admin_notifications 
            WHERE related_id = b.id 
            AND type = 'overdue_escalation_day3'
            AND DATE(created_at) = CURDATE()
        )
    ";
    
    $result = mysqli_query($conn, $sql);
    
    while ($booking = mysqli_fetch_assoc($result)) {
        // Create admin notification
        $title = "⚠️ Day 3 Escalation: Booking #{$booking['id']}";
        $message = "Vehicle: {$booking['vehicle_name']}\n";
        $message .= "Owner: {$booking['owner_name']}\n";
        $message .= "Renter: {$booking['renter_name']} ({$booking['renter_phone']})\n";
        $message .= "Rental: ₱" . number_format($booking['total_amount'], 2) . " (Protected in escrow)\n";
        $message .= "Late Fee: ₱" . number_format($booking['late_fee_amount'], 2) . "\n\n";
        $message .= "Action: Monitor and prepare for intervention if not returned soon.";
        
        $notifSql = "INSERT INTO admin_notifications 
                     (admin_id, title, message, type, related_id, priority, created_at) 
                     VALUES (1, ?, ?, 'overdue_escalation_day3', ?, 'medium', NOW())";
        
        $stmt = mysqli_prepare($conn, $notifSql);
        mysqli_stmt_bind_param($stmt, "ssi", $title, $message, $booking['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $count++;
            
            // Also notify owner
            $ownerMsg = "Your booking has been escalated to admin team for monitoring. ";
            $ownerMsg .= "Your rental payment (₱" . number_format($booking['total_amount'], 2) . ") remains secure in escrow.";
            
            $ownerNotifSql = "INSERT INTO notifications 
                              (user_id, title, message, type, created_at) 
                              VALUES (?, 'Admin Team Monitoring Your Booking', ?, 'escalation', NOW())";
            
            $stmt = mysqli_prepare($conn, $ownerNotifSql);
            mysqli_stmt_bind_param($stmt, "is", $booking['owner_id'], $ownerMsg);
            mysqli_stmt_execute($stmt);
        }
    }
    
    return $count;
}

/**
 * Day 7: Critical intervention required
 */
function escalateDay7Bookings($conn) {
    $count = 0;
    
    $sql = "
        SELECT 
            b.id,
            b.owner_id,
            b.user_id as renter_id,
            b.total_amount,
            b.late_fee_amount,
            b.overdue_days,
            CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
            u_renter.fullname as renter_name,
            u_renter.phone as renter_phone,
            u_renter.email as renter_email,
            u_owner.fullname as owner_name,
            u_owner.phone as owner_phone
        FROM bookings b
        JOIN users u_renter ON b.user_id = u_renter.id
        JOIN users u_owner ON b.owner_id = u_owner.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.status = 'approved'
        AND b.overdue_days = 7
        AND b.escrow_status = 'held'
        AND NOT EXISTS (
            SELECT 1 FROM admin_notifications 
            WHERE related_id = b.id 
            AND type = 'overdue_critical_day7'
            AND DATE(created_at) = CURDATE()
        )
    ";
    
    $result = mysqli_query($conn, $sql);
    
    while ($booking = mysqli_fetch_assoc($result)) {
        // Create critical admin notification
        $title = "🚨 CRITICAL Day 7: Booking #{$booking['id']} - Action Required";
        $message = "CRITICAL OVERDUE SITUATION\n\n";
        $message .= "Vehicle: {$booking['vehicle_name']}\n";
        $message .= "Owner: {$booking['owner_name']} ({$booking['owner_phone']})\n";
        $message .= "Renter: {$booking['renter_name']}\n";
        $message .= "  Phone: {$booking['renter_phone']}\n";
        $message .= "  Email: {$booking['renter_email']}\n\n";
        $message .= "Financial:\n";
        $message .= "  Rental: ₱" . number_format($booking['total_amount'], 2) . " (PROTECTED IN ESCROW)\n";
        $message .= "  Late Fee: ₱" . number_format($booking['late_fee_amount'], 2) . "\n\n";
        $message .= "REQUIRED ACTIONS:\n";
        $message .= "1. Contact renter immediately\n";
        $message .= "2. Contact owner to verify vehicle status\n";
        $message .= "3. Prepare legal action notice\n";
        $message .= "4. Consider police report if necessary\n";
        $message .= "5. Activate guarantee payment at Day 10 if not resolved";
        
        $notifSql = "INSERT INTO admin_notifications 
                     (admin_id, title, message, type, related_id, priority, created_at) 
                     VALUES (1, ?, ?, 'overdue_critical_day7', ?, 'critical', NOW())";
        
        $stmt = mysqli_prepare($conn, $notifSql);
        mysqli_stmt_bind_param($stmt, "ssi", $title, $message, $booking['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $count++;
            
            // Send critical notice to owner
            require_once '../notifications/send_owner_protection_notice.php';
            sendCriticalEscalation($conn, $booking['id']);
            
            // Send final warning to renter
            $renterMsg = "🚨 FINAL WARNING: Your rental is critically overdue (7 days).\n\n";
            $renterMsg .= "Vehicle: {$booking['vehicle_name']}\n";
            $renterMsg .= "Late Fee: ₱" . number_format($booking['late_fee_amount'], 2) . "\n\n";
            $renterMsg .= "IMMEDIATE ACTION REQUIRED:\n";
            $renterMsg .= "1. Return vehicle today\n";
            $renterMsg .= "2. Pay all late fees\n";
            $renterMsg .= "3. Contact admin immediately\n\n";
            $renterMsg .= "Failure to comply will result in:\n";
            $renterMsg .= "- Legal action\n";
            $renterMsg .= "- Police report\n";
            $renterMsg .= "- Permanent ban from platform\n";
            $renterMsg .= "- Criminal charges if applicable";
            
            $renterNotifSql = "INSERT INTO notifications 
                               (user_id, title, message, type, created_at) 
                               VALUES (?, '🚨 CRITICAL: Return Vehicle Immediately', ?, 'critical_warning', NOW())";
            
            $stmt = mysqli_prepare($conn, $renterNotifSql);
            mysqli_stmt_bind_param($stmt, "is", $booking['renter_id'], $renterMsg);
            mysqli_stmt_execute($stmt);
        }
    }
    
    return $count;
}

/**
 * Day 10: Trigger guarantee payment to owner
 */
function escalateDay10Bookings($conn) {
    $count = 0;
    
    $sql = "
        SELECT 
            b.id,
            b.owner_id,
            b.total_amount,
            b.late_fee_amount,
            b.overdue_days,
            CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
            u_owner.fullname as owner_name
        FROM bookings b
        JOIN users u_owner ON b.owner_id = u_owner.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.status = 'approved'
        AND b.overdue_days >= 10
        AND b.escrow_status = 'held'
        AND NOT EXISTS (
            SELECT 1 FROM admin_notifications 
            WHERE related_id = b.id 
            AND type = 'guarantee_payment_triggered'
        )
    ";
    
    $result = mysqli_query($conn, $sql);
    
    while ($booking = mysqli_fetch_assoc($result)) {
        $totalOwed = $booking['total_amount'] + $booking['late_fee_amount'];
        
        // Create guarantee payment notification
        $title = "💰 Guarantee Payment Required: Booking #{$booking['id']}";
        $message = "OWNER GUARANTEE PAYMENT TRIGGERED\n\n";
        $message .= "Vehicle: {$booking['vehicle_name']}\n";
        $message .= "Owner: {$booking['owner_name']}\n";
        $message .= "Days Overdue: {$booking['overdue_days']}\n\n";
        $message .= "PAYMENT DUE TO OWNER:\n";
        $message .= "  Rental (from escrow): ₱" . number_format($booking['total_amount'], 2) . "\n";
        $message .= "  Late Fee (platform cover): ₱" . number_format($booking['late_fee_amount'], 2) . "\n";
        $message .= "  TOTAL: ₱" . number_format($totalOwed, 2) . "\n\n";
        $message .= "ACTION:\n";
        $message .= "1. Release escrow to owner\n";
        $message .= "2. Process late fee payment from guarantee fund\n";
        $message .= "3. Continue pursuing renter for recovery\n";
        $message .= "4. File police report if not done\n";
        $message .= "5. Initiate legal proceedings";
        
        $notifSql = "INSERT INTO admin_notifications 
                     (admin_id, title, message, type, related_id, priority, created_at) 
                     VALUES (1, ?, ?, 'guarantee_payment_triggered', ?, 'urgent', NOW())";
        
        $stmt = mysqli_prepare($conn, $notifSql);
        mysqli_stmt_bind_param($stmt, "ssi", $title, $message, $booking['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $count++;
            
            // Notify owner they'll be compensated
            $ownerMsg = "🎯 PAYMENT GUARANTEE ACTIVATED\n\n";
            $ownerMsg .= "Your booking has reached Day {$booking['overdue_days']}.\n\n";
            $ownerMsg .= "CarGo guarantee system has been activated:\n";
            $ownerMsg .= "✓ Rental payment: ₱" . number_format($booking['total_amount'], 2) . " (from escrow)\n";
            $ownerMsg .= "✓ Late fees: ₱" . number_format($booking['late_fee_amount'], 2) . " (covered by platform)\n\n";
            $ownerMsg .= "TOTAL YOU WILL RECEIVE: ₱" . number_format($totalOwed, 2) . "\n\n";
            $ownerMsg .= "Admin will contact you within 24 hours to process your payment.\n";
            $ownerMsg .= "We will continue pursuing the renter for vehicle recovery.";
            
            $ownerNotifSql = "INSERT INTO notifications 
                              (user_id, title, message, type, created_at) 
                              VALUES (?, '💰 Guarantee Payment Activated', ?, 'guarantee_payment', NOW())";
            
            $stmt = mysqli_prepare($conn, $ownerNotifSql);
            mysqli_stmt_bind_param($stmt, "is", $booking['owner_id'], $ownerMsg);
            mysqli_stmt_execute($stmt);
        }
    }
    
    return $count;
}

// Run if called directly (for cron)
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    echo "=== Admin Escalation System ===\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
    
    $results = checkEscalations($conn);
    
    echo "Escalation Results:\n";
    echo "- Day 3 (Monitoring): {$results['day3_escalations']}\n";
    echo "- Day 7 (Critical): {$results['day7_critical']}\n";
    echo "- Day 10 (Guarantee): {$results['day10_guarantee']}\n";
    
    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
    
    mysqli_close($conn);
} else {
    // Return JSON if called via web
    header('Content-Type: application/json');
    echo json_encode(checkEscalations($conn));
    mysqli_close($conn);
}
?>
