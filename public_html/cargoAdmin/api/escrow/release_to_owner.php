<?php
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/db.php';

function releaseEscrowToOwner($bookingId, $connection = null, $processedBy = null) {
    global $conn;
    $shouldClose = false;
    if (!$connection) {
        $connection = $conn; // Use centralized connection
        $shouldClose = false; // Don't close shared connection
    }
    
    try {
        mysqli_begin_transaction($connection);
        
        // Get escrow
        $stmt = $connection->prepare("
            SELECT e.id, e.amount, b.owner_id, b.owner_payout, b.platform_fee
            FROM escrow e
            JOIN bookings b ON b.id = e.booking_id
            WHERE e.booking_id = ? AND e.status = 'held'
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $escrow = $stmt->get_result()->fetch_assoc();
        
        if (!$escrow) {
            throw new Exception("No held escrow found");
        }
        
        // Update escrow
        $stmt = $connection->prepare("
            UPDATE escrow 
            SET status = 'released', 
                released_at = NOW(),
                release_reason = 'Rental completed',
                processed_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $processedBy, $escrow['id']);
        $stmt->execute();
        
        // Update booking
        $stmt = $connection->prepare("
            UPDATE bookings 
            SET escrow_status = 'released_to_owner',
                escrow_released_at = NOW(),
                payout_status = 'pending'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        
        // Create payout
        $stmt = $connection->prepare("
            INSERT INTO payouts 
            (booking_id, owner_id, escrow_id, amount, platform_fee, net_amount, status, scheduled_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("iiiddd", 
            $bookingId,
            $escrow['owner_id'],
            $escrow['id'],
            $escrow['amount'],
            $escrow['platform_fee'],
            $escrow['owner_payout']
        );
        $stmt->execute();
        
        // Log
        $stmt = $connection->prepare("
            INSERT INTO payment_transactions 
            (booking_id, transaction_type, amount, description, created_by)
            VALUES (?, 'escrow_release', ?, 'Escrow released to owner', ?)
        ");
        $stmt->bind_param("idi", $bookingId, $escrow['owner_payout'], $processedBy);
        $stmt->execute();
        
        // Notify owner
        $stmt = $connection->prepare("
            INSERT INTO notifications (user_id, title, message)
            VALUES (?, 'Payment Released 💰', CONCAT('Your payout of ₱', FORMAT(?, 2), ' for booking #', ?, ' is being processed.'))
        ");
        $stmt->bind_param("idi", $escrow['owner_id'], $escrow['owner_payout'], $bookingId);
        $stmt->execute();
        
        mysqli_commit($connection);
        
        return ['success' => true, 'payout_amount' => $escrow['owner_payout']];
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        error_log("Release failed: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    } finally {
        if ($shouldClose && $connection) {
            $connection->close();
        }
    }
}

// API endpoint
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    session_start();
    
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $bookingId = $input['booking_id'] ?? null;
    
    if (!$bookingId) {
        echo json_encode(['error' => 'Missing booking_id']);
        exit;
    }
    
    $result = releaseEscrowToOwner($bookingId, null, $_SESSION['admin_id']);
    echo json_encode($result);
}