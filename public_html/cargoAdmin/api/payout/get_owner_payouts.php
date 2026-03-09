<?php
/**
 * =====================================================
 * GET OWNER PAYOUTS API
 * File: api/payout/get_owner_payouts.php
 * =====================================================
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../include/db.php';

if (!isset($_GET['owner_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Owner ID is required'
    ]);
    exit;
}

$ownerId = intval($_GET['owner_id']);

try {
    // Get statistics
    $statsQuery = "
        SELECT 
            -- Total earnings (all completed payouts)
            COALESCE(SUM(CASE WHEN p.status = 'completed' THEN p.net_amount ELSE 0 END), 0) as total_earnings,
            
            -- Pending amount (escrow released but payout not completed)
            COALESCE(SUM(CASE WHEN b.escrow_status = 'released_to_owner' AND b.payout_status = 'pending' THEN b.owner_payout ELSE 0 END), 0) as pending_amount,
            
            -- Completed payouts sum
            COALESCE(SUM(CASE WHEN p.status = 'completed' THEN p.net_amount ELSE 0 END), 0) as completed_payouts,
            
            -- Total platform fees
            COALESCE(SUM(p.platform_fee), 0) as total_platform_fees
            
        FROM bookings b
        LEFT JOIN payouts p ON b.id = p.booking_id
        WHERE b.owner_id = ?
    ";
    
    $stmt = $conn->prepare($statsQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $statistics = $stmt->get_result()->fetch_assoc();
    
    // Get recent payouts (last 5)
    $recentQuery = "
        SELECT 
            p.id as payout_id,
            p.booking_id,
            p.amount,
            p.platform_fee,
            p.net_amount,
            p.status,
            p.created_at,
            p.processed_at,
            p.completion_reference,
            p.transfer_proof,
            b.car_id,
            CONCAT(c.brand, ' ', c.model) as car_name
        FROM payouts p
        INNER JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN cars c ON b.car_id = c.id
        WHERE p.owner_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ";
    
    $stmt = $conn->prepare($recentQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $recentResult = $stmt->get_result();
    
    $recentPayouts = [];
    while ($row = $recentResult->fetch_assoc()) {
        $recentPayouts[] = $row;
    }
    
    // Get pending releases (escrow released but payout not completed)
    $pendingQuery = "
        SELECT 
            b.id as booking_id,
            b.owner_payout,
            b.return_date as expected_release_date,
            b.escrow_released_at,
            CONCAT(c.brand, ' ', c.model) as car_name
        FROM bookings b
        LEFT JOIN cars c ON b.car_id = c.id
        WHERE b.owner_id = ?
        AND b.escrow_status = 'released_to_owner'
        AND b.payout_status IN ('pending', 'processing')
        ORDER BY b.escrow_released_at DESC
    ";
    
    $stmt = $conn->prepare($pendingQuery);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $pendingResult = $stmt->get_result();
    
    $pendingReleases = [];
    while ($row = $pendingResult->fetch_assoc()) {
        $pendingReleases[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'statistics' => $statistics,
        'recent_payouts' => $recentPayouts,
        'pending_releases' => $pendingReleases
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();