<?php
/**
 * AUTO-RELEASE ESCROW CRON JOB
 * Run daily: 0 0 * * * php /path/to/cron/auto_release_escrow.php
 * 
 * Automatically releases escrow 3 days after rental completion
 */

require_once __DIR__ . '/../api/include/db.php';
require_once __DIR__ . '/../api/escrow/release_to_owner.php';
require_once __DIR__ . '/../api/payment/transaction_logger.php';

$logger = new TransactionLogger($conn);

// Find bookings ready for auto-release
$sql = "
    SELECT 
        b.id as booking_id,
        b.owner_id,
        b.owner_payout,
        b.return_date,
        e.id as escrow_id
    FROM bookings b
    INNER JOIN escrow e ON e.booking_id = b.id
    WHERE b.status = 'completed'
      AND b.escrow_status = 'held'
      AND e.status = 'held'
      AND b.return_date < DATE_SUB(NOW(), INTERVAL 3 DAY)
";

$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo "No escrows ready for release.\n";
    exit;
}

echo "Found {$result->num_rows} escrows to release...\n";

while ($booking = $result->fetch_assoc()) {
    echo "Processing booking #{$booking['booking_id']}... ";
    
    try {
        // Release escrow
        $release = releaseEscrowToOwner($booking['booking_id'], $conn, 0); // 0 = automated
        
        if ($release['success']) {
            echo "✓ Released ₱{$booking['owner_payout']} to owner #{$booking['owner_id']}\n";
            
            // Log auto-release
            $logger->log(
                $booking['booking_id'],
                'escrow_release',
                $booking['owner_payout'],
                'Automated escrow release (3 days after return)',
                0, // System user
                [
                    'auto_released' => true,
                    'return_date' => $booking['return_date']
                ]
            );
        } else {
            echo "✗ Failed: {$release['error']}\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\nAuto-release job completed.\n";
$conn->close();