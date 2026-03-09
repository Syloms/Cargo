<?php
// api/dashboard/dashboard_stats.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../../include/db.php';

$owner_id = $_GET['owner_id'] ?? null;

if (!$owner_id) {
    echo json_encode(['success' => false, 'message' => 'Owner ID required']);
    exit;
}

try {
    // ========================================
    // VEHICLE STATISTICS (CARS + MOTORCYCLES)
    // ========================================
    
    // ✅ FIXED: Total Vehicles - ONLY APPROVED (for dashboard)
    // Fix: Use "ii" for integer binding instead of "ss" for string
    $totalCarsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM cars WHERE owner_id = ? AND status = 'approved') +
            (SELECT COUNT(*) FROM motorcycles WHERE owner_id = ? AND status = 'approved') as total
    ";
    $carStmt = $conn->prepare($totalCarsQuery);
    $carStmt->bind_param("ii", $owner_id, $owner_id);
    $carStmt->execute();
    $totalCars = $carStmt->get_result()->fetch_assoc()['total'];
    
    // ✅ FIXED: Approved Vehicles (Cars + Motorcycles)
    $approvedQuery = "
        SELECT 
            (SELECT COUNT(*) FROM cars WHERE owner_id = ? AND status = 'approved') +
            (SELECT COUNT(*) FROM motorcycles WHERE owner_id = ? AND status = 'approved') as total
    ";
    $approvedStmt = $conn->prepare($approvedQuery);
    $approvedStmt->bind_param("ii", $owner_id, $owner_id);
    $approvedStmt->execute();
    $approvedCars = $approvedStmt->get_result()->fetch_assoc()['total'];
    
    // ✅ FIXED: Pending Vehicles (Cars + Motorcycles)
    $pendingQuery = "
        SELECT 
            (SELECT COUNT(*) FROM cars WHERE owner_id = ? AND status = 'pending') +
            (SELECT COUNT(*) FROM motorcycles WHERE owner_id = ? AND status = 'pending') as total
    ";
    $pendingStmt = $conn->prepare($pendingQuery);
    $pendingStmt->bind_param("ii", $owner_id, $owner_id);
    $pendingStmt->execute();
    $pendingCars = $pendingStmt->get_result()->fetch_assoc()['total'];
    
    // ✅ FIXED: Rented Vehicles (Cars + Motorcycles currently active)
    $rentedQuery = "
        SELECT 
            (SELECT COUNT(*) FROM cars WHERE owner_id = ? AND status = 'rented') +
            (SELECT COUNT(*) FROM motorcycles WHERE owner_id = ? AND status = 'rented') as total
    ";
    $rentedStmt = $conn->prepare($rentedQuery);
    $rentedStmt->bind_param("ii", $owner_id, $owner_id);
    $rentedStmt->execute();
    $rentedCars = $rentedStmt->get_result()->fetch_assoc()['total'];

    // ========================================
    // BOOKING STATISTICS
    // ========================================
    
    // Total Bookings (all statuses)
    $totalBookingsStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE owner_id = ?");
    $totalBookingsStmt->bind_param("s", $owner_id);
    $totalBookingsStmt->execute();
    $totalBookings = $totalBookingsStmt->get_result()->fetch_assoc()['total'];
    
    // Pending Requests (only count bookings with verified payments)
    $pendingRequestsStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE owner_id = ? AND status = 'pending' AND payment_status = 'paid'");
    $pendingRequestsStmt->bind_param("s", $owner_id);
    $pendingRequestsStmt->execute();
    $pendingRequests = $pendingRequestsStmt->get_result()->fetch_assoc()['total'];
    
    // Active Bookings (approved and currently ongoing)
    $activeBookingsStmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM bookings 
    WHERE owner_id = ? 
    AND status IN ('approved', 'ongoing')
    AND pickup_date <= CURDATE()
    AND return_date >= CURDATE()
");

    $activeBookingsStmt->bind_param("s", $owner_id);
    $activeBookingsStmt->execute();
    $activeBookings = $activeBookingsStmt->get_result()->fetch_assoc()['total'];
    
    // Cancelled Bookings (cancelled by renter)
    $cancelledStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE owner_id = ? AND status = 'cancelled'");
    $cancelledStmt->bind_param("s", $owner_id);
    $cancelledStmt->execute();
    $cancelledBookings = $cancelledStmt->get_result()->fetch_assoc()['total'];
    
    // Rejected Bookings (rejected by owner)
    $rejectedStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE owner_id = ? AND status = 'rejected'");
    $rejectedStmt->bind_param("s", $owner_id);
    $rejectedStmt->execute();
    $rejectedBookings = $rejectedStmt->get_result()->fetch_assoc()['total'];

    // ========================================
    // INCOME STATISTICS (CORRECTED - INCLUDES COMPLETED, ESCROW, LATE FEES, REFUNDS)
    // ========================================
    
    // Total Income (all secured and completed payments)
    // FIX: Use IF-ELSEIF logic via CASE to prevent duplicate counting
    $totalIncomeStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(
                CASE
                    -- Priority 1: Payout already completed (final state)
                    WHEN b.payout_status = 'completed' THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    -- Priority 2: Money held in escrow (secured but not paid out yet)
                    WHEN b.escrow_status IN ('held', 'released_to_owner') THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    -- Priority 3: Completed rental with verified payment (legacy bookings)
                    WHEN b.status = 'completed' AND b.payment_verified_at IS NOT NULL THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    ELSE 0
                END
            ), 0) as total
        FROM bookings b
        WHERE b.owner_id = ?
    ");
    $totalIncomeStmt->bind_param("s", $owner_id);
    $totalIncomeStmt->execute();
    $totalIncome = floatval($totalIncomeStmt->get_result()->fetch_assoc()['total']);
    
    // Calculate total refunds
    $refundStmt = $conn->prepare("
        SELECT COALESCE(SUM(r.refund_amount - COALESCE(r.deduction_amount, 0)), 0) as total
        FROM refunds r
        INNER JOIN bookings b ON r.booking_id = b.id
        WHERE b.owner_id = ?
        AND r.status IN ('completed', 'processing')
    ");
    $refundStmt->bind_param("s", $owner_id);
    $refundStmt->execute();
    $totalRefunds = floatval($refundStmt->get_result()->fetch_assoc()['total']);
    
    // Store gross for breakdown
    $grossTotalIncome = $totalIncome;
    $totalIncome = $totalIncome - $totalRefunds;
    
    // Monthly Income (current month)
    // FIX: Use IF-ELSEIF logic via CASE to prevent duplicate counting
    $monthlyIncomeStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(
                CASE
                    WHEN b.payout_status = 'completed' THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    WHEN b.escrow_status IN ('held', 'released_to_owner') THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    WHEN b.status = 'completed' AND b.payment_verified_at IS NOT NULL THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    ELSE 0
                END
            ), 0) as total
        FROM bookings b
        WHERE b.owner_id = ? 
        AND YEAR(b.created_at) = YEAR(CURDATE())
        AND MONTH(b.created_at) = MONTH(CURDATE())
    ");
    $monthlyIncomeStmt->bind_param("s", $owner_id);
    $monthlyIncomeStmt->execute();
    $monthlyIncome = floatval($monthlyIncomeStmt->get_result()->fetch_assoc()['total']);
    
    // Monthly refunds
    $monthlyRefundStmt = $conn->prepare("
        SELECT COALESCE(SUM(r.refund_amount - COALESCE(r.deduction_amount, 0)), 0) as total
        FROM refunds r
        INNER JOIN bookings b ON r.booking_id = b.id
        WHERE b.owner_id = ?
        AND r.status IN ('completed', 'processing')
        AND YEAR(r.processed_at) = YEAR(CURDATE())
        AND MONTH(r.processed_at) = MONTH(CURDATE())
    ");
    $monthlyRefundStmt->bind_param("s", $owner_id);
    $monthlyRefundStmt->execute();
    $monthlyRefunds = floatval($monthlyRefundStmt->get_result()->fetch_assoc()['total']);
    
    $grossMonthlyIncome = $monthlyIncome;
    $monthlyIncome = $monthlyIncome - $monthlyRefunds;
    
    // Weekly Income (last 7 days)
    // FIX: Use IF-ELSEIF logic via CASE to prevent duplicate counting
    $weeklyIncomeStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(
                CASE
                    WHEN b.payout_status = 'completed' THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    WHEN b.escrow_status IN ('held', 'released_to_owner') THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    WHEN b.status = 'completed' AND b.payment_verified_at IS NOT NULL THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    ELSE 0
                END
            ), 0) as total
        FROM bookings b
        WHERE b.owner_id = ? 
        AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $weeklyIncomeStmt->bind_param("s", $owner_id);
    $weeklyIncomeStmt->execute();
    $weeklyIncome = floatval($weeklyIncomeStmt->get_result()->fetch_assoc()['total']);
    
    // Weekly refunds
    $weeklyRefundStmt = $conn->prepare("
        SELECT COALESCE(SUM(r.refund_amount - COALESCE(r.deduction_amount, 0)), 0) as total
        FROM refunds r
        INNER JOIN bookings b ON r.booking_id = b.id
        WHERE b.owner_id = ?
        AND r.status IN ('completed', 'processing')
        AND r.processed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $weeklyRefundStmt->bind_param("s", $owner_id);
    $weeklyRefundStmt->execute();
    $weeklyRefunds = floatval($weeklyRefundStmt->get_result()->fetch_assoc()['total']);
    
    $grossWeeklyIncome = $weeklyIncome;
    $weeklyIncome = $weeklyIncome - $weeklyRefunds;
    
    // Today's Income
    // FIX: Use IF-ELSEIF logic via CASE to prevent duplicate counting
    $todayIncomeStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(
                CASE
                    WHEN b.payout_status = 'completed' THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    WHEN b.escrow_status IN ('held', 'released_to_owner') THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    WHEN b.status = 'completed' AND b.payment_verified_at IS NOT NULL THEN 
                        b.owner_payout + CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
                    ELSE 0
                END
            ), 0) as total
        FROM bookings b
        WHERE b.owner_id = ? 
        AND DATE(b.created_at) = CURDATE()
    ");
    $todayIncomeStmt->bind_param("s", $owner_id);
    $todayIncomeStmt->execute();
    $todayIncome = floatval($todayIncomeStmt->get_result()->fetch_assoc()['total']);
    
    // Today's refunds
    $todayRefundStmt = $conn->prepare("
        SELECT COALESCE(SUM(r.refund_amount - COALESCE(r.deduction_amount, 0)), 0) as total
        FROM refunds r
        INNER JOIN bookings b ON r.booking_id = b.id
        WHERE b.owner_id = ?
        AND r.status IN ('completed', 'processing')
        AND DATE(r.processed_at) = CURDATE()
    ");
    $todayRefundStmt->bind_param("s", $owner_id);
    $todayRefundStmt->execute();
    $todayRefunds = floatval($todayRefundStmt->get_result()->fetch_assoc()['total']);
    
    $grossTodayIncome = $todayIncome;
    $todayIncome = $todayIncome - $todayRefunds;
    
    // Get late fees breakdown
    // FIX: Use IF-ELSEIF logic via CASE to prevent duplicate counting
    $lateFeesStmt = $conn->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN payout_status = 'completed' AND late_fee_charged = 1 THEN late_fee_amount
                WHEN escrow_status IN ('held', 'released_to_owner') AND late_fee_charged = 1 THEN late_fee_amount
                WHEN status = 'completed' AND payment_verified_at IS NOT NULL AND late_fee_charged = 1 THEN late_fee_amount
                ELSE 0
            END
        ), 0) as total
        FROM bookings
        WHERE owner_id = ?
    ");
    $lateFeesStmt->bind_param("s", $owner_id);
    $lateFeesStmt->execute();
    $totalLateFees = floatval($lateFeesStmt->get_result()->fetch_assoc()['total']);

    // ========================================
    // NOTIFICATIONS & MESSAGES
    // ========================================
    
    // Unread Notifications
    $notifStmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND read_status = 'unread'");
    $notifStmt->bind_param("s", $owner_id);
    $notifStmt->execute();
    $unreadNotifications = $notifStmt->get_result()->fetch_assoc()['total'];
    
    // Unread Messages (placeholder - implement based on your messaging system)
    $unreadMessages = 0;

    // ========================================
    // RETURN RESPONSE
    // ========================================
    
    echo json_encode([
        'success' => true,
        'stats' => [
            // Car Stats
            'total_cars' => intval($totalCars),
            'approved_cars' => intval($approvedCars),
            'pending_cars' => intval($pendingCars),
            'rented_cars' => intval($rentedCars),
            
            // Booking Stats
            'total_bookings' => intval($totalBookings),
            'pending_requests' => intval($pendingRequests),
            'active_bookings' => intval($activeBookings),
            'cancelled_bookings' => intval($cancelledBookings),
            'rejected_bookings' => intval($rejectedBookings),
            
            // Income Stats (Net Revenue)
            'total_income' => $totalIncome,
            'monthly_income' => $monthlyIncome,
            'weekly_income' => $weeklyIncome,
            'today_income' => $todayIncome,
            
            // Revenue Breakdown (NEW)
            'revenue_breakdown' => [
                'total' => [
                    'gross_revenue' => $grossTotalIncome,
                    'late_fees' => $totalLateFees,
                    'refunds_issued' => $totalRefunds,
                    'net_revenue' => $totalIncome
                ],
                'monthly' => [
                    'gross_revenue' => $grossMonthlyIncome,
                    'refunds_issued' => $monthlyRefunds,
                    'net_revenue' => $monthlyIncome
                ],
                'weekly' => [
                    'gross_revenue' => $grossWeeklyIncome,
                    'refunds_issued' => $weeklyRefunds,
                    'net_revenue' => $weeklyIncome
                ],
                'today' => [
                    'gross_revenue' => $grossTodayIncome,
                    'refunds_issued' => $todayRefunds,
                    'net_revenue' => $todayIncome
                ]
            ],
            
            // Notifications
            'unread_notifications' => intval($unreadNotifications),
            'unread_messages' => intval($unreadMessages)
        ]
    ]);

    // Close all statements
    $carStmt->close();
    $approvedStmt->close();
    $pendingStmt->close();
    $rentedStmt->close();
    $totalBookingsStmt->close();
    $pendingRequestsStmt->close();
    $activeBookingsStmt->close();
    $cancelledStmt->close();
    $rejectedStmt->close();
    $totalIncomeStmt->close();
    $refundStmt->close();
    $monthlyIncomeStmt->close();
    $monthlyRefundStmt->close();
    $weeklyIncomeStmt->close();
    $weeklyRefundStmt->close();
    $todayIncomeStmt->close();
    $todayRefundStmt->close();
    $lateFeesStmt->close();
    $notifStmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard stats: ' . $e->getMessage()
    ]);
}

$conn->close();
?>