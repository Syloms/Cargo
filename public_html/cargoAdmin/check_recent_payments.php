<?php
$conn = new mysqli('localhost', 'root', '', 'dbcargo');

echo "=== Checking Recent Payments ===" . PHP_EOL . PHP_EOL;

echo "1. Recent payments in REGULAR payments table:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT id, booking_id, amount, payment_status, payment_reference, created_at 
                                FROM payments 
                                ORDER BY created_at DESC LIMIT 5");
while($row = mysqli_fetch_assoc($result)) {
    echo "   ID: {$row['id']}, Booking: {$row['booking_id']}, Amount: ₱{$row['amount']}, Created: {$row['created_at']}" . PHP_EOL;
}

echo PHP_EOL . "2. Records in LATE_FEE_PAYMENTS table:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM late_fee_payments");
$count = mysqli_fetch_assoc($result)['count'];
echo "   Total: {$count}" . PHP_EOL;

if ($count > 0) {
    $result = mysqli_query($conn, "SELECT id, booking_id, total_amount, payment_status, created_at 
                                    FROM late_fee_payments 
                                    ORDER BY created_at DESC LIMIT 5");
    while($row = mysqli_fetch_assoc($result)) {
        echo "   ID: {$row['id']}, Booking: {$row['booking_id']}, Amount: ₱{$row['total_amount']}, Created: {$row['created_at']}" . PHP_EOL;
    }
}

mysqli_close($conn);
?>
