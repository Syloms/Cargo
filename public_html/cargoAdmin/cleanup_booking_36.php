<?php
$conn = new mysqli('localhost', 'root', '', 'dbcargo');

echo "=== Cleaning up Booking 36 test payment ===" . PHP_EOL . PHP_EOL;

// Delete the wrong payment from regular payments table
$result = mysqli_query($conn, "DELETE FROM payments WHERE id = 87 AND booking_id = 36");
if ($result) {
    echo "✓ Deleted payment ID 87 from 'payments' table" . PHP_EOL;
} else {
    echo "✗ Failed to delete: " . mysqli_error($conn) . PHP_EOL;
}

// Reset booking status
$result = mysqli_query($conn, "UPDATE bookings SET late_fee_payment_status = 'none' WHERE id = 36");
if ($result) {
    echo "✓ Reset booking 36 late_fee_payment_status to 'none'" . PHP_EOL;
} else {
    echo "✗ Failed to update: " . mysqli_error($conn) . PHP_EOL;
}

// Delete the transaction log for that payment
$result = mysqli_query($conn, "DELETE FROM payment_transactions WHERE reference_id = '1212121212121'");
if ($result) {
    echo "✓ Deleted transaction log" . PHP_EOL;
} else {
    echo "✗ Failed to delete log: " . mysqli_error($conn) . PHP_EOL;
}

echo PHP_EOL . "=== Verification ===" . PHP_EOL;

// Check booking status
$result = mysqli_query($conn, "SELECT id, late_fee_amount, late_fee_payment_status, payment_status 
                                FROM bookings WHERE id = 36");
$booking = mysqli_fetch_assoc($result);
echo "Booking 36 status:" . PHP_EOL;
echo "  Late Fee: ₱{$booking['late_fee_amount']}" . PHP_EOL;
echo "  Late Fee Payment Status: {$booking['late_fee_payment_status']}" . PHP_EOL;
echo "  Payment Status: {$booking['payment_status']}" . PHP_EOL;

echo PHP_EOL . "✅ Booking 36 is ready for a fresh test!" . PHP_EOL;
echo "   Please submit the late fee payment again from the Flutter app." . PHP_EOL;
echo "   It should now go to 'late_fee_payments' table." . PHP_EOL;

mysqli_close($conn);
?>
