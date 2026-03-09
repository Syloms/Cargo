<?php
/**
 * Admin: Reject Insurance Claim
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$claimId = isset($input['claim_id']) ? intval($input['claim_id']) : 0;
$rejectionReason = $input['rejection_reason'] ?? '';
$adminId = isset($input['admin_id']) ? intval($input['admin_id']) : 1;

if ($claimId <= 0 || empty($rejectionReason)) {
    echo json_encode(['success' => false, 'message' => 'Claim ID and rejection reason required']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Update claim status
    $stmt = $conn->prepare("
        UPDATE insurance_claims 
        SET status = 'rejected',
            reviewed_by = ?,
            reviewed_at = NOW(),
            rejection_reason = ?
        WHERE id = ?
    ");
    $stmt->bind_param("isi", $adminId, $rejectionReason, $claimId);
    $stmt->execute();
    
    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO insurance_audit_log (claim_id, action_type, action_by, action_details)
        VALUES (?, 'claim_rejected', ?, ?)
    ");
    $actionDetails = json_encode(['rejection_reason' => $rejectionReason]);
    $stmt->bind_param("iis", $claimId, $adminId, $actionDetails);
    $stmt->execute();
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Claim rejected successfully'
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
