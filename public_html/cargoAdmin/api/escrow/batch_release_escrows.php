<?php
/**
 * API Endpoint: Batch Release Escrows
 * Releases multiple escrows at once
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../include/db.php';

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$bookingIds = $input['booking_ids'] ?? [];
$adminId = $input['admin_id'] ?? null;

if (empty($bookingIds)) {
    echo json_encode([
        'success' => false,
        'message' => 'No booking IDs provided'
    ]);
    exit;
}

// Start transaction
$conn->begin_transaction();

$results = [
    'success' => [],
    'failed' => [],
    'total_released' => 0
];

try {
    foreach ($bookingIds as $bookingId) {
        try {
            // Get booking details
            $stmt = $conn->prepare("
                SELECT 
                    b.id,
                    b.owner_id,
                    b.owner_payout,
                    b.platform_fee,
                    b.escrow_status,
                    b.status,
                    b.return_date,
                    u.fullname as owner_name,
                    u.gcash_number,
                    u.gcash_name
                FROM bookings b
                LEFT JOIN users u ON b.owner_id = u.id
                WHERE b.id = ?
            ");
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $booking = $stmt->get_result()->fetch_assoc();
            
            if (!$booking) {
                throw new Exception("Booking not found");
            }
            
            // Validate status
            if ($booking['escrow_status'] !== 'held') {
                throw new Exception("Escrow is not in held status (current: {$booking['escrow_status']})");
            }
            
            if ($booking['status'] !== 'completed') {
                throw new Exception("Booking is not completed (current: {$booking['status']})");
            }
            
            // Check if escrow table exists and update it
            $escrowTableExists = $conn->query("SHOW TABLES LIKE 'escrow'")->num_rows > 0;
            
            if ($escrowTableExists) {
                $stmt = $conn->prepare("
                    UPDATE escrow 
                    SET status = 'released', 
                        released_at = NOW(),
                        release_reason = 'Batch release by admin',
                        processed_by = ?
                    WHERE booking_id = ? AND status = 'held'
                ");
                $stmt->bind_param("ii", $adminId, $bookingId);
                $stmt->execute();
            }
            
            // Update booking
            $stmt = $conn->prepare("
                UPDATE bookings 
                SET escrow_status = 'released_to_owner',
                    escrow_released_at = NOW(),
                    payout_status = 'pending'
                WHERE id = ?
            ");
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            
            // Create payout record if table exists
            $payoutsTableExists = $conn->query("SHOW TABLES LIKE 'payouts'")->num_rows > 0;
            
            if ($payoutsTableExists) {
                // Check if payout already exists
                $stmt = $conn->prepare("SELECT id FROM payouts WHERE booking_id = ?");
                $stmt->bind_param("i", $bookingId);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows === 0) {
                    $stmt = $conn->prepare("
                        INSERT INTO payouts 
                        (booking_id, owner_id, amount, platform_fee, net_amount, payout_method, payout_account, status, scheduled_at, created_at)
                        VALUES (?, ?, ?, ?, ?, 'gcash', ?, 'pending', NOW(), NOW())
                    ");
                    $totalAmount = $booking['owner_payout'] + $booking['platform_fee'];
                    $gcashAccount = $booking['gcash_number'] ?? null;
                    
                    $stmt->bind_param(
                        "iiddds", 
                        $bookingId,
                        $booking['owner_id'],
                        $totalAmount,
                        $booking['platform_fee'],
                        $booking['owner_payout'],
                        $gcashAccount
                    );
                    $stmt->execute();
                }
            }
            
            // Log transaction
            $txTableExists = $conn->query("SHOW TABLES LIKE 'payment_transactions'")->num_rows > 0;
            
            if ($txTableExists) {
                $stmt = $conn->prepare("
                    INSERT INTO payment_transactions 
                    (booking_id, transaction_type, amount, description, created_by, created_at)
                    VALUES (?, 'escrow_release', ?, 'Batch escrow release to owner payout', ?, NOW())
                ");
                $stmt->bind_param("idi", $bookingId, $booking['owner_payout'], $adminId);
                $stmt->execute();
            }
            
            // Send notification
            $notifTableExists = $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0;
            
            if ($notifTableExists) {
                $stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (user_id, title, message, type, created_at)
                    VALUES (?, 'Payment Released 💰', ?, 'payout', NOW())
                ");
                $message = "Your payout of ₱" . number_format($booking['owner_payout'], 2) . " for booking #{$bookingId} is now being processed.";
                $stmt->bind_param("is", $booking['owner_id'], $message);
                $stmt->execute();
            }
            
            $results['success'][] = [
                'booking_id' => $bookingId,
                'owner_name' => $booking['owner_name'],
                'amount' => $booking['owner_payout']
            ];
            
            $results['total_released'] += $booking['owner_payout'];
            
        } catch (Exception $e) {
            $results['failed'][] = [
                'booking_id' => $bookingId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Commit all changes
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => count($results['success']) . ' escrow(s) released successfully',
        'results' => $results
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Batch release failed: ' . $e->getMessage(),
        'results' => $results
    ]);
}

$conn->close();
?>
