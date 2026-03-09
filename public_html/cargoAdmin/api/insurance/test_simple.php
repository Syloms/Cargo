<?php
/**
 * Simple API Test - Returns JSON to verify APIs work
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require_once __DIR__ . '/../../include/db.php';
    
    if (!isset($conn) || !$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Test 1: Check providers
    $providers = $conn->query("SELECT COUNT(*) as cnt FROM insurance_providers WHERE status = 'active'")->fetch_assoc()['cnt'];
    
    // Test 2: Check coverage types
    $coverageTypes = $conn->query("SELECT COUNT(*) as cnt FROM insurance_coverage_types WHERE is_active = 1")->fetch_assoc()['cnt'];
    
    // Test 3: Check bookings
    $bookings = $conn->query("SELECT id, user_id FROM bookings WHERE status IN ('approved', 'completed') AND insurance_policy_id IS NULL LIMIT 1")->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'message' => 'API is working!',
        'database' => [
            'connected' => true,
            'providers' => $providers,
            'coverage_types' => $coverageTypes,
            'has_test_booking' => $bookings ? true : false,
            'test_booking_id' => $bookings ? $bookings['id'] : null,
            'test_user_id' => $bookings ? $bookings['user_id'] : null
        ],
        'status' => [
            'providers_ok' => $providers > 0,
            'coverage_types_ok' => $coverageTypes >= 4,
            'can_test_create_policy' => $bookings ? true : false
        ],
        'recommendations' => [
            'providers' => $providers > 0 ? 'OK' : 'Run: INSERT INTO insurance_providers',
            'coverage_types' => $coverageTypes >= 4 ? 'OK' : 'Run insurance migration SQL',
            'test_data' => $bookings ? "Use booking_id={$bookings['id']}, user_id={$bookings['user_id']}" : 'Create a test booking first'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
