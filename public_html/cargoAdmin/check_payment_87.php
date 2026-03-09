<?php
$conn = new mysqli('localhost', 'root', '', 'dbcargo');

echo "=== Investigating Payment ID 87 ===" . PHP_EOL . PHP_EOL;

// Check the payment details
echo "1. Payment details:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT * FROM payments WHERE id = 87");
$payment = mysqli_fetch_assoc($result);
echo "   ID: {$payment['id']}" . PHP_EOL;
echo "   Booking ID: {$payment['booking_id']}" . PHP_EOL;
echo "   Amount: ₱{$payment['amount']}" . PHP_EOL;
echo "   Reference: {$payment['payment_reference']}" . PHP_EOL;
echo "   Created: {$payment['created_at']}" . PHP_EOL;

// Check transaction log
echo PHP_EOL . "2. Transaction log:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT * FROM payment_transactions 
                                WHERE reference_id = '{$payment['payment_reference']}' 
                                OR (booking_id = 36 AND created_at >= '{$payment['created_at']}')
                                ORDER BY created_at DESC LIMIT 3");
$count = 0;
while($row = mysqli_fetch_assoc($result)) {
    $count++;
    echo "   Type: {$row['transaction_type']}" . PHP_EOL;
    echo "   Description: {$row['description']}" . PHP_EOL;
    echo "   Metadata: {$row['metadata']}" . PHP_EOL;
    echo "   Created: {$row['created_at']}" . PHP_EOL;
    echo "   ---" . PHP_EOL;
}
if ($count == 0) echo "   (no transaction log)" . PHP_EOL;

// Check if this was supposed to be a late fee payment
echo PHP_EOL . "3. Analysis:" . PHP_EOL;
if ($payment['amount'] == 35900.00) {
    echo "   ⚠ This amount matches the late fee for booking 36!" . PHP_EOL;
    echo "   ⚠ This should have gone to late_fee_payments table" . PHP_EOL;
    echo "   ⚠ The old code or cached version was executed" . PHP_EOL;
}

// Check what endpoint was likely called
echo PHP_EOL . "4. Likely cause:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT metadata FROM payment_transactions 
                                WHERE booking_id = 36 
                                AND created_at >= '{$payment['created_at']}'
                                ORDER BY created_at DESC LIMIT 1");
if ($row = mysqli_fetch_assoc($result)) {
    $metadata = json_decode($row['metadata'], true);
    if (isset($metadata['payment_type']) && $metadata['payment_type'] == 'late_fee_payment') {
        echo "   ✓ Metadata shows it was marked as late_fee_payment" . PHP_EOL;
        echo "   ✓ But it still went to wrong table!" . PHP_EOL;
    } else {
        echo "   ✗ No late_fee_payment marker in metadata" . PHP_EOL;
        echo "   ✗ Wrong endpoint was called or old code executed" . PHP_EOL;
    }
} else {
    echo "   ⚠ No transaction log with metadata found" . PHP_EOL;
    echo "   ⚠ This suggests old code was used (no logging)" . PHP_EOL;
}

mysqli_close($conn);
?>
