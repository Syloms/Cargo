<?php
/**
 * API Endpoint: Check Releasable Escrows
 * Returns bookings that are ready for escrow release
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../include/db.php';

try {
    // Find bookings ready for auto-release (completed + 3 days since return)
    $query = "
        SELECT 
            b.id as booking_id,
            b.owner_id,
            b.user_id as renter_id,
            b.owner_payout,
            b.platform_fee,
            b.return_date,
            b.escrow_status,
            b.payout_status,
            DATEDIFF(NOW(), b.return_date) as days_since_return,
            u.fullname as owner_name,
            u.email as owner_email,
            u.gcash_number as owner_gcash,
            u.gcash_name as owner_gcash_name,
            r.fullname as renter_name,
            CASE 
                WHEN c.id IS NOT NULL THEN c.brand
                WHEN m.id IS NOT NULL THEN m.brand
                ELSE 'Unknown'
            END as vehicle_brand,
            CASE 
                WHEN c.id IS NOT NULL THEN c.model
                WHEN m.id IS NOT NULL THEN m.model
                ELSE 'Unknown'
            END as vehicle_model
        FROM bookings b
        LEFT JOIN users u ON b.owner_id = u.id
        LEFT JOIN users r ON b.user_id = r.id
        LEFT JOIN cars c ON b.car_id = c.id
        LEFT JOIN motorcycles m ON b.motorcycle_id = m.id
        WHERE b.status = 'completed'
          AND b.escrow_status = 'held'
          AND b.return_date IS NOT NULL
          AND b.return_date < DATE_SUB(NOW(), INTERVAL 3 DAY)
          AND b.owner_payout > 0
        ORDER BY b.return_date ASC
    ";
    
    $result = $conn->query($query);
    
    $releasableBookings = [];
    $totalAmount = 0;
    $ownersWithoutGcash = [];
    
    while ($row = $result->fetch_assoc()) {
        $gcashConfigured = !empty($row['owner_gcash']) && !empty($row['owner_gcash_name']);
        
        if (!$gcashConfigured) {
            $ownersWithoutGcash[] = [
                'owner_id' => $row['owner_id'],
                'owner_name' => $row['owner_name'],
                'booking_id' => $row['booking_id']
            ];
        }
        
        $releasableBookings[] = [
            'booking_id' => $row['booking_id'],
            'owner_id' => $row['owner_id'],
            'owner_name' => $row['owner_name'],
            'renter_name' => $row['renter_name'],
            'vehicle' => $row['vehicle_brand'] . ' ' . $row['vehicle_model'],
            'owner_payout' => floatval($row['owner_payout']),
            'platform_fee' => floatval($row['platform_fee']),
            'return_date' => $row['return_date'],
            'days_since_return' => intval($row['days_since_return']),
            'gcash_configured' => $gcashConfigured,
            'gcash_number' => $row['owner_gcash'] ?? null,
            'gcash_name' => $row['owner_gcash_name'] ?? null,
            'can_auto_release' => true
        ];
        
        $totalAmount += floatval($row['owner_payout']);
    }
    
    // Get count of already released but pending payouts
    $pendingQuery = "
        SELECT COUNT(*) as count, SUM(owner_payout) as total
        FROM bookings
        WHERE escrow_status = 'released_to_owner'
          AND payout_status IN ('pending', 'processing')
          AND owner_payout > 0
    ";
    
    $pendingResult = $conn->query($pendingQuery);
    $pendingData = $pendingResult->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'releasable_count' => count($releasableBookings),
        'releasable_bookings' => $releasableBookings,
        'total_releasable_amount' => round($totalAmount, 2),
        'pending_payouts_count' => intval($pendingData['count']),
        'pending_payouts_amount' => round(floatval($pendingData['total']), 2),
        'owners_without_gcash' => $ownersWithoutGcash,
        'warnings' => count($ownersWithoutGcash) > 0 ? [
            'message' => count($ownersWithoutGcash) . ' owner(s) do not have GCash configured',
            'affected_owners' => $ownersWithoutGcash
        ] : null
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking releasable escrows: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
