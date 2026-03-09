<?php
/**
 * Debug Create Policy - Step by step testing
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$steps = [];

try {
    // Step 1: Load database
    $steps[] = ['step' => 1, 'action' => 'Loading database connection', 'status' => 'trying'];
    require_once __DIR__ . '/../../include/db.php';
    $steps[0]['status'] = 'success';
    
    // Step 2: Check connection
    $steps[] = ['step' => 2, 'action' => 'Checking database connection', 'status' => isset($conn) && $conn ? 'success' : 'failed'];
    
    if (!isset($conn) || !$conn) {
        echo json_encode(['success' => false, 'message' => 'No database connection', 'steps' => $steps]);
        exit;
    }
    
    // Step 3: Check booking exists
    $bookingId = 1;
    $userId = 7;
    
    $steps[] = ['step' => 3, 'action' => "Checking booking $bookingId exists", 'status' => 'trying'];
    $stmt = $conn->prepare("SELECT id, user_id, owner_id, car_id, vehicle_type, pickup_date, return_date, total_amount, insurance_policy_id FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        $steps[2]['status'] = 'failed';
        $steps[2]['details'] = 'Booking not found in database';
        echo json_encode(['success' => false, 'message' => 'Booking not found', 'steps' => $steps]);
        exit;
    }
    
    $steps[2]['status'] = 'success';
    $steps[2]['details'] = $booking;
    
    // Step 4: Check user matches
    $steps[] = ['step' => 4, 'action' => 'Validating user_id matches', 'status' => $booking['user_id'] == $userId ? 'success' : 'failed'];
    
    if ($booking['user_id'] != $userId) {
        echo json_encode(['success' => false, 'message' => "User mismatch: booking belongs to user {$booking['user_id']}, not $userId", 'steps' => $steps]);
        exit;
    }
    
    // Step 5: Check if already has policy
    $steps[] = ['step' => 5, 'action' => 'Checking if policy already exists', 'status' => $booking['insurance_policy_id'] ? 'already exists' : 'ok'];
    
    if ($booking['insurance_policy_id']) {
        echo json_encode(['success' => false, 'message' => 'Policy already exists', 'policy_id' => $booking['insurance_policy_id'], 'steps' => $steps]);
        exit;
    }
    
    // Step 6: Check coverage type exists
    $coverageCode = 'BASIC';
    $steps[] = ['step' => 6, 'action' => 'Checking coverage type exists', 'status' => 'trying'];
    $stmt = $conn->prepare("SELECT * FROM insurance_coverage_types WHERE coverage_code = ? AND is_active = 1");
    $stmt->bind_param("s", $coverageCode);
    $stmt->execute();
    $coverage = $stmt->get_result()->fetch_assoc();
    
    if (!$coverage) {
        $steps[5]['status'] = 'failed';
        echo json_encode(['success' => false, 'message' => 'Coverage type not found', 'steps' => $steps]);
        exit;
    }
    
    $steps[5]['status'] = 'success';
    $steps[5]['details'] = $coverage;
    
    // Step 7: Check provider exists
    $steps[] = ['step' => 7, 'action' => 'Checking for active provider', 'status' => 'trying'];
    $stmt = $conn->prepare("SELECT id, provider_name FROM insurance_providers WHERE status = 'active' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    
    if (!$provider) {
        $steps[6]['status'] = 'failed';
        echo json_encode(['success' => false, 'message' => 'No active provider', 'steps' => $steps]);
        exit;
    }
    
    $steps[6]['status'] = 'success';
    $steps[6]['details'] = $provider;
    
    // Step 8: Calculate premium
    $rentalAmount = floatval($booking['total_amount']);
    $premiumRate = floatval($coverage['base_premium_rate']);
    $premiumAmount = $rentalAmount * $premiumRate;
    
    $steps[] = ['step' => 8, 'action' => 'Calculate premium', 'status' => 'success', 'details' => [
        'rental_amount' => $rentalAmount,
        'premium_rate' => $premiumRate,
        'premium_amount' => $premiumAmount
    ]];
    
    // Step 9: Generate policy number
    $policyNumber = 'INS-' . date('Y') . '-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT) . '-BAS';
    $steps[] = ['step' => 9, 'action' => 'Generate policy number', 'status' => 'success', 'details' => $policyNumber];
    
    // All checks passed
    echo json_encode([
        'success' => true,
        'message' => 'All pre-flight checks passed! Ready to create policy.',
        'ready_to_create' => true,
        'policy_data' => [
            'policy_number' => $policyNumber,
            'provider_id' => $provider['id'],
            'booking_id' => $bookingId,
            'user_id' => $userId,
            'coverage_type' => 'basic',
            'premium_amount' => $premiumAmount,
            'rental_amount' => $rentalAmount
        ],
        'steps' => $steps
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage(), 'steps' => $steps]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'steps' => $steps]);
}
?>
