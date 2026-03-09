<?php
/**
 * Get Insurance Claims
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../include/db.php';

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$claimId = isset($_GET['claim_id']) ? intval($_GET['claim_id']) : 0;
$status = $_GET['status'] ?? 'all';

try {
    if ($claimId > 0) {
        // Get specific claim
        $stmt = $conn->prepare("
            SELECT 
                ic.*,
                ip.policy_number,
                ip.coverage_type,
                b.id as booking_id,
                b.vehicle_type
            FROM insurance_claims ic
            JOIN insurance_policies ip ON ic.policy_id = ip.id
            JOIN bookings b ON ic.booking_id = b.id
            WHERE ic.id = ? AND ic.user_id = ?
        ");
        $stmt->bind_param("ii", $claimId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Claim not found']);
            exit;
        }
        
        $claim = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'claim_id' => $claim['id'],
                'claim_number' => $claim['claim_number'],
                'policy_number' => $claim['policy_number'],
                'claim_type' => $claim['claim_type'],
                'incident_date' => $claim['incident_date'],
                'incident_location' => $claim['incident_location'],
                'incident_description' => $claim['incident_description'],
                'claimed_amount' => floatval($claim['claimed_amount']),
                'approved_amount' => floatval($claim['approved_amount']),
                'payout_amount' => floatval($claim['payout_amount']),
                'status' => $claim['status'],
                'priority' => $claim['priority'],
                'police_report_number' => $claim['police_report_number'],
                'evidence_photos' => json_decode($claim['evidence_photos'] ?? '[]'),
                'review_notes' => $claim['review_notes'],
                'rejection_reason' => $claim['rejection_reason'],
                'created_at' => $claim['created_at'],
                'reviewed_at' => $claim['reviewed_at']
            ]
        ]);
        
    } else {
        // Get all claims for user
        $query = "
            SELECT 
                ic.*,
                ip.policy_number,
                b.vehicle_type
            FROM insurance_claims ic
            JOIN insurance_policies ip ON ic.policy_id = ip.id
            JOIN bookings b ON ic.booking_id = b.id
            WHERE ic.user_id = ?
        ";
        
        if ($status !== 'all') {
            $query .= " AND ic.status = ?";
        }
        
        $query .= " ORDER BY ic.created_at DESC";
        
        $stmt = $conn->prepare($query);
        
        if ($status !== 'all') {
            $stmt->bind_param("is", $userId, $status);
        } else {
            $stmt->bind_param("i", $userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $claims = [];
        while ($row = $result->fetch_assoc()) {
            $claims[] = [
                'claim_id' => $row['id'],
                'claim_number' => $row['claim_number'],
                'policy_number' => $row['policy_number'],
                'claim_type' => $row['claim_type'],
                'incident_date' => $row['incident_date'],
                'claimed_amount' => floatval($row['claimed_amount']),
                'approved_amount' => floatval($row['approved_amount']),
                'status' => $row['status'],
                'priority' => $row['priority'],
                'vehicle_type' => $row['vehicle_type'],
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $claims,
            'count' => count($claims)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
