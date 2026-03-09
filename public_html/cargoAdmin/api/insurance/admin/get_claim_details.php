<?php
/**
 * Get detailed claim information for modal view
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../include/db.php';

$claimId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$claimId) {
    echo json_encode(['success' => false, 'message' => 'Claim ID is required']);
    exit;
}

try {
    $query = "
        SELECT 
            ic.*,
            ip.policy_number,
            ip.coverage_type,
            ip.coverage_limit,
            ip.deductible,
            b.id as booking_id,
            u.id as claimant_id,
            u.fullname AS claimant_name,
            u.email as claimant_email,
            u.phone as claimant_contact,
            reviewer.fullname as reviewer_name,
            reviewer.email as reviewer_email,
            CASE 
                WHEN ip.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model, ' ', c.car_year)
                WHEN ip.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model, ' ', m.motorcycle_year)
            END AS vehicle_name
        FROM insurance_claims ic
        JOIN insurance_policies ip ON ic.policy_id = ip.id
        JOIN bookings b ON ic.booking_id = b.id
        JOIN users u ON ic.user_id = u.id
        LEFT JOIN users reviewer ON ic.reviewed_by = reviewer.id
        LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
        WHERE ic.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $claimId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Claim not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    
    // Get supporting documents if any (check if table exists first)
    $documents = [];
    $tableCheckQuery = "SHOW TABLES LIKE 'claim_documents'";
    $tableCheckResult = $conn->query($tableCheckQuery);
    
    if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
        $docsQuery = "SELECT * FROM claim_documents WHERE claim_id = ?";
        $docsStmt = $conn->prepare($docsQuery);
        $docsStmt->bind_param('i', $claimId);
        $docsStmt->execute();
        $docsResult = $docsStmt->get_result();
        
        while ($doc = $docsResult->fetch_assoc()) {
            $documents[] = [
                'id' => $doc['id'],
                'file_name' => $doc['file_name'],
                'file_path' => $doc['file_path'],
                'file_type' => $doc['file_type'],
                'uploaded_at' => $doc['uploaded_at']
            ];
        }
    }
    
    // Parse evidence photos from JSON
    $evidencePhotos = [];
    if (!empty($row['evidence_photos'])) {
        $decoded = json_decode($row['evidence_photos'], true);
        if (is_array($decoded)) {
            $evidencePhotos = $decoded;
        }
    }
    
    // Parse damage assessment from JSON
    $damageAssessment = null;
    if (!empty($row['damage_assessment'])) {
        $decoded = json_decode($row['damage_assessment'], true);
        if ($decoded) {
            $damageAssessment = $decoded;
        }
    }
    
    $claim = [
        'id' => $row['id'],
        'claim_number' => $row['claim_number'],
        'policy_number' => $row['policy_number'],
        'booking_id' => $row['booking_id'],
        'claim_type' => $row['claim_type'],
        'incident_date' => $row['incident_date'],
        'incident_location' => $row['incident_location'],
        'incident_description' => $row['incident_description'],
        'police_report_number' => $row['police_report_number'],
        'police_report_file' => $row['police_report_file'],
        'claimed_amount' => floatval($row['claimed_amount']),
        'approved_amount' => floatval($row['approved_amount']),
        'status' => $row['status'],
        'priority' => $row['priority'],
        'review_notes' => $row['review_notes'],
        'rejection_reason' => $row['rejection_reason'],
        'created_at' => $row['created_at'],
        'reviewed_at' => $row['reviewed_at'],
        'evidence_photos' => $evidencePhotos,
        'damage_assessment' => $damageAssessment,
        'claimant' => [
            'id' => $row['claimant_id'],
            'name' => $row['claimant_name'],
            'email' => $row['claimant_email'],
            'contact' => $row['claimant_contact']
        ],
        'reviewer' => [
            'name' => $row['reviewer_name'] ?? null,
            'email' => $row['reviewer_email'] ?? null
        ],
        'policy' => [
            'number' => $row['policy_number'],
            'coverage_type' => $row['coverage_type'],
            'coverage_limit' => floatval($row['coverage_limit']),
            'deductible' => floatval($row['deductible'])
        ],
        'vehicle' => [
            'name' => $row['vehicle_name']
        ],
        'documents' => $documents
    ];
    
    echo json_encode(['success' => true, 'data' => $claim]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
