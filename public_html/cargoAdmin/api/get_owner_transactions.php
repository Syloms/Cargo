<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../include/db.php';

if (!isset($_GET['owner_id'])) {
    echo json_encode(['success' => false, 'message' => 'Owner ID required']);
    exit;
}

$ownerId = intval($_GET['owner_id']);

try {
    // Get all bookings with payment info for owner
    $stmt = $conn->prepare("
        SELECT 
            b.id AS booking_id,
            b.user_id,
            b.car_id,
            b.vehicle_type,
            b.pickup_date,
            b.return_date,
            b.total_amount,
            b.platform_fee,
            b.owner_payout,
            b.status AS booking_status,
            b.payment_status,
            b.escrow_status,
            b.payout_status,
            b.created_at AS booking_date,
            b.payment_verified_at,
            b.escrow_held_at,
            b.escrow_released_at,
            b.payout_reference,
            b.payout_completed_at AS payout_date,
            
            -- Payment details
            p.id AS payment_id,
            p.payment_method,
            p.payment_reference,
            p.payment_date,
            
            -- Car/Motorcycle details
            CASE 
                WHEN b.vehicle_type = 'motorcycle' THEN m.brand
                ELSE c.brand
            END AS brand,
            CASE 
                WHEN b.vehicle_type = 'motorcycle' THEN m.model
                ELSE c.model
            END AS model,
            CASE 
                WHEN b.vehicle_type = 'motorcycle' THEN m.motorcycle_year
                ELSE c.car_year
            END AS vehicle_year,
            CASE 
                WHEN b.vehicle_type = 'motorcycle' THEN m.image
                ELSE c.image
            END AS vehicle_image,
            
            -- Renter details
            u.fullname AS renter_name,
            u.email AS renter_email,
            u.phone AS renter_contact,
            
            -- Escrow details
            e.id AS escrow_id,
            e.held_at AS escrow_held_date,
            e.released_at AS escrow_released_date
            
        FROM bookings b
                        LEFT JOIN payments p 
                ON b.id = p.booking_id 
                AND p.id = (
                SELECT MAX(id) 
                FROM payments 
                WHERE booking_id = b.id
                )

        LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN escrow e ON b.id = e.booking_id
        WHERE b.owner_id = ?
       AND (
            b.payout_status = 'completed'
            OR b.escrow_status IN ('held', 'released_to_owner')
            OR b.payment_status IN ('pending', 'verified', 'paid')
        )


        ORDER BY b.created_at DESC
    ");
    
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    $totalEarnings = 0;
    $pendingPayouts = 0;
    $completedPayouts = 0;
    $totalPlatformFees = 0;
    
    // Count statuses
    $paidCount = 0;
    $pendingCount = 0;
    $escrowedCount = 0;
    $completedCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Format vehicle name
        $vehicleName = trim($row['brand'] . ' ' . $row['model'] . ' ' . $row['vehicle_year']);
        
        // Format vehicle image
        $vehicleImage = $row['vehicle_image'];
        if (!empty($vehicleImage) && strpos($vehicleImage, 'http') !== 0) {
            // Load config if not already loaded
            if (!defined('BASE_URL')) {
                require_once __DIR__ . '/../include/config.php';
            }
            $vehicleImage = BASE_URL . '/' . $vehicleImage;
        }
        
        // Determine status and badge
        $statusBadge = getOwnerTransactionStatus($row);
        
        // Calculate earnings and fees
        $totalAmount = floatval($row['total_amount']);
        $platformFee = floatval($row['platform_fee']);
        $ownerPayout = floatval($row['owner_payout']);
        
        // Update statistics based on status
        $escrowStatus = $row['escrow_status'];
        $paymentStatus = $row['payment_status'];
        $payoutStatus = $row['payout_status'];
        
        if ($escrowStatus === 'released_to_owner' || $payoutStatus === 'completed') {
            $completedPayouts += $ownerPayout;
            $totalEarnings += $ownerPayout;
            $completedCount++;
        } elseif ($escrowStatus === 'held') {
            $pendingPayouts += $ownerPayout;
            $escrowedCount++;
        } elseif ($paymentStatus === 'paid') {
            $paidCount++;
        } else {
            $pendingCount++;
        }
        
        $totalPlatformFees += $platformFee;
        
        $transactions[] = [
            'booking_id' => $row['booking_id'],
            'booking_reference' => '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT),
            
            // Amounts
            'total_amount' => $totalAmount,
            'platform_fee' => $platformFee,
            'owner_payout' => $ownerPayout,
            
            // Vehicle info
            'vehicle_type' => $row['vehicle_type'],
            'vehicle_name' => $vehicleName,
            'vehicle_image' => $vehicleImage,
            
            // Renter info
            'renter_name' => $row['renter_name'],
            'renter_email' => $row['renter_email'],
            'renter_contact' => $row['renter_contact'],
            
            // Dates
            'pickup_date' => $row['pickup_date'],
            'return_date' => $row['return_date'],
            'booking_date' => $row['booking_date'],
            'booking_date_formatted' => date('M d, Y h:i A', strtotime($row['booking_date'])),
            
            // Payment info
            'payment_method' => $row['payment_method'],
            'payment_reference' => $row['payment_reference'],
            'payment_date' => $row['payment_date'],
            
            // Status info
            'booking_status' => $row['booking_status'],
            'payment_status' => $row['payment_status'],
            'escrow_status' => $escrowStatus,
            'payout_status' => $payoutStatus,
            
            // Payout info
            'payout_reference' => $row['payout_reference'],
            'payout_date' => $row['payout_date'],
            'payment_verified_at' => $row['payment_verified_at'],
            'escrow_held_at' => $row['escrow_held_at'],
            'escrow_released_at' => $row['escrow_released_at'],
            
            // Status badge
            'status_badge' => $statusBadge,
            
            // Helper flags
            'is_paid' => !empty($row['payment_verified_at']),
            'is_escrowed' => $escrowStatus === 'held',
            'is_completed' => $escrowStatus === 'released_to_owner' || $payoutStatus === 'completed',
        ];
    }
    
    // Calculate totals
    $totalRevenue = $totalEarnings + $pendingPayouts;
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'statistics' => [
            'total_earnings' => $totalEarnings, // Completed payouts
            'pending_payouts' => $pendingPayouts, // Money in escrow
            'total_revenue' => $totalRevenue, // Total expected
            'platform_fees' => $totalPlatformFees,
            'net_earnings' => $totalEarnings, // After fees (already calculated)
            
            // Counts
            'paid_count' => $paidCount,
            'pending_count' => $pendingCount,
            'escrowed_count' => $escrowedCount,
            'completed_count' => $completedCount, 
            'total_transactions' => count($transactions),
            
            // Averages
            'average_payout' => count($transactions) > 0 ? $totalRevenue / count($transactions) : 0,
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function getOwnerTransactionStatus($booking) {
    $escrowStatus = $booking['escrow_status'];
    $paymentStatus = $booking['payment_status'];
    $payoutStatus = $booking['payout_status'];
    
    // Completed - Money released to owner
    if ($escrowStatus === 'released_to_owner' || $payoutStatus === 'completed') {
        return [
            'label' => 'COMPLETED',
            'sublabel' => 'Payout Released',
            'color' => 'green',
            'icon' => 'check_circle'
        ];
    }
    
    // Escrowed - Money held, waiting for booking completion
    if ($escrowStatus === 'held') {
        return [
            'label' => 'ESCROWED',
            'sublabel' => 'Funds Held',
            'color' => 'blue',
            'icon' => 'lock'
        ];
    }
    
    // Paid - Payment verified, pending escrow
    if ($paymentStatus === 'paid' || !empty($booking['payment_verified_at'])) {
        return [
            'label' => 'PAID',
            'sublabel' => 'Payment Verified',
            'color' => 'green',
            'icon' => 'verified'
        ];
    }
    
    // Pending payment
    if ($paymentStatus === 'pending') {
        return [
            'label' => 'PENDING',
            'sublabel' => 'Awaiting Payment',
            'color' => 'orange',
            'icon' => 'schedule'
        ];
    }
    
    // Processing payout
    if ($payoutStatus === 'processing') {
        return [
            'label' => 'PROCESSING',
            'sublabel' => 'Payout in Progress',
            'color' => 'blue',
            'icon' => 'sync'
        ];
    }
    
    // Default
    return [
        'label' => strtoupper($paymentStatus ?? 'UNKNOWN'),
        'sublabel' => '',
        'color' => 'grey',
        'icon' => 'info'
    ];
}

$conn->close();