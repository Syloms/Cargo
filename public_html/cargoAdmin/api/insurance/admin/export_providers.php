<?php
/**
 * ============================================================================
 * EXPORT INSURANCE PROVIDERS TO CSV - Admin Download
 * ============================================================================
 */

session_start();
require_once __DIR__ . '/../../../include/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

try {
    $query = "
        SELECT 
            provider_name,
            contact_phone,
            contact_email,
            status,
            created_at,
            (SELECT COUNT(*) FROM insurance_policies WHERE provider_id = insurance_providers.id) as total_policies,
            (SELECT COUNT(*) FROM insurance_policies WHERE provider_id = insurance_providers.id AND status = 'active') as active_policies
        FROM insurance_providers
        ORDER BY provider_name ASC
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception('Query error: ' . mysqli_error($conn));
    }
    
    // Set headers for CSV download
    $filename = 'insurance_providers_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Output CSV header
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, [
        'Provider Name',
        'Contact Phone',
        'Contact Email',
        'Status',
        'Total Policies',
        'Active Policies',
        'Created Date'
    ]);
    
    // Output data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['provider_name'],
            $row['contact_phone'],
            $row['contact_email'],
            $row['status'],
            $row['total_policies'],
            $row['active_policies'],
            $row['created_at']
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
