<?php
/**
 * Transaction Logger Class
 * Logs all payment-related transactions for audit trail
 */

class TransactionLogger {
    private $conn;
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }
    
    /**
     * Log a payment transaction
     * 
     * @param int $bookingId The booking ID
     * @param string $transactionType Type: payment, escrow_hold, escrow_release, payout, refund
     * @param float $amount Transaction amount
     * @param string $description Human-readable description
     * @param int|null $createdBy Admin ID who performed the action
     * @param array $metadata Additional data to store as JSON
     * @return bool Success status
     */
    public function log(
        int $bookingId,
        string $transactionType,
        float $amount,
        string $description,
        ?int $createdBy = null,
        array $metadata = []
    ): bool {
        try {
            $metadataJson = !empty($metadata) ? json_encode($metadata) : null;
            
            $stmt = $this->conn->prepare("
                INSERT INTO payment_transactions (
                    booking_id,
                    transaction_type,
                    amount,
                    description,
                    created_by,
                    metadata,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param(
                "isdsss",
                $bookingId,
                $transactionType,
                $amount,
                $description,
                $createdBy,
                $metadataJson
            );
            
            $result = $stmt->execute();
            
            if ($result) {
                error_log("[AUDIT] $transactionType | Booking #$bookingId | ₱$amount | $description");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Transaction Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all transactions for a booking
     * 
     * @param int $bookingId
     * @return array Transaction history
     */
    public function getBookingTransactions(int $bookingId): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    pt.*,
                    a.fullname as admin_name,
                    DATE_FORMAT(pt.created_at, '%M %d, %Y %h:%i %p') as formatted_date
                FROM payment_transactions pt
                LEFT JOIN admin a ON pt.created_by = a.id
                WHERE pt.booking_id = ?
                ORDER BY pt.created_at DESC
            ");
            
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $transactions = [];
            while ($row = $result->fetch_assoc()) {
                if ($row['metadata']) {
                    $row['metadata'] = json_decode($row['metadata'], true);
                }
                $transactions[] = $row;
            }
            
            return $transactions;
            
        } catch (Exception $e) {
            error_log("Get Transactions Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get transaction summary for a booking
     * 
     * @param int $bookingId
     * @return array Summary data
     */
    public function getBookingSummary(int $bookingId): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    transaction_type,
                    SUM(amount) as total_amount,
                    COUNT(*) as transaction_count
                FROM payment_transactions
                WHERE booking_id = ?
                GROUP BY transaction_type
            ");
            
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $summary = [];
            while ($row = $result->fetch_assoc()) {
                $summary[$row['transaction_type']] = [
                    'total' => $row['total_amount'],
                    'count' => $row['transaction_count']
                ];
            }
            
            return $summary;
            
        } catch (Exception $e) {
            error_log("Get Summary Error: " . $e->getMessage());
            return [];
        }
    }
}