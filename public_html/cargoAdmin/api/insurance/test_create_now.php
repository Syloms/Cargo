<?php
/**
 * Test Create Policy - Actually attempt to create it
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../include/db.php';
    
    // Simulate the POST request
    $bookingId = 1;
    $userId = 7;
    $coverageType = 'basic';
    
    mysqli_begin_transaction($conn);
    
    // Get booking
    $stmt = $conn->prepare("SELECT b.id, b.user_id, b.owner_id, b.car_id, b.vehicle_type, b.pickup_date, b.return_date, b.total_amount, b.insurance_policy_id FROM bookings b WHERE b.id = ? AND b.user_id = ?");
    $stmt->bind_param("ii", $bookingId, $userId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    if ($booking['insurance_policy_id']) {
        throw new Exception('Policy already exists with ID: ' . $booking['insurance_policy_id']);
    }
    
    // Get coverage
    $coverageCode = 'BASIC';
    $stmt = $conn->prepare("SELECT * FROM insurance_coverage_types WHERE coverage_code = ? AND is_active = 1");
    $stmt->bind_param("s", $coverageCode);
    $stmt->execute();
    $coverage = $stmt->get_result()->fetch_assoc();
    
    // Get provider
    $stmt = $conn->prepare("SELECT id FROM insurance_providers WHERE status = 'active' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    $providerId = $provider['id'];
    
    // Calculate
    $rentalAmount = floatval($booking['total_amount']);
    $premiumRate = floatval($coverage['base_premium_rate']);
    $premiumAmount = $rentalAmount * $premiumRate;
    
    // Limits
    $limits = ['limit' => 100000, 'collision' => 50000, 'liability' => 50000, 'theft' => 0, 'injury' => 0, 'deductible' => 5000];
    
    // Policy number
    $policyNumber = 'INS-' . date('Y') . '-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT) . '-BAS';
    
    // Check table structure first
    $result = $conn->query("DESCRIBE insurance_policies");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    // Try INSERT
    $sql = "INSERT INTO insurance_policies (
        policy_number, provider_id, booking_id, vehicle_type, vehicle_id,
        user_id, owner_id, coverage_type, policy_start, policy_end,
        premium_amount, coverage_limit, deductible,
        collision_coverage, liability_coverage, theft_coverage,
        personal_injury_coverage, roadside_assistance,
        status, issued_at, terms_accepted, terms_accepted_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), 1, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $roadsideAssistance = 0;
    
    // Count: s=string, i=integer, d=decimal
    // policy_number(s), provider_id(i), booking_id(i), vehicle_type(s), vehicle_id(i),
    // user_id(i), owner_id(i), coverage_type(s), policy_start(s), policy_end(s),
    // premium_amount(d), coverage_limit(d), deductible(d),
    // collision_coverage(d), liability_coverage(d), theft_coverage(d),
    // personal_injury_coverage(d), roadside_assistance(i)
    
    // 18 parameters: s i i s i i i s s s d d d d d d d i
    $stmt->bind_param(
        "siisiiiissdddddddi",
        $policyNumber,           // 1. s - policy_number
        $providerId,             // 2. i - provider_id
        $bookingId,              // 3. i - booking_id
        $booking['vehicle_type'], // 4. s - vehicle_type
        $booking['car_id'],      // 5. i - vehicle_id
        $booking['user_id'],     // 6. i - user_id
        $booking['owner_id'],    // 7. i - owner_id
        $coverageType,           // 8. i - coverage_type
        $booking['pickup_date'], // 9. s - policy_start
        $booking['return_date'], // 10. s - policy_end
        $premiumAmount,          // 11. d - premium_amount
        $limits['limit'],        // 12. d - coverage_limit
        $limits['deductible'],   // 13. d - deductible
        $limits['collision'],    // 14. d - collision_coverage
        $limits['liability'],    // 15. d - liability_coverage
        $limits['theft'],        // 16. d - theft_coverage
        $limits['injury'],       // 17. d - personal_injury_coverage
        $roadsideAssistance      // 18. i - roadside_assistance
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $policyId = $stmt->insert_id;
    
    // Update booking
    $stmt = $conn->prepare("UPDATE bookings SET insurance_policy_id = ?, insurance_premium = ?, insurance_coverage_type = ?, insurance_verified = 1 WHERE id = ?");
    $stmt->bind_param("idsi", $policyId, $premiumAmount, $coverageType, $bookingId);
    $stmt->execute();
    
    // Log action
    $stmt = $conn->prepare("INSERT INTO insurance_audit_log (policy_id, action_type, action_by, action_details) VALUES (?, 'policy_created', ?, ?)");
    $actionDetails = json_encode(['coverage_type' => $coverageType, 'premium_amount' => $premiumAmount, 'policy_number' => $policyNumber]);
    $stmt->bind_param("iis", $policyId, $userId, $actionDetails);
    $stmt->execute();
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Policy created successfully!',
        'policy_id' => $policyId,
        'policy_number' => $policyNumber,
        'premium_amount' => $premiumAmount,
        'table_columns' => $columns,
        'booking_data' => $booking
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
