<?php
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/db.php';

function refundEscrowToRenter($bookingId, $reason, $connection = null, $processedBy = null) {
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
            SELECT e.id, e.amount, b.user_id
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
            SET status = 'refunded',
                refunded_at = NOW(),
                refund_reason = ?,
                processed_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $reason, $processedBy, $escrow['id']);
        $stmt->execute();
        
        // Update booking
        $stmt = $connection->prepare("
            UPDATE bookings 
            SET escrow_status = 'refunded',
                payment_status = 'refunded'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        
        // Log
        $stmt = $connection->prepare("
            INSERT INTO payment_transactions 
            (booking_id, transaction_type, amount, description, created_by)
            VALUES (?, 'refund', ?, ?, ?)
        ");
        $stmt->bind_param("idsi", $bookingId, $escrow['amount'], "Refund: $reason", $processedBy);
        $stmt->execute();
        
        // Notify
        $stmt = $connection->prepare("
            INSERT INTO notifications (user_id, title, message)
            VALUES (?, 'Refund Processed 💵', CONCAT('Your refund of ₱', FORMAT(?, 2), ' has been processed.'))
        ");
        $stmt->bind_param("id", $escrow['user_id'], $escrow['amount']);
        $stmt->execute();
        
        mysqli_commit($connection);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        return ['error' => $e->getMessage()];
    } finally {
        if ($shouldClose && $connection) {
            $connection->close();
        }
    }
}