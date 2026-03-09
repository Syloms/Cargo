<?php
/**
 * Purchase Insurance Policy
 * Wrapper endpoint for Flutter app - redirects to create_policy.php logic
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../include/db.php';

try {
    // Get input data
    $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $ownerId = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : 0;
    $coverageTypeId = isset($_POST['coverage_type_id']) ? intval($_POST['coverage_type_id']) : 0;
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : '';

    // Validation
    if ($bookingId <= 0 || $ownerId <= 0 || $coverageTypeId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing required parameters'
        ]);
        exit;
    }

    // Get coverage type details
    $coverageQuery = "SELECT * FROM insurance_coverage_types WHERE id = ?";
    $stmt = $conn->prepare($coverageQuery);
    $stmt->bind_param("i", $coverageTypeId);
    $stmt->execute();
    $coverage = $stmt->get_result()->fetch_assoc();

    if (!$coverage) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid coverage type'
        ]);
        exit;
    }

    // Get booking details
    $bookingQuery = "SELECT * FROM bookings WHERE id = ? AND owner_id = ?";
    $stmt = $conn->prepare($bookingQuery);
    $stmt->bind_param("ii", $bookingId, $ownerId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo json_encode([
            'success' => false,
            'error' => 'Booking not found or unauthorized'
        ]);
        exit;
    }

    // Use booking dates if not provided
    if (empty($startDate)) $startDate = $booking['pickup_date'];
    if (empty($endDate)) $endDate = $booking['return_date'];

    // Calculate premium
    $days = (strtotime($endDate) - strtotime($startDate)) / 86400;
    $premium = $coverage['daily_rate'] * max(1, $days);

    // Generate policy number
    $policyNumber = 'POL-' . strtoupper(uniqid());

    // Create policy
    $insertQuery = "
        INSERT INTO insurance_policies (
            policy_number, booking_id, owner_id, coverage_type_id,
            premium, start_date, end_date, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ";

    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("siiidss", $policyNumber, $bookingId, $ownerId, $coverageTypeId, $premium, $startDate, $endDate);
    
    if ($stmt->execute()) {
        $policyId = $conn->insert_id;

        // Get the created policy
        $getPolicyQuery = "SELECT * FROM insurance_policies WHERE id = ?";
        $stmt = $conn->prepare($getPolicyQuery);
        $stmt->bind_param("i", $policyId);
        $stmt->execute();
        $policy = $stmt->get_result()->fetch_assoc();

        echo json_encode([
            'success' => true,
            'message' => 'Insurance policy purchased successfully',
            'policy' => $policy,
            'policy_id' => $policyId,
            'policy_number' => $policyNumber
        ]);
    } else {
        throw new Exception('Failed to create policy: ' . $conn->error);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
