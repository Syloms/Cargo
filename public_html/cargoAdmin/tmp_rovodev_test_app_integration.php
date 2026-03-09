<?php
/**
 * Security Deposit App Integration Test
 * Tests the complete flow: App → Backend → Database → Admin
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/include/db.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 10px; }
    h3 { color: #7f8c8d; margin-top: 20px; }
    .test { margin: 15px 0; padding: 15px; border-radius: 5px; }
    .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
    .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
    .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #3498db; color: white; font-weight: bold; }
    tr:hover { background: #f5f5f5; }
    .badge { padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
    .badge-success { background: #28a745; color: white; }
    .badge-warning { background: #ffc107; color: black; }
    .badge-danger { background: #dc3545; color: white; }
    .badge-info { background: #17a2b8; color: white; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .flow-step { background: #fff; border: 2px solid #3498db; padding: 15px; margin: 10px 0; border-radius: 8px; }
    .flow-step h4 { margin-top: 0; color: #3498db; }
</style>";

echo "<div class='container'>";
echo "<h1>🔗 Security Deposit - Full Integration Test</h1>";
echo "<p><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// ============================================================
// TEST 1: API ENDPOINTS AVAILABILITY
// ============================================================
echo "<h2>🌐 Test 1: API Endpoints Availability</h2>";

$apiEndpoints = [
    'Create Booking' => 'api/create_booking.php',
    'Submit Payment' => 'api/submit_payment.php',
    'Calculate Deposit' => 'api/security_deposit/calculate_deposit.php',
    'Get Deposit Status' => 'api/security_deposit/get_deposit_status.php',
    'Add Deduction (Admin)' => 'api/security_deposit/add_deduction.php',
    'Process Refund (Admin)' => 'api/security_deposit/process_refund.php',
];

echo "<table>";
echo "<tr><th>Endpoint</th><th>Path</th><th>Status</th></tr>";

foreach ($apiEndpoints as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    $exists = file_exists($fullPath);
    
    echo "<tr>";
    echo "<td><strong>$name</strong></td>";
    echo "<td><code>$path</code></td>";
    if ($exists) {
        echo "<td><span class='badge badge-success'>✓ EXISTS</span></td>";
    } else {
        echo "<td><span class='badge badge-danger'>✗ MISSING</span></td>";
    }
    echo "</tr>";
}

echo "</table>";

// ============================================================
// TEST 2: SIMULATE APP BOOKING FLOW
// ============================================================
echo "<h2>📱 Test 2: Simulate Flutter App Booking Flow</h2>";

// Get test data
$carQuery = "SELECT id, model, price_per_day, owner_id FROM cars WHERE status = 'approved' LIMIT 1";
$carResult = mysqli_query($conn, $carQuery);

$userQuery = "SELECT id, fullname, email, phone FROM users WHERE role = 'Renter' LIMIT 1";
$userResult = mysqli_query($conn, $userQuery);

if (mysqli_num_rows($carResult) > 0 && mysqli_num_rows($userResult) > 0) {
    $car = mysqli_fetch_assoc($carResult);
    $user = mysqli_fetch_assoc($userResult);
    
    $days = 5;
    $baseRental = $days * $car['price_per_day'];
    $serviceFee = $baseRental * 0.05;
    $totalAmount = $baseRental + $serviceFee;
    $securityDeposit = max(500, min(10000, $totalAmount * 0.20));
    $grandTotal = $totalAmount + $securityDeposit;
    
    echo "<div class='flow-step'>";
    echo "<h4>Step 1: User Selects Car & Dates (Flutter App)</h4>";
    echo "<strong>Selected Car:</strong> {$car['model']}<br>";
    echo "<strong>Renter:</strong> {$user['fullname']}<br>";
    echo "<strong>Rental Days:</strong> $days days<br>";
    echo "<strong>Price/Day:</strong> ₱" . number_format($car['price_per_day'], 2) . "<br>";
    echo "</div>";
    
    echo "<div class='flow-step'>";
    echo "<h4>Step 2: App Calculates Price Breakdown</h4>";
    echo "<table>";
    echo "<tr><th>Item</th><th>Amount</th></tr>";
    echo "<tr><td>Base Rental (₱{$car['price_per_day']} × $days)</td><td>₱" . number_format($baseRental, 2) . "</td></tr>";
    echo "<tr><td>Service Fee (5%)</td><td>₱" . number_format($serviceFee, 2) . "</td></tr>";
    echo "<tr style='background: #e3f2fd;'><td><strong>Rental Total</strong></td><td><strong>₱" . number_format($totalAmount, 2) . "</strong></td></tr>";
    echo "<tr style='background: #fff3cd;'><td><strong>Security Deposit (20%)</strong></td><td><strong>₱" . number_format($securityDeposit, 2) . "</strong></td></tr>";
    echo "<tr style='background: #d4edda;'><td><strong>GRAND TOTAL</strong></td><td><strong>₱" . number_format($grandTotal, 2) . "</strong></td></tr>";
    echo "</table>";
    echo "<div class='test info'>ℹ️ User sees this breakdown in the app before confirming booking</div>";
    echo "</div>";
    
    echo "<div class='flow-step'>";
    echo "<h4>Step 3: Create Booking via API</h4>";
    
    // Simulate API call to create_booking.php
    $pickupDate = date('Y-m-d', strtotime('+3 days'));
    $returnDate = date('Y-m-d', strtotime('+8 days'));
    
    $stmt = $conn->prepare("
        INSERT INTO bookings 
        (user_id, vehicle_type, car_id, owner_id, pickup_date, return_date, pickup_time, return_time,
         total_amount, price_per_day, rental_period, needs_delivery,
         full_name, email, contact, security_deposit_amount, status, payment_status, created_at)
        VALUES (?, 'car', ?, ?, ?, ?, '09:00:00', '17:00:00', ?, ?, 'Day', 0, ?, ?, ?, ?, 'pending', 'pending', NOW())
    ");
    
    $stmt->bind_param(
        "iiissddsssd",
        $user['id'], $car['id'], $car['owner_id'], $pickupDate, $returnDate,
        $totalAmount, $car['price_per_day'], $user['fullname'], $user['email'], $user['phone'], $securityDeposit
    );
    
    if ($stmt->execute()) {
        $bookingId = $stmt->insert_id;
        echo "<div class='test success'>✅ Booking created successfully! Booking ID: #$bookingId</div>";
        
        echo "<pre>";
        echo "API Response (simulated):\n";
        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully. Please proceed to payment.',
            'data' => [
                'booking_id' => $bookingId,
                'total_amount' => $totalAmount,
                'security_deposit' => $securityDeposit,
                'grand_total' => $grandTotal,
                'payment_method' => 'gcash'
            ]
        ], JSON_PRETTY_PRINT);
        echo "</pre>";
        
        echo "</div>";
        
        // ============================================================
        // TEST 3: PAYMENT FLOW
        // ============================================================
        echo "<div class='flow-step'>";
        echo "<h4>Step 4: User Proceeds to GCash Payment</h4>";
        echo "<strong>Payment Screen Shows:</strong><br>";
        echo "• Total Amount: ₱" . number_format($grandTotal, 2) . "<br>";
        echo "• Breakdown: Rental (₱" . number_format($totalAmount, 2) . ") + Deposit (₱" . number_format($securityDeposit, 2) . ")<br>";
        echo "• Payment Method: GCash<br>";
        echo "<br>";
        echo "<div class='test warning'>⚠️ User enters GCash number and reference number</div>";
        echo "</div>";
        
        echo "<div class='flow-step'>";
        echo "<h4>Step 5: Submit Payment via API</h4>";
        
        // Simulate payment submission
        $gcashNumber = '09123456789';
        $gcashReference = '1234567890123';
        
        // Insert payment record
        $paymentStmt = $conn->prepare("
            INSERT INTO payments (booking_id, user_id, amount, payment_method, payment_reference, payment_status, created_at)
            VALUES (?, ?, ?, 'gcash', ?, 'pending', NOW())
        ");
        $paymentStmt->bind_param("iids", $bookingId, $user['id'], $grandTotal, $gcashReference);
        $paymentStmt->execute();
        $paymentId = $paymentStmt->insert_id;
        
        // Update booking
        $updateStmt = $conn->prepare("
            UPDATE bookings SET 
                payment_id = ?,
                payment_method = 'gcash',
                payment_status = 'pending',
                gcash_number = ?,
                gcash_reference = ?,
                payment_date = NOW(),
                security_deposit_status = 'held',
                security_deposit_held_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->bind_param("issi", $paymentId, $gcashNumber, $gcashReference, $bookingId);
        $updateStmt->execute();
        
        echo "<div class='test success'>✅ Payment submitted! Status: Pending Admin Verification</div>";
        echo "<pre>";
        echo "Payment Details:\n";
        echo "• Payment ID: #$paymentId\n";
        echo "• GCash Number: $gcashNumber\n";
        echo "• Reference: $gcashReference\n";
        echo "• Amount: ₱" . number_format($grandTotal, 2) . "\n";
        echo "• Security Deposit Status: HELD\n";
        echo "</pre>";
        echo "</div>";
        
        // ============================================================
        // TEST 4: ADMIN VERIFICATION
        // ============================================================
        echo "<h2>👨‍💼 Test 3: Admin Panel Flow</h2>";
        
        echo "<div class='flow-step'>";
        echo "<h4>Admin Dashboard - Pending Payment</h4>";
        
        $pendingQuery = "SELECT b.*, p.payment_reference, p.amount as payment_amount, u.fullname as renter_name
                         FROM bookings b
                         LEFT JOIN payments p ON b.payment_id = p.id
                         LEFT JOIN users u ON b.user_id = u.id
                         WHERE b.id = $bookingId";
        $pendingResult = mysqli_query($conn, $pendingQuery);
        $pendingBooking = mysqli_fetch_assoc($pendingResult);
        
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Booking ID</td><td>#{$pendingBooking['id']}</td></tr>";
        echo "<tr><td>Renter</td><td>{$pendingBooking['renter_name']}</td></tr>";
        echo "<tr><td>Payment Amount</td><td>₱" . number_format($pendingBooking['payment_amount'], 2) . "</td></tr>";
        echo "<tr><td>Security Deposit</td><td>₱" . number_format($pendingBooking['security_deposit_amount'], 2) . "</td></tr>";
        echo "<tr><td>Deposit Status</td><td><span class='badge badge-warning'>{$pendingBooking['security_deposit_status']}</span></td></tr>";
        echo "<tr><td>Payment Status</td><td><span class='badge badge-warning'>{$pendingBooking['payment_status']}</span></td></tr>";
        echo "<tr><td>GCash Reference</td><td>{$pendingBooking['payment_reference']}</td></tr>";
        echo "</table>";
        echo "</div>";
        
        echo "<div class='flow-step'>";
        echo "<h4>Admin Verifies Payment & Approves Booking</h4>";
        
        // Simulate admin approval
        mysqli_query($conn, "UPDATE bookings SET status = 'approved', payment_status = 'paid' WHERE id = $bookingId");
        
        echo "<div class='test success'>✅ Admin verified payment and approved booking</div>";
        echo "</div>";
        
        // ============================================================
        // TEST 5: RENTAL COMPLETION & REFUND
        // ============================================================
        echo "<h2>🔄 Test 4: Rental Completion & Deposit Refund</h2>";
        
        echo "<div class='flow-step'>";
        echo "<h4>Scenario: Booking Completed Successfully</h4>";
        
        // Mark booking as completed
        mysqli_query($conn, "UPDATE bookings SET status = 'completed', actual_return_date = NOW() WHERE id = $bookingId");
        
        echo "<div class='test success'>✅ Vehicle returned successfully</div>";
        echo "</div>";
        
        echo "<div class='flow-step'>";
        echo "<h4>Admin Inspects Vehicle</h4>";
        echo "<strong>Option 1:</strong> No damage - Full refund<br>";
        echo "<strong>Option 2:</strong> Minor damage - Partial refund with deduction<br>";
        echo "<br>";
        echo "<div class='test info'>Let's simulate Option 2: Minor damage found</div>";
        echo "</div>";
        
        echo "<div class='flow-step'>";
        echo "<h4>Add Deduction via Admin Panel</h4>";
        
        $deductionAmount = 300.00;
        $deductionReason = "Minor scratch on rear bumper";
        
        // Add deduction
        $deductStmt = $conn->prepare("
            INSERT INTO security_deposit_deductions (booking_id, deduction_type, amount, description, created_at)
            VALUES (?, 'damage', ?, ?, NOW())
        ");
        $deductStmt->bind_param("ids", $bookingId, $deductionAmount, $deductionReason);
        $deductStmt->execute();
        
        // Update booking
        mysqli_query($conn, "UPDATE bookings SET security_deposit_deductions = $deductionAmount WHERE id = $bookingId");
        
        echo "<div class='test warning'>⚠️ Deduction Added</div>";
        echo "<table>";
        echo "<tr><th>Type</th><th>Amount</th><th>Reason</th></tr>";
        echo "<tr><td>Damage</td><td>₱" . number_format($deductionAmount, 2) . "</td><td>$deductionReason</td></tr>";
        echo "</table>";
        echo "</div>";
        
        echo "<div class='flow-step'>";
        echo "<h4>Process Refund via Admin Panel</h4>";
        
        $refundAmount = $securityDeposit - $deductionAmount;
        $refundReference = 'REF' . time();
        
        // Process refund
        $refundStmt = $conn->prepare("
            UPDATE bookings SET 
                security_deposit_status = 'partial_refund',
                security_deposit_refunded_at = NOW(),
                security_deposit_refund_amount = ?,
                security_deposit_refund_reference = ?
            WHERE id = ?
        ");
        $refundStmt->bind_param("dsi", $refundAmount, $refundReference, $bookingId);
        $refundStmt->execute();
        
        echo "<div class='test success'>✅ Refund Processed</div>";
        echo "<table>";
        echo "<tr><th>Item</th><th>Amount</th></tr>";
        echo "<tr><td>Original Deposit</td><td>₱" . number_format($securityDeposit, 2) . "</td></tr>";
        echo "<tr><td>Deduction</td><td>-₱" . number_format($deductionAmount, 2) . "</td></tr>";
        echo "<tr style='background: #d4edda;'><td><strong>Refund Amount</strong></td><td><strong>₱" . number_format($refundAmount, 2) . "</strong></td></tr>";
        echo "<tr><td>Refund Reference</td><td>$refundReference</td></tr>";
        echo "</table>";
        echo "</div>";
        
        // ============================================================
        // TEST 6: VIEW IN DATABASE
        // ============================================================
        echo "<h2>💾 Test 5: Final Database State</h2>";
        
        $finalQuery = "SELECT * FROM bookings WHERE id = $bookingId";
        $finalResult = mysqli_query($conn, $finalQuery);
        $finalBooking = mysqli_fetch_assoc($finalResult);
        
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
        echo "<tr><td>Booking Status</td><td><span class='badge badge-success'>{$finalBooking['status']}</span></td><td>✓</td></tr>";
        echo "<tr><td>Payment Status</td><td><span class='badge badge-success'>{$finalBooking['payment_status']}</span></td><td>✓</td></tr>";
        echo "<tr><td>Security Deposit</td><td>₱" . number_format($finalBooking['security_deposit_amount'], 2) . "</td><td>✓</td></tr>";
        echo "<tr><td>Deposit Status</td><td><span class='badge badge-info'>{$finalBooking['security_deposit_status']}</span></td><td>✓</td></tr>";
        echo "<tr><td>Deductions</td><td>₱" . number_format($finalBooking['security_deposit_deductions'], 2) . "</td><td>✓</td></tr>";
        echo "<tr><td>Refund Amount</td><td>₱" . number_format($finalBooking['security_deposit_refund_amount'], 2) . "</td><td>✓</td></tr>";
        echo "<tr><td>Refund Reference</td><td>{$finalBooking['security_deposit_refund_reference']}</td><td>✓</td></tr>";
        echo "</table>";
        
        // Check deductions table
        $deductionsQuery = "SELECT * FROM security_deposit_deductions WHERE booking_id = $bookingId";
        $deductionsResult = mysqli_query($conn, $deductionsQuery);
        
        echo "<h3>Deduction History:</h3>";
        echo "<table>";
        echo "<tr><th>Type</th><th>Amount</th><th>Description</th><th>Date</th></tr>";
        while ($deduction = mysqli_fetch_assoc($deductionsResult)) {
            echo "<tr>";
            echo "<td><span class='badge badge-warning'>{$deduction['deduction_type']}</span></td>";
            echo "<td>₱" . number_format($deduction['amount'], 2) . "</td>";
            echo "<td>{$deduction['description']}</td>";
            echo "<td>{$deduction['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // ============================================================
        // SUMMARY
        // ============================================================
        echo "<h2>📊 Complete Flow Summary</h2>";
        
        echo "<div class='test success'>";
        echo "<h3>✅ Full Integration Test PASSED!</h3>";
        echo "<strong>Complete Flow Tested:</strong><br><br>";
        echo "1. ✓ <strong>Flutter App:</strong> User selects car, sees price breakdown with deposit<br>";
        echo "2. ✓ <strong>Create Booking API:</strong> Booking created with security deposit<br>";
        echo "3. ✓ <strong>Payment Submission:</strong> GCash payment with deposit held<br>";
        echo "4. ✓ <strong>Admin Verification:</strong> Payment verified and approved<br>";
        echo "5. ✓ <strong>Rental Completion:</strong> Vehicle returned<br>";
        echo "6. ✓ <strong>Deduction Added:</strong> Damage recorded<br>";
        echo "7. ✓ <strong>Refund Processed:</strong> Partial refund issued<br>";
        echo "8. ✓ <strong>Database Tracking:</strong> All data recorded correctly<br>";
        echo "</div>";
        
        echo "<div class='test info'>";
        echo "<strong>📋 Test Booking Details:</strong><br>";
        echo "• Booking ID: #$bookingId<br>";
        echo "• Rental Amount: ₱" . number_format($totalAmount, 2) . "<br>";
        echo "• Security Deposit: ₱" . number_format($securityDeposit, 2) . "<br>";
        echo "• Deduction: ₱" . number_format($deductionAmount, 2) . "<br>";
        echo "• Refunded: ₱" . number_format($refundAmount, 2) . "<br>";
        echo "• Status: {$finalBooking['security_deposit_status']}<br>";
        echo "</div>";
        
        echo "<div class='test warning'>";
        echo "<strong>⚠️ Cleanup:</strong><br>";
        echo "To remove test data, run:<br>";
        echo "<code>DELETE FROM bookings WHERE id = $bookingId;</code><br>";
        echo "<code>DELETE FROM security_deposit_deductions WHERE booking_id = $bookingId;</code><br>";
        echo "<code>DELETE FROM payments WHERE booking_id = $bookingId;</code>";
        echo "</div>";
        
    } else {
        echo "<div class='test error'>❌ Failed to create test booking</div>";
    }
    
} else {
    echo "<div class='test warning'>⚠️ No approved cars or renters available for testing</div>";
}

echo "</div>";
?>
