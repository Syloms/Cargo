<?php
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/db.php';

function holdFundsInEscrow($bookingId, $connection = null) {
    global $conn;
    $shouldClose = false;
    if (!$connection) {
        $connection = $conn; // Use centralized connection
        $shouldClose = false; // Don't close shared connection
    }
    
    try {
        mysqli_begin_transaction($connection);
        
        // Get booking and payment
        $stmt = $connection->prepare("
            SELECT b.total_amount, b.owner_id, p.id as payment_id
            FROM bookings b
            JOIN payments p ON p.booking_id = b.id
            WHERE b.id = ? AND p.payment_status = 'verified'
            LIMIT 1
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Booking or verified payment not found");
        }
        
        $data = $result->fetch_assoc();
        
        // Calculate fees (10% platform commission)
        $totalAmount = $data['total_amount'];
        $platformFee = $totalAmount * 0.10;
        $ownerPayout = $totalAmount - $platformFee;
        
        // Insert into escrow
        $stmt = $connection->prepare("
            INSERT INTO escrow (booking_id, payment_id, amount, status, held_at)
            VALUES (?, ?, ?, 'held', NOW())
        ");
        $stmt->bind_param("iid", $bookingId, $data['payment_id'], $totalAmount);
        $stmt->execute();
        
        // Update booking
        $stmt = $connection->prepare("
            UPDATE bookings 
            SET escrow_status = 'held',
                platform_fee = ?,
                owner_payout = ?,
                escrow_held_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ddi", $platformFee, $ownerPayout, $bookingId);
        $stmt->execute();
        
        // Log
        $stmt = $connection->prepare("
            INSERT INTO payment_transactions 
            (booking_id, transaction_type, amount, description)
            VALUES (?, 'escrow_hold', ?, 'Funds held in escrow')
        ");
        $stmt->bind_param("id", $bookingId, $totalAmount);
        $stmt->execute();
        
        mysqli_commit($connection);
        
        error_log("Escrow: Held ₱$totalAmount for booking #$bookingId");
        
        return [
            'success' => true,
            'platform_fee' => $platformFee,
            'owner_payout' => $ownerPayout
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        error_log("Escrow hold failed: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    } finally {
        if ($shouldClose && $connection) {
            $connection->close();
        }
    }
}