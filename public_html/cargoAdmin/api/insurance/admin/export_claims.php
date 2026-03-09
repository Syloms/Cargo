<?php
/**
 * ============================================================================
 * EXPORT INSURANCE CLAIMS TO CSV - Admin Download
 * ============================================================================
 */

session_start();
require_once __DIR__ . '/../../../include/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

$status = $_GET['status'] ?? 'all';
$priority = $_GET['priority'] ?? 'all';

try {
    $query = "
        SELECT 
            ic.claim_number,
            ip.policy_number,
            ic.claim_type,
            ic.incident_date,
            ic.incident_description,
            ic.claimed_amount,
            ic.approved_amount,
            ic.status,
            ic.priority,
            ic.created_at,
            ic.reviewed_at,
            ic.review_notes,
            u.fullname AS claimant_name,
            u.email as claimant_email,
            reviewer.fullname as reviewer_name
        FROM insurance_claims ic
        JOIN insurance_policies ip ON ic.policy_id = ip.id
        JOIN bookings b ON ic.booking_id = b.id
        JOIN users u ON ic.user_id = u.id
        LEFT JOIN users reviewer ON ic.reviewed_by = reviewer.id
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    if ($status !== 'all') {
        $query .= " AND ic.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($priority !== 'all') {
        $query .= " AND ic.priority = ?";
        $params[] = $priority;
        $types .= 's';
    }
    
    $query .= " ORDER BY 
        CASE ic.priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
            WHEN 'low' THEN 4
        END,
        ic.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set headers for CSV download
    $filename = 'insurance_claims_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Output CSV header
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, [
        'Claim Number',
        'Policy Number',
        'Claim Type',
        'Incident Date',
        'Incident Description',
        'Claimed Amount',
        'Approved Amount',
        'Status',
        'Priority',
        'Claimant Name',
        'Claimant Email',
        'Submitted Date',
        'Reviewed Date',
        'Reviewer',
        'Review Notes'
    ]);
    
    // Output data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['claim_number'],
            $row['policy_number'],
            $row['claim_type'],
            $row['incident_date'],
            $row['incident_description'],
            number_format($row['claimed_amount'], 2),
            number_format($row['approved_amount'], 2),
            $row['status'],
            $row['priority'],
            $row['claimant_name'],
            $row['claimant_email'],
            $row['created_at'],
            $row['reviewed_at'] ?? 'N/A',
            $row['reviewer_name'] ?? 'N/A',
            $row['review_notes'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
    mysqli_close($conn);
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>
