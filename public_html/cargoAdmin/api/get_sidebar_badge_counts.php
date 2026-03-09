<?php
/**
 * API Endpoint: Get Sidebar Badge Counts
 * Returns real-time counts for all sidebar badges
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../include/db.php';

try {
    $badges = [];

    // 1. User Management - Pending Verifications
    $user_verif_query = "SELECT COUNT(*) as count FROM user_verifications WHERE status = 'pending'";
    $user_verif_result = mysqli_query($conn, $user_verif_query);
    $badges['users'] = $user_verif_result ? mysqli_fetch_assoc($user_verif_result)['count'] : 0;

    // 2. Car Listings - Pending Approval
    $car_pending_query = "SELECT COUNT(*) as count FROM cars WHERE status = 'pending'";
    $car_pending_result = mysqli_query($conn, $car_pending_query);
    $badges['cars'] = $car_pending_result ? mysqli_fetch_assoc($car_pending_result)['count'] : 0;

    // 3. Motorcycle Listings - Pending Approval
    $moto_pending_query = "SELECT COUNT(*) as count FROM motorcycles WHERE status = 'pending'";
    $moto_pending_result = mysqli_query($conn, $moto_pending_query);
    $badges['motorcycles'] = $moto_pending_result ? mysqli_fetch_assoc($moto_pending_result)['count'] : 0;

    // 4. Bookings - Pending Approval
    $booking_pending_query = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
    $booking_pending_result = mysqli_query($conn, $booking_pending_query);
    $badges['bookings'] = $booking_pending_result ? mysqli_fetch_assoc($booking_pending_result)['count'] : 0;

    // 5. Payments - Pending Verification (Regular + Late Fees)
    $regular_pending_query = "SELECT COUNT(*) AS c FROM payments p
                              WHERE p.payment_status='pending'
                              AND NOT EXISTS (
                                  SELECT 1 FROM payment_transactions pt 
                                  WHERE pt.booking_id = p.booking_id 
                                  AND pt.reference_id = p.payment_reference
                                  AND pt.transaction_type = 'late_fee_payment'
                              )";
    $regular_pending_result = mysqli_query($conn, $regular_pending_query);
    $regular_pending = $regular_pending_result ? mysqli_fetch_assoc($regular_pending_result)['c'] : 0;

    $latefee_pending_query = "SELECT COUNT(*) AS c FROM late_fee_payments WHERE payment_status='pending'";
    $latefee_pending_result = mysqli_query($conn, $latefee_pending_query);
    $latefee_pending = $latefee_pending_result ? mysqli_fetch_assoc($latefee_pending_result)['c'] : 0;

    $badges['payments'] = $regular_pending + $latefee_pending;

    // 6. Overdue Rentals
    $overdue_query = "SELECT COUNT(*) as count FROM bookings 
                      WHERE status = 'approved' 
                      AND CONCAT(return_date, ' ', return_time) < NOW()";
    $overdue_result = mysqli_query($conn, $overdue_query);
    $badges['overdue'] = $overdue_result ? mysqli_fetch_assoc($overdue_result)['count'] : 0;

    // 7. Refunds - Pending
    $refund_query = "SELECT COUNT(*) as count FROM refunds WHERE status = 'pending'";
    $refund_result = mysqli_query($conn, $refund_query);
    $badges['refunds'] = $refund_result ? mysqli_fetch_assoc($refund_result)['count'] : 0;

    // 8. Payouts - Pending/Processing
    $payout_query = "SELECT COUNT(*) as count FROM payouts WHERE status IN ('pending', 'processing')";
    $payout_result = mysqli_query($conn, $payout_query);
    $badges['payouts'] = $payout_result ? mysqli_fetch_assoc($payout_result)['count'] : 0;

    // 9. Escrow - Held
    $escrow_query = "SELECT COUNT(*) as count FROM escrow WHERE status = 'held'";
    $escrow_result = mysqli_query($conn, $escrow_query);
    $badges['escrow'] = $escrow_result ? mysqli_fetch_assoc($escrow_result)['count'] : 0;

    // 10. User Reports - Unresolved (Pending + Under Review)
    $reports_query = "SELECT COUNT(*) as count FROM reports WHERE status IN ('pending', 'under_review')";
    $reports_result = mysqli_query($conn, $reports_query);
    $badges['reports'] = $reports_result ? mysqli_fetch_assoc($reports_result)['count'] : 0;

    // 11. Notifications - Unread
    $notif_query = "SELECT COUNT(*) as count FROM admin_notifications WHERE read_status = 'unread'";
    $notif_result = mysqli_query($conn, $notif_query);
    $badges['notifications'] = $notif_result ? mysqli_fetch_assoc($notif_result)['count'] : 0;

    // Return success response
    echo json_encode([
        'success' => true,
        'badges' => $badges,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'badges' => []
    ]);
}

mysqli_close($conn);
?>
