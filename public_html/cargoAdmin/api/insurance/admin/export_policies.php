<?php
/**
 * ============================================================================
 * EXPORT INSURANCE POLICIES TO CSV - Admin Download
 * ============================================================================
 */

session_start();
require_once __DIR__ . '/../../../include/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

try {
    $query = "
        SELECT 
            ip.policy_number,
            ip.coverage_type,
            ip.premium_amount,
            ip.coverage_limit,
            ip.deductible,
            ip.policy_start,
            ip.policy_end,
            ip.status,
            prov.provider_name,
            u.fullname AS renter_name,
            u.email as renter_email,
            o.fullname AS owner_name,
            CASE 
                WHEN ip.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model)
                WHEN ip.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model)
            END AS vehicle_name,
            DATEDIFF(ip.policy_end, NOW()) AS days_remaining
        FROM insurance_policies ip
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        JOIN bookings b ON ip.booking_id = b.id
        JOIN users u ON ip.user_id = u.id
        JOIN users o ON ip.owner_id = o.id
        LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    if ($status !== 'all') {
        $query .= " AND ip.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if (!empty($search)) {
        $query .= " AND (ip.policy_number LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }
    
    $query .= " ORDER BY ip.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set headers for CSV download
    $filename = 'insurance_policies_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Output CSV header
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, [
        'Policy Number',
        'Provider',
        'Coverage Type',
        'Premium Amount',
        'Coverage Limit',
        'Deductible',
        'Policy Start',
        'Policy End',
        'Days Remaining',
        'Status',
        'Renter Name',
        'Renter Email',
        'Owner Name',
        'Vehicle'
    ]);
    
    // Output data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['policy_number'],
            $row['provider_name'],
            $row['coverage_type'],
            number_format($row['premium_amount'], 2),
            number_format($row['coverage_limit'], 2),
            number_format($row['deductible'], 2),
            $row['policy_start'],
            $row['policy_end'],
            $row['days_remaining'],
            $row['status'],
            $row['renter_name'],
            $row['renter_email'],
            $row['owner_name'],
            $row['vehicle_name']
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
