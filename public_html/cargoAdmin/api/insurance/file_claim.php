<?php
/**
 * File Insurance Claim
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
$policyId = isset($input['policy_id']) ? intval($input['policy_id']) : 0;
$bookingId = isset($input['booking_id']) ? intval($input['booking_id']) : 0;
$userId = isset($input['user_id']) ? intval($input['user_id']) : 0;
$claimType = $input['claim_type'] ?? '';
$incidentDate = $input['incident_date'] ?? '';
$incidentDescription = $input['incident_description'] ?? '';
$claimedAmount = isset($input['claimed_amount']) ? floatval($input['claimed_amount']) : 0;

// Optional fields
$incidentLocation = $input['incident_location'] ?? '';
$policeReportNumber = $input['police_report_number'] ?? null;
$evidencePhotos = $input['evidence_photos'] ?? []; // Array of photo paths

// Validation
if ($policyId <= 0 || $bookingId <= 0 || $userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required IDs']);
    exit;
}

if (!in_array($claimType, ['collision', 'theft', 'liability', 'personal_injury', 'property_damage', 'other'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid claim type']);
    exit;
}

if (empty($incidentDescription) || $claimedAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing incident details or invalid amount']);
    exit;
}

// Auto-expire policies before processing claim
$expireStmt = $conn->prepare("
    UPDATE insurance_policies 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND policy_end < NOW()
");
$expireStmt->execute();
$expireStmt->close();

mysqli_begin_transaction($conn);

try {
    // 1. Verify policy exists and belongs to user
    $stmt = $conn->prepare("
        SELECT ip.*, b.user_id 
        FROM insurance_policies ip
        JOIN bookings b ON ip.booking_id = b.id
        WHERE ip.id = ? AND ip.booking_id = ? AND b.user_id = ?
    ");
    $stmt->bind_param("iii", $policyId, $bookingId, $userId);
    $stmt->execute();
    $policy = $stmt->get_result()->fetch_assoc();
    
    if (!$policy) {
        throw new Exception('Policy not found or unauthorized');
    }
    
    if ($policy['status'] !== 'active') {
        throw new Exception('Policy is not active');
    }
    
    // 2. Validate claimed amount against coverage limits
    $coverageLimit = floatval($policy['coverage_limit']);
    if ($claimedAmount > $coverageLimit) {
        throw new Exception("Claimed amount exceeds policy coverage limit of ₱" . number_format($coverageLimit, 2));
    }
    
    // 3. Generate claim number
    $claimNumber = 'CLM-' . date('Y') . '-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(uniqid(), -4));
    
    // 4. Insert claim
    $evidencePhotosJson = json_encode($evidencePhotos);
    
    $stmt = $conn->prepare("
        INSERT INTO insurance_claims (
            claim_number, policy_id, booking_id, user_id,
            claim_type, incident_date, incident_location, incident_description,
            police_report_number, claimed_amount, evidence_photos,
            status, priority
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            'submitted', 'normal'
        )
    ");
    
    // 11 parameters: s i i i s s s s s d s
    $stmt->bind_param(
        "siiisssssds",
        $claimNumber,            // 1. s - claim_number
        $policyId,               // 2. i - policy_id
        $bookingId,              // 3. i - booking_id
        $userId,                 // 4. i - user_id
        $claimType,              // 5. s - claim_type
        $incidentDate,           // 6. s - incident_date
        $incidentLocation,       // 7. s - incident_location
        $incidentDescription,    // 8. s - incident_description
        $policeReportNumber,     // 9. s - police_report_number
        $claimedAmount,          // 10. d - claimed_amount
        $evidencePhotosJson      // 11. s - evidence_photos
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to file claim');
    }
    
    $claimId = $stmt->insert_id;
    
    // 5. Update policy status
    $stmt = $conn->prepare("UPDATE insurance_policies SET status = 'claimed' WHERE id = ?");
    $stmt->bind_param("i", $policyId);
    $stmt->execute();
    
    // 6. Log the action
    $stmt = $conn->prepare("
        INSERT INTO insurance_audit_log (claim_id, action_type, action_by, action_details)
        VALUES (?, 'claim_filed', ?, ?)
    ");
    $actionDetails = json_encode([
        'claim_type' => $claimType,
        'claimed_amount' => $claimedAmount,
        'claim_number' => $claimNumber
    ]);
    $stmt->bind_param("iis", $claimId, $userId, $actionDetails);
    $stmt->execute();
    
    // 7. Create notification for admin
    $notifTitle = "New Insurance Claim Filed";
    $notifMessage = "Claim #$claimNumber - $claimType - ₱" . number_format($claimedAmount, 2);
    $stmt = $conn->prepare("
        INSERT INTO admin_notifications (type, title, message, priority)
        VALUES ('system', ?, ?, 'high')
    ");
    $stmt->bind_param("ss", $notifTitle, $notifMessage);
    $stmt->execute();
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Insurance claim filed successfully',
        'data' => [
            'claim_id' => $claimId,
            'claim_number' => $claimNumber,
            'status' => 'submitted',
            'claimed_amount' => $claimedAmount,
            'deductible' => floatval($policy['deductible'])
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
