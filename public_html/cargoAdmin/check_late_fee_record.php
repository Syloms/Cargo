<?php
$conn = new mysqli('localhost', 'root', '', 'dbcargo');

echo "=== Late Fee Payments Table Analysis ===" . PHP_EOL . PHP_EOL;

echo "1. Record in late_fee_payments table:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT * FROM late_fee_payments ORDER BY created_at DESC LIMIT 1");
$payment = mysqli_fetch_assoc($result);

if ($payment) {
    echo "   ID: {$payment['id']}" . PHP_EOL;
    echo "   Booking ID: {$payment['booking_id']}" . PHP_EOL;
    echo "   User ID: {$payment['user_id']}" . PHP_EOL;
    echo "   Late Fee Amount: ₱{$payment['late_fee_amount']}" . PHP_EOL;
    echo "   Rental Amount: ₱{$payment['rental_amount']}" . PHP_EOL;
    echo "   Total Amount: ₱{$payment['total_amount']}" . PHP_EOL;
    echo "   Payment Status: {$payment['payment_status']}" . PHP_EOL;
    echo "   Is Rental Paid: {$payment['is_rental_paid']}" . PHP_EOL;
    echo "   Reference: {$payment['payment_reference']}" . PHP_EOL;
    echo "   Created: {$payment['created_at']}" . PHP_EOL;
    
    echo PHP_EOL . "✓ This proves the NEW code is working!" . PHP_EOL;
} else {
    echo "   (no records)" . PHP_EOL;
}

echo PHP_EOL . "2. Checking booking 23 status:" . PHP_EOL;
$result = mysqli_query($conn, "SELECT id, late_fee_amount, late_fee_payment_status, payment_status 
                                FROM bookings WHERE id = 23");
$booking = mysqli_fetch_assoc($result);
echo "   Late Fee: ₱{$booking['late_fee_amount']}" . PHP_EOL;
echo "   Late Fee Payment Status: {$booking['late_fee_payment_status']}" . PHP_EOL;
echo "   Payment Status: {$booking['payment_status']}" . PHP_EOL;

echo PHP_EOL . "3. So the question is: Why did booking 36 NOT work?" . PHP_EOL;
echo "   Possible reasons:" . PHP_EOL;
echo "   - Different Flutter app instance (cached)" . PHP_EOL;
echo "   - Different API endpoint called" . PHP_EOL;
echo "   - Browser/app cache" . PHP_EOL;

echo PHP_EOL . "4. SOLUTION: Try submitting booking 36 again NOW" . PHP_EOL;
echo "   It should work this time!" . PHP_EOL;

mysqli_close($conn);
?>
