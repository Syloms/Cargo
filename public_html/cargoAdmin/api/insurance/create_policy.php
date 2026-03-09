<?php
/**
 * Insurance Policy Creation API
 * Creates an insurance policy for a booking
 */

// Error handling - ensure JSON output even on errors
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once __DIR__ . '/../../include/db.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Required fields
$bookingId = isset($input['booking_id']) ? intval($input['booking_id']) : 0;
$coverageType = $input['coverage_type'] ?? 'basic';
$userId = isset($input['user_id']) ? intval($input['user_id']) : 0;

// Validation
if ($bookingId <= 0 || $userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($coverageType, ['basic', 'standard', 'premium', 'comprehensive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid coverage type']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Get booking details
    $stmt = $conn->prepare("
        SELECT 
            b.id, b.user_id, b.owner_id, b.car_id, b.vehicle_type,
            b.pickup_date, b.return_date, b.total_amount,
            b.insurance_policy_id
        FROM bookings b
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->bind_param("ii", $bookingId, $userId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        throw new Exception('Booking not found or unauthorized');
    }
    
    if ($booking['insurance_policy_id']) {
        throw new Exception('Insurance policy already exists for this booking');
    }
    
    // 2. Get coverage details
    $stmt = $conn->prepare("
        SELECT * FROM insurance_coverage_types 
        WHERE coverage_code = ? AND is_active = 1
    ");
    $coverageCode = strtoupper($coverageType);
    $stmt->bind_param("s", $coverageCode);
    $stmt->execute();
    $coverage = $stmt->get_result()->fetch_assoc();
    
    if (!$coverage) {
        throw new Exception('Coverage type not found');
    }
    
    // 3. Get default provider
    $stmt = $conn->prepare("
        SELECT id FROM insurance_providers 
        WHERE status = 'active' 
        ORDER BY id ASC 
        LIMIT 1
    ");
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    
    if (!$provider) {
        throw new Exception('No active insurance provider available');
    }
    
    $providerId = $provider['id'];
    
    // 4. Calculate premium
    $rentalAmount = floatval($booking['total_amount']);
    $premiumRate = floatval($coverage['base_premium_rate']);
    $premiumAmount = $rentalAmount * $premiumRate;
    
    // 5. Set coverage limits based on type
    $coverageLimits = [
        'basic' => ['limit' => 100000, 'collision' => 50000, 'liability' => 50000, 'theft' => 0, 'injury' => 0, 'deductible' => 5000],
        'standard' => ['limit' => 300000, 'collision' => 150000, 'liability' => 100000, 'theft' => 50000, 'injury' => 0, 'deductible' => 3000],
        'premium' => ['limit' => 500000, 'collision' => 250000, 'liability' => 150000, 'theft' => 75000, 'injury' => 25000, 'deductible' => 2000],
        'comprehensive' => ['limit' => 1000000, 'collision' => 500000, 'liability' => 300000, 'theft' => 150000, 'injury' => 50000, 'deductible' => 1000]
    ];
    
    $limits = $coverageLimits[$coverageType];
    
    // 6. Generate policy number
    $policyNumber = 'INS-' . date('Y') . '-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT) . '-' . strtoupper(substr($coverageType, 0, 3));
    
    // 7. Create insurance policy
    $stmt = $conn->prepare("
        INSERT INTO insurance_policies (
            policy_number, provider_id, booking_id, vehicle_type, vehicle_id,
            user_id, owner_id, coverage_type, policy_start, policy_end,
            premium_amount, coverage_limit, deductible,
            collision_coverage, liability_coverage, theft_coverage,
            personal_injury_coverage, roadside_assistance,
            status, issued_at, terms_accepted, terms_accepted_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?,
            'active', NOW(), 1, NOW()
        )
    ");
    
    $roadsideAssistance = ($coverageType === 'comprehensive') ? 1 : 0;
    
    // Format string: 18 parameters total
    // Pattern: s i i s i i i s s s d d d d d d d i
    $stmt->bind_param(
        "siisiiiissdddddddi",
        $policyNumber,           // 1. s - policy_number
        $providerId,             // 2. i - provider_id
        $bookingId,              // 3. i - booking_id
        $booking['vehicle_type'], // 4. s - vehicle_type
        $booking['car_id'],      // 5. i - vehicle_id
        $booking['user_id'],     // 6. i - user_id
        $booking['owner_id'],    // 7. i - owner_id
        $coverageType,           // 8. s - coverage_type
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
        throw new Exception('Failed to create insurance policy');
    }
    
    $policyId = $stmt->insert_id;
    
    // 8. Update booking with insurance info
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET insurance_policy_id = ?,
            insurance_premium = ?,
            insurance_coverage_type = ?,
            insurance_verified = 1
        WHERE id = ?
    ");
    $stmt->bind_param("idsi", $policyId, $premiumAmount, $coverageType, $bookingId);
    $stmt->execute();
    
    // 9. Log the action
    $stmt = $conn->prepare("
        INSERT INTO insurance_audit_log (policy_id, action_type, action_by, action_details)
        VALUES (?, 'policy_created', ?, ?)
    ");
    $actionDetails = json_encode([
        'coverage_type' => $coverageType,
        'premium_amount' => $premiumAmount,
        'policy_number' => $policyNumber
    ]);
    $stmt->bind_param("iis", $policyId, $userId, $actionDetails);
    $stmt->execute();
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Insurance policy created successfully',
        'data' => [
            'policy_id' => $policyId,
            'policy_number' => $policyNumber,
            'coverage_type' => $coverageType,
            'premium_amount' => $premiumAmount,
            'coverage_limit' => $limits['limit'],
            'deductible' => $limits['deductible'],
            'collision_coverage' => $limits['collision'],
            'liability_coverage' => $limits['liability'],
            'theft_coverage' => $limits['theft'],
            'personal_injury_coverage' => $limits['injury'],
            'roadside_assistance' => $roadsideAssistance,
            'policy_start' => $booking['pickup_date'],
            'policy_end' => $booking['return_date']
        ]
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
