<?php
$conn = new mysqli('localhost', 'root', '', 'dbcargo');

echo "=== Checking Booking 36 Payment ===" . PHP_EOL . PHP_EOL;

echo "1. Regular payments table:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT id, booking_id, amount, payment_status, payment_reference, created_at 
                                FROM payments 
                                WHERE booking_id = 36 
                                ORDER BY created_at DESC LIMIT 5");
$count = 0;
while($row = mysqli_fetch_assoc($result)) {
    $count++;
    echo "   ID: {$row['id']}, Amount: ₱{$row['amount']}, Status: {$row['payment_status']}, ";
    echo "Ref: {$row['payment_reference']}, Created: {$row['created_at']}" . PHP_EOL;
}
if ($count == 0) echo "   (no records)" . PHP_EOL;

echo PHP_EOL . "2. Late fee payments table:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT id, booking_id, total_amount, payment_status, payment_reference, created_at 
                                FROM late_fee_payments 
                                WHERE booking_id = 36 
                                ORDER BY created_at DESC LIMIT 5");
$count = 0;
while($row = mysqli_fetch_assoc($result)) {
    $count++;
    echo "   ID: {$row['id']}, Amount: ₱{$row['total_amount']}, Status: {$row['payment_status']}, ";
    echo "Ref: {$row['payment_reference']}, Created: {$row['created_at']}" . PHP_EOL;
}
if ($count == 0) echo "   (no records)" . PHP_EOL;

echo PHP_EOL . "3. Booking 36 details:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT id, late_fee_amount, late_fee_payment_status, payment_status, late_fee_charged 
                                FROM bookings WHERE id = 36");
$row = mysqli_fetch_assoc($result);
echo "   Late Fee: ₱{$row['late_fee_amount']}" . PHP_EOL;
echo "   Late Fee Payment Status: {$row['late_fee_payment_status']}" . PHP_EOL;
echo "   Payment Status: {$row['payment_status']}" . PHP_EOL;
echo "   Late Fee Charged: {$row['late_fee_charged']}" . PHP_EOL;

mysqli_close($conn);
?>
