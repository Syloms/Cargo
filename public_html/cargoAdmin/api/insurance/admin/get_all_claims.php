<?php
/**
 * Admin: Get All Insurance Claims
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../../include/db.php';

$status = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Count query
    $countQuery = "
        SELECT COUNT(*) as total
        FROM insurance_claims ic
        JOIN insurance_policies ip ON ic.policy_id = ip.id
        JOIN bookings b ON ic.booking_id = b.id
        JOIN users u ON ic.user_id = u.id
        WHERE 1=1
    ";
    
    if ($status !== 'all') {
        $countQuery .= " AND ic.status = ?";
    }
    
    $countStmt = $conn->prepare($countQuery);
    if ($status !== 'all') {
        $countStmt->bind_param("s", $status);
    }
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    
    // Data query
    $query = "
        SELECT 
            ic.*,
            ip.policy_number,
            b.id as booking_id,
            u.fullname AS claimant_name,
            u.email as claimant_email
        FROM insurance_claims ic
        JOIN insurance_policies ip ON ic.policy_id = ip.id
        JOIN bookings b ON ic.booking_id = b.id
        JOIN users u ON ic.user_id = u.id
        WHERE 1=1
    ";
    
    if ($status !== 'all') {
        $query .= " AND ic.status = ?";
    }
    
    $query .= " ORDER BY 
        CASE ic.priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
            WHEN 'low' THEN 4
        END,
        ic.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    if ($status !== 'all') {
        $stmt->bind_param("sii", $status, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $claims = [];
    while ($row = $result->fetch_assoc()) {
        $evidence = [];
        if (!empty($row['evidence_photos'])) {
            $decoded = json_decode($row['evidence_photos'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    if (defined('BASE_URL') && strpos($p, 'http') !== 0) {
                        $evidence[] = BASE_URL . '/' . $p;
                    } else {
                        $evidence[] = $p;
                    }
                }
            }
        }
        $claims[] = [
            'claim_id' => $row['id'],
            'claim_number' => $row['claim_number'],
            'policy_number' => $row['policy_number'],
            'booking_id' => $row['booking_id'],
            'claimant' => [
                'name' => $row['claimant_name'],
                'email' => $row['claimant_email']
            ],
            'claim_type' => $row['claim_type'],
            'incident_date' => $row['incident_date'],
            'incident_location' => $row['incident_location'],
            'incident_description' => $row['incident_description'],
            'police_report_number' => $row['police_report_number'],
            'claimed_amount' => floatval($row['claimed_amount']),
            'approved_amount' => floatval($row['approved_amount']),
            'status' => $row['status'],
            'priority' => $row['priority'],
            'created_at' => $row['created_at'],
            'reviewed_at' => $row['reviewed_at'],
            'evidence_photos' => $evidence,
            'evidence_count' => count($evidence)
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'data' => $claims,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
