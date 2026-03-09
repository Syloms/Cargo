<?php
/**
 * =====================================================
 * REVENUE VALIDATION & MIGRATION SCRIPT
 * CarGo Admin - Validate Revenue Calculations
 * =====================================================
 * 
 * This script validates the revenue data to ensure:
 * 1. All completed bookings are counted
 * 2. Escrow status is properly tracked
 * 3. Late fees are calculated correctly
 * 4. Refunds are properly accounted for
 * 5. Revenue calculations match transaction history
 */

header('Content-Type: application/json');
require_once '../../include/db.php';

// Authentication check (uncomment for production)
// session_start();
// if (!isset($_SESSION['admin_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

$action = $_GET['action'] ?? 'validate';
$owner_id = $_GET['owner_id'] ?? null;

try {
    switch ($action) {
        case 'validate':
            echo json_encode(validateRevenueData($conn, $owner_id));
            break;
        case 'compare':
            echo json_encode(compareOldVsNewCalculation($conn, $owner_id));
            break;
        case 'audit':
            echo json_encode(auditBookingStatuses($conn, $owner_id));
            break;
        case 'fix_missing_fields':
            echo json_encode(fixMissingFields($conn));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();

// =====================================================
// VALIDATION FUNCTIONS
// =====================================================

/**
 * Validate revenue data for all owners or specific owner
 */
function validateRevenueData($conn, $owner_id = null) {
    $whereClause = $owner_id ? "WHERE b.owner_id = $owner_id" : "";
    
    $query = "
        SELECT 
            b.owner_id,
            u.fullname as owner_name,
            COUNT(*) as total_bookings,
            
            -- Count by status
            SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN b.status = 'ongoing' THEN 1 ELSE 0 END) as ongoing_count,
            SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            
            -- Payment verification
            SUM(CASE WHEN b.payment_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_payments,
            SUM(CASE WHEN b.payment_verified_at IS NULL THEN 1 ELSE 0 END) as unverified_payments,
            
            -- Escrow status
            SUM(CASE WHEN b.escrow_status = 'held' THEN 1 ELSE 0 END) as escrow_held,
            SUM(CASE WHEN b.escrow_status = 'released_to_owner' THEN 1 ELSE 0 END) as escrow_released,
            SUM(CASE WHEN b.escrow_status = 'pending' THEN 1 ELSE 0 END) as escrow_pending,
            
            -- Payout status
            SUM(CASE WHEN b.payout_status = 'completed' THEN 1 ELSE 0 END) as payouts_completed,
            SUM(CASE WHEN b.payout_status = 'pending' THEN 1 ELSE 0 END) as payouts_pending,
            
            -- Late fees
            SUM(CASE WHEN b.late_fee_charged = 1 THEN 1 ELSE 0 END) as bookings_with_late_fees,
            SUM(CASE WHEN b.late_fee_charged = 1 THEN b.late_fee_amount ELSE 0 END) as total_late_fees,
            
            -- Revenue calculations
            SUM(b.total_amount) as total_booking_amount,
            SUM(b.platform_fee) as total_platform_fees,
            SUM(b.owner_payout) as total_owner_payout,
            
            -- Issues detection
            SUM(CASE WHEN b.status = 'completed' AND b.payment_verified_at IS NULL THEN 1 ELSE 0 END) as completed_but_unverified,
            SUM(CASE WHEN b.status = 'completed' AND b.escrow_status = 'pending' THEN 1 ELSE 0 END) as completed_but_not_escrowed,
            SUM(CASE WHEN b.status = 'approved' AND b.payment_verified_at IS NULL THEN 1 ELSE 0 END) as approved_but_unpaid
            
        FROM bookings b
        LEFT JOIN users u ON b.owner_id = u.id
        $whereClause
        GROUP BY b.owner_id
        ORDER BY total_bookings DESC
    ";
    
    $result = $conn->query($query);
    $data = [];
    $totalIssues = 0;
    
    while ($row = $result->fetch_assoc()) {
        $issues = [];
        
        if ($row['completed_but_unverified'] > 0) {
            $issues[] = $row['completed_but_unverified'] . " completed bookings without payment verification";
            $totalIssues += $row['completed_but_unverified'];
        }
        
        if ($row['completed_but_not_escrowed'] > 0) {
            $issues[] = $row['completed_but_not_escrowed'] . " completed bookings not in escrow";
            $totalIssues += $row['completed_but_not_escrowed'];
        }
        
        if ($row['approved_but_unpaid'] > 0) {
            $issues[] = $row['approved_but_unpaid'] . " approved bookings without payment";
            $totalIssues += $row['approved_but_unpaid'];
        }
        
        $data[] = [
            'owner_id' => $row['owner_id'],
            'owner_name' => $row['owner_name'],
            'statistics' => [
                'total_bookings' => intval($row['total_bookings']),
                'status_breakdown' => [
                    'pending' => intval($row['pending_count']),
                    'approved' => intval($row['approved_count']),
                    'ongoing' => intval($row['ongoing_count']),
                    'completed' => intval($row['completed_count']),
                    'cancelled' => intval($row['cancelled_count']),
                    'rejected' => intval($row['rejected_count'])
                ],
                'payment_status' => [
                    'verified' => intval($row['verified_payments']),
                    'unverified' => intval($row['unverified_payments'])
                ],
                'escrow_status' => [
                    'held' => intval($row['escrow_held']),
                    'released' => intval($row['escrow_released']),
                    'pending' => intval($row['escrow_pending'])
                ],
                'payout_status' => [
                    'completed' => intval($row['payouts_completed']),
                    'pending' => intval($row['payouts_pending'])
                ],
                'late_fees' => [
                    'count' => intval($row['bookings_with_late_fees']),
                    'total_amount' => floatval($row['total_late_fees'])
                ],
                'revenue' => [
                    'total_booking_amount' => floatval($row['total_booking_amount']),
                    'platform_fees' => floatval($row['total_platform_fees']),
                    'owner_payout' => floatval($row['total_owner_payout'])
                ]
            ],
            'issues' => $issues,
            'has_issues' => count($issues) > 0
        ];
    }
    
    return [
        'success' => true,
        'action' => 'validate',
        'total_owners' => count($data),
        'total_issues_found' => $totalIssues,
        'data' => $data
    ];
}

/**
 * Compare old calculation method vs new calculation method
 */
function compareOldVsNewCalculation($conn, $owner_id = null) {
    if (!$owner_id) {
        return ['success' => false, 'message' => 'Owner ID required for comparison'];
    }
    
    // OLD METHOD (incorrect)
    $oldQuery = "
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM bookings
        WHERE owner_id = ?
        AND status IN ('ongoing', 'approved')
    ";
    $stmt = $conn->prepare($oldQuery);
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $oldRevenue = floatval($stmt->get_result()->fetch_assoc()['total']);
    
    // NEW METHOD (corrected)
    $newQuery = "
        SELECT 
            COALESCE(SUM(
                b.owner_payout + 
                CASE WHEN b.late_fee_charged = 1 THEN COALESCE(b.late_fee_amount, 0) ELSE 0 END
            ), 0) as total
        FROM bookings b
        WHERE b.owner_id = ?
        AND (
            b.escrow_status IN ('held', 'released_to_owner')
            OR b.payout_status = 'completed'
            OR (b.status = 'completed' AND b.payment_verified_at IS NOT NULL)
        )
    ";
    $stmt = $conn->prepare($newQuery);
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $newRevenue = floatval($stmt->get_result()->fetch_assoc()['total']);
    
    // Get refunds
    $refundQuery = "
        SELECT COALESCE(SUM(r.refund_amount - COALESCE(r.deduction_amount, 0)), 0) as total
        FROM refunds r
        INNER JOIN bookings b ON r.booking_id = b.id
        WHERE b.owner_id = ?
        AND r.status IN ('completed', 'processing')
    ";
    $stmt = $conn->prepare($refundQuery);
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalRefunds = floatval($stmt->get_result()->fetch_assoc()['total']);
    
    $netNewRevenue = $newRevenue - $totalRefunds;
    
    // Get breakdown of what's different
    $breakdownQuery = "
        SELECT 
            SUM(CASE WHEN status = 'completed' AND payment_verified_at IS NOT NULL THEN owner_payout ELSE 0 END) as completed_revenue,
            SUM(CASE WHEN status IN ('approved', 'ongoing') THEN owner_payout ELSE 0 END) as active_revenue,
            SUM(CASE WHEN escrow_status = 'held' THEN owner_payout ELSE 0 END) as escrowed_revenue,
            SUM(CASE WHEN late_fee_charged = 1 THEN late_fee_amount ELSE 0 END) as late_fee_revenue,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status IN ('approved', 'ongoing') THEN 1 END) as active_count
        FROM bookings
        WHERE owner_id = ?
    ";
    $stmt = $conn->prepare($breakdownQuery);
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $breakdown = $stmt->get_result()->fetch_assoc();
    
    return [
        'success' => true,
        'action' => 'compare',
        'owner_id' => $owner_id,
        'comparison' => [
            'old_method' => [
                'total_revenue' => $oldRevenue,
                'description' => 'Only counts approved + ongoing bookings (incorrect)'
            ],
            'new_method' => [
                'gross_revenue' => $newRevenue,
                'refunds' => $totalRefunds,
                'net_revenue' => $netNewRevenue,
                'description' => 'Counts completed, escrowed, and verified payments + late fees - refunds'
            ],
            'difference' => $netNewRevenue - $oldRevenue,
            'difference_percentage' => $oldRevenue > 0 ? (($netNewRevenue - $oldRevenue) / $oldRevenue * 100) : 0
        ],
        'breakdown' => [
            'completed_bookings' => [
                'count' => intval($breakdown['completed_count']),
                'revenue' => floatval($breakdown['completed_revenue'])
            ],
            'active_bookings' => [
                'count' => intval($breakdown['active_count']),
                'revenue' => floatval($breakdown['active_revenue'])
            ],
            'escrowed_funds' => floatval($breakdown['escrowed_revenue']),
            'late_fees' => floatval($breakdown['late_fee_revenue']),
            'refunds_issued' => $totalRefunds
        ],
        'analysis' => [
            'missing_from_old_calculation' => floatval($breakdown['completed_revenue']),
            'includes_late_fees' => floatval($breakdown['late_fee_revenue']),
            'deducts_refunds' => $totalRefunds
        ]
    ];
}

/**
 * Audit booking statuses to find anomalies
 */
function auditBookingStatuses($conn, $owner_id = null) {
    $whereClause = $owner_id ? "WHERE owner_id = $owner_id" : "";
    
    $issues = [];
    
    // Issue 1: Completed bookings without payment verification
    $query1 = "
        SELECT id, owner_id, total_amount, status, payment_status, created_at
        FROM bookings
        $whereClause
        " . ($whereClause ? "AND" : "WHERE") . " status = 'completed' 
        AND payment_verified_at IS NULL
        LIMIT 50
    ";
    $result1 = $conn->query($query1);
    $issue1Data = [];
    while ($row = $result1->fetch_assoc()) {
        $issue1Data[] = $row;
    }
    if (count($issue1Data) > 0) {
        $issues[] = [
            'type' => 'completed_unverified',
            'severity' => 'HIGH',
            'count' => count($issue1Data),
            'description' => 'Completed bookings without payment verification',
            'impact' => 'These bookings may not be counted in revenue',
            'samples' => array_slice($issue1Data, 0, 5)
        ];
    }
    
    // Issue 2: Approved bookings without payment
    $query2 = "
        SELECT id, owner_id, total_amount, status, payment_status, created_at
        FROM bookings
        $whereClause
        " . ($whereClause ? "AND" : "WHERE") . " status = 'approved' 
        AND payment_verified_at IS NULL
        LIMIT 50
    ";
    $result2 = $conn->query($query2);
    $issue2Data = [];
    while ($row = $result2->fetch_assoc()) {
        $issue2Data[] = $row;
    }
    if (count($issue2Data) > 0) {
        $issues[] = [
            'type' => 'approved_unpaid',
            'severity' => 'MEDIUM',
            'count' => count($issue2Data),
            'description' => 'Approved bookings without payment verification',
            'impact' => 'Old calculation counted these as revenue (incorrect)',
            'samples' => array_slice($issue2Data, 0, 5)
        ];
    }
    
    // Issue 3: Bookings with late fees not charged
    $query3 = "
        SELECT id, owner_id, overdue_days, late_fee_amount, late_fee_charged, status
        FROM bookings
        $whereClause
        " . ($whereClause ? "AND" : "WHERE") . " overdue_days > 0 
        AND late_fee_amount > 0 
        AND late_fee_charged = 0
        LIMIT 50
    ";
    $result3 = $conn->query($query3);
    $issue3Data = [];
    while ($row = $result3->fetch_assoc()) {
        $issue3Data[] = $row;
    }
    if (count($issue3Data) > 0) {
        $issues[] = [
            'type' => 'late_fees_not_charged',
            'severity' => 'LOW',
            'count' => count($issue3Data),
            'description' => 'Bookings with calculated late fees not marked as charged',
            'impact' => 'Late fees may not be included in revenue',
            'samples' => array_slice($issue3Data, 0, 5)
        ];
    }
    
    // Issue 4: Escrow status inconsistencies
    $query4 = "
        SELECT id, owner_id, status, payment_status, escrow_status, payout_status
        FROM bookings
        $whereClause
        " . ($whereClause ? "AND" : "WHERE") . " status = 'completed' 
        AND escrow_status = 'pending'
        LIMIT 50
    ";
    $result4 = $conn->query($query4);
    $issue4Data = [];
    while ($row = $result4->fetch_assoc()) {
        $issue4Data[] = $row;
    }
    if (count($issue4Data) > 0) {
        $issues[] = [
            'type' => 'escrow_inconsistency',
            'severity' => 'HIGH',
            'count' => count($issue4Data),
            'description' => 'Completed bookings with escrow still pending',
            'impact' => 'May not be counted in revenue calculations',
            'samples' => array_slice($issue4Data, 0, 5)
        ];
    }
    
    return [
        'success' => true,
        'action' => 'audit',
        'total_issues' => count($issues),
        'issues' => $issues,
        'recommendation' => count($issues) > 0 ? 'Review and fix identified issues' : 'No issues found'
    ];
}

/**
 * Fix missing or incorrect field values
 */
function fixMissingFields($conn) {
    $fixes = [];
    
    // Fix 1: Set owner_payout if missing
    $query1 = "
        UPDATE bookings 
        SET owner_payout = total_amount - platform_fee
        WHERE owner_payout = 0 OR owner_payout IS NULL
    ";
    $result1 = $conn->query($query1);
    $fixes[] = [
        'fix' => 'Set owner_payout from total_amount - platform_fee',
        'rows_affected' => $conn->affected_rows
    ];
    
    // Fix 2: Set platform_fee if missing (assuming 10% fee)
    $query2 = "
        UPDATE bookings 
        SET platform_fee = total_amount * 0.10,
            owner_payout = total_amount * 0.90
        WHERE (platform_fee = 0 OR platform_fee IS NULL)
        AND total_amount > 0
    ";
    $result2 = $conn->query($query2);
    $fixes[] = [
        'fix' => 'Set platform_fee to 10% and recalculate owner_payout',
        'rows_affected' => $conn->affected_rows
    ];
    
    return [
        'success' => true,
        'action' => 'fix_missing_fields',
        'fixes_applied' => $fixes,
        'total_rows_updated' => array_sum(array_column($fixes, 'rows_affected'))
    ];
}
?>
