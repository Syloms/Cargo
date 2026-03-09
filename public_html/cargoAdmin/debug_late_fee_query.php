<?php
$conn = new mysqli('localhost', 'root', '', 'dbcargo');

echo "=== Debugging Late Fee Payments Display ===" . PHP_EOL . PHP_EOL;

echo "1. Direct query to late_fee_payments table:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT * FROM late_fee_payments ORDER BY created_at DESC");
$count = 0;
while($row = mysqli_fetch_assoc($result)) {
    $count++;
    echo "   Record {$count}:" . PHP_EOL;
    echo "     ID: {$row['id']}" . PHP_EOL;
    echo "     Booking: {$row['booking_id']}" . PHP_EOL;
    echo "     Amount: ₱{$row['total_amount']}" . PHP_EOL;
    echo "     Status: {$row['payment_status']}" . PHP_EOL;
    echo "     Created: {$row['created_at']}" . PHP_EOL;
    echo PHP_EOL;
}

if ($count == 0) {
    echo "   (no records)" . PHP_EOL;
}

echo PHP_EOL . "2. What payment.php is querying (checking payments table with metadata):" . PHP_EOL;
$sql = "SELECT p.*, b.id as booking_id
FROM payments p
JOIN bookings b ON p.booking_id = b.id
WHERE 1 
AND p.payment_method = 'gcash'
AND EXISTS (
    SELECT 1 FROM payment_transactions pt 
    WHERE pt.booking_id = p.booking_id 
    AND pt.transaction_type = 'payment'
    AND JSON_EXTRACT(pt.metadata, '$.payment_type') = 'late_fee_payment'
)
ORDER BY p.created_at DESC";

echo "   Query:" . PHP_EOL;
echo "   " . str_replace("\n", "\n   ", $sql) . PHP_EOL . PHP_EOL;

$result = mysqli_query($conn, $sql);
if (!$result) {
    echo "   ✗ Query failed: " . mysqli_error($conn) . PHP_EOL;
} else {
    $count = 0;
    while($row = mysqli_fetch_assoc($result)) {
        $count++;
        echo "   Record {$count}: Payment ID {$row['id']}, Booking {$row['booking_id']}" . PHP_EOL;
    }
    if ($count == 0) {
        echo "   (no records found)" . PHP_EOL;
    }
}

echo PHP_EOL . "3. THE PROBLEM:" . PHP_EOL;
echo "   payment.php is looking in the WRONG table!" . PHP_EOL;
echo "   It's querying 'payments' table, but late fees are now in 'late_fee_payments' table" . PHP_EOL;

echo PHP_EOL . "4. SOLUTION:" . PHP_EOL;
echo "   We need to UPDATE payment.php to query the late_fee_payments table" . PHP_EOL;
echo "   when type=late_fee is selected" . PHP_EOL;

mysqli_close($conn);
?>
