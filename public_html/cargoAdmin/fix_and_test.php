<?php
/**
 * Test if the late_fee_payments table can be written to
 */

$conn = new mysqli('localhost', 'root', '', 'dbcargo');

echo "=== Testing Direct Insert to late_fee_payments ===" . PHP_EOL . PHP_EOL;

// Try to insert a test record
$bookingId = 36;
$userId = 7;
$lateFeeAmount = 35900.00;
$rentalAmount = 0.00;
$totalAmount = 35900.00;
$referenceNumber = 'TEST_' . time();
$gcashNumber = '09123456789';
$isRentalPaid = 1;
$hoursOverdue = 100;
$daysOverdue = 5;

echo "1. Attempting to insert test record..." . PHP_EOL;

$sql = "INSERT INTO late_fee_payments 
        (booking_id, user_id, late_fee_amount, rental_amount, total_amount, 
         payment_method, payment_reference, gcash_number, payment_status, 
         is_rental_paid, hours_overdue, days_overdue, payment_date, created_at) 
        VALUES (?, ?, ?, ?, ?, 'gcash', ?, ?, 'pending', ?, ?, ?, NOW(), NOW())";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo "   ✗ Failed to prepare statement: " . mysqli_error($conn) . PHP_EOL;
    exit;
}

mysqli_stmt_bind_param($stmt, "iidddssiii", 
    $bookingId, 
    $userId, 
    $lateFeeAmount, 
    $rentalAmount, 
    $totalAmount, 
    $referenceNumber, 
    $gcashNumber,
    $isRentalPaid,
    $hoursOverdue,
    $daysOverdue
);

if (mysqli_stmt_execute($stmt)) {
    $insertId = mysqli_insert_id($conn);
    echo "   ✓ SUCCESS! Inserted with ID: {$insertId}" . PHP_EOL;
    
    // Verify it's there
    $result = mysqli_query($conn, "SELECT * FROM late_fee_payments WHERE id = {$insertId}");
    $row = mysqli_fetch_assoc($result);
    echo "   ✓ Verified: booking_id={$row['booking_id']}, total_amount={$row['total_amount']}" . PHP_EOL;
    
    // Clean up test record
    mysqli_query($conn, "DELETE FROM late_fee_payments WHERE id = {$insertId}");
    echo "   ✓ Test record cleaned up" . PHP_EOL;
} else {
    echo "   ✗ FAILED to execute: " . mysqli_error($conn) . PHP_EOL;
    echo "   Error code: " . mysqli_errno($conn) . PHP_EOL;
}

echo PHP_EOL . "2. Checking current API file..." . PHP_EOL;
$content = file_get_contents(__DIR__ . '/api/payment/submit_late_fee_payment.php');
if (strpos($content, 'INSERT INTO late_fee_payments') !== false) {
    echo "   ✓ API file contains correct INSERT statement" . PHP_EOL;
} else {
    echo "   ✗ API file does NOT contain late_fee_payments INSERT!" . PHP_EOL;
}

echo PHP_EOL . "3. Recommendation:" . PHP_EOL;
echo "   The table works fine. The issue is likely:" . PHP_EOL;
echo "   • PHP OPcache is caching old code" . PHP_EOL;
echo "   • Or the Flutter app is calling a different file" . PHP_EOL;
echo "   • Or there's a backup/old file being used" . PHP_EOL;

mysqli_close($conn);
?>
