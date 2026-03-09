<?php
/**
 * Admin: Approve Insurance Claim
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

try {
    require_once __DIR__ . '/../../../include/db.php';
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

$claimId = isset($input['claim_id']) ? intval($input['claim_id']) : 0;
$approvedAmountRaw = $input['approved_amount'] ?? null;
$approvedAmount = 0;
if (is_numeric($approvedAmountRaw)) {
    $approvedAmount = floatval($approvedAmountRaw);
} elseif (is_string($approvedAmountRaw)) {
    $normalized = preg_replace('/[^\d\.]/', '', $approvedAmountRaw);
    $approvedAmount = $normalized !== '' ? floatval($normalized) : 0;
}
$reviewNotes = $input['review_notes'] ?? '';
$adminId = isset($input['admin_id']) ? intval($input['admin_id']) : 1; // TODO: Get from session

if ($claimId <= 0 || $approvedAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid claim ID or amount']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Get claim details
    $stmt = $conn->prepare("
        SELECT ic.*, ip.deductible 
        FROM insurance_claims ic
        JOIN insurance_policies ip ON ic.policy_id = ip.id
        WHERE ic.id = ?
    ");
    $stmt->bind_param("i", $claimId);
    $stmt->execute();
    $claim = $stmt->get_result()->fetch_assoc();
    
    if (!$claim) {
        throw new Exception('Claim not found');
    }
    
    if ($claim['status'] !== 'submitted' && $claim['status'] !== 'under_review') {
        throw new Exception('Claim cannot be approved in current status');
    }
    
    if ($approvedAmount > floatval($claim['claimed_amount'])) {
        throw new Exception('Approved amount cannot exceed claimed amount');
    }
    
    $evidenceList = json_decode($claim['evidence_photos'] ?? '[]', true);
    if (!is_array($evidenceList) || count($evidenceList) === 0) {
        throw new Exception('Evidence photos are required before approval');
    }
    
    // Calculate payout (approved amount minus deductible)
    $deductible = floatval($claim['deductible']);
    $payoutAmount = max(0, $approvedAmount - $deductible);
    
    // Update claim
    $stmt = $conn->prepare("
        UPDATE insurance_claims 
        SET status = 'approved',
            approved_amount = ?,
            payout_amount = ?,
            reviewed_by = ?,
            reviewed_at = NOW(),
            review_notes = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ddisi", $approvedAmount, $payoutAmount, $adminId, $reviewNotes, $claimId);
    $stmt->execute();
    
    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO insurance_audit_log (claim_id, action_type, action_by, action_details)
        VALUES (?, 'claim_approved', ?, ?)
    ");
    $actionDetails = json_encode([
        'approved_amount' => $approvedAmount,
        'payout_amount' => $payoutAmount,
        'review_notes' => $reviewNotes
    ]);
    $stmt->bind_param("iis", $claimId, $adminId, $actionDetails);
    $stmt->execute();
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Claim approved successfully',
        'data' => [
            'approved_amount' => $approvedAmount,
            'payout_amount' => $payoutAmount,
            'deductible' => $deductible
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
