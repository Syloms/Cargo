<?php
/**
 * ============================================================================
 * EXPORT REFUNDS TO CSV - Admin Download
 * ============================================================================
 */

session_start();
require_once '../../include/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

// ============================================================================
// GET FILTERS FROM URL
// ============================================================================

$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where = "WHERE 1=1";

if ($statusFilter !== 'all') {
    $where .= " AND r.status = '" . mysqli_real_escape_string($conn, $statusFilter) . "'";
}

if (!empty($search)) {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (
        r.id LIKE '%$searchEsc%' OR
        u_renter.fullname LIKE '%$searchEsc%' OR
        u_renter.email LIKE '%$searchEsc%'
    )";
}

// ============================================================================
// FETCH REFUNDS (FIXED)
// ============================================================================

$query = "
    SELECT 
        CONCAT('#RF-', LPAD(r.id, 4, '0')) AS 'Refund ID',
        r.status AS 'Status',
        u_renter.fullname AS 'Renter Name',
        u_renter.email AS 'Renter Email',
        CONCAT('#BK-', LPAD(b.id, 4, '0')) AS 'Booking ID',
        CONCAT(
            COALESCE(c.brand, m.brand), ' ',
            COALESCE(c.model, m.model), ' ',
            COALESCE(c.car_year, m.motorcycle_year)
        ) AS 'Car',
        u_owner.fullname AS 'Owner Name',
        r.refund_amount AS 'Refund Amount',
        r.refund_method AS 'Method',
        r.account_number AS 'Account Number',
        r.account_name AS 'Account Name',
        r.refund_reason AS 'Reason',
        r.completion_reference AS 'Reference',
        DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i') AS 'Created',
        DATE_FORMAT(r.processed_at, '%Y-%m-%d %H:%i') AS 'Processed',
        DATEDIFF(NOW(), r.created_at) AS 'Days Pending'
        
    FROM refunds r
    LEFT JOIN users u_renter ON r.user_id = u_renter.id
    LEFT JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN users u_owner ON b.owner_id = u_owner.id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    $where
    ORDER BY r.created_at DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}

// ============================================================================
// SET CSV HEADERS
// ============================================================================

$filename = 'refunds_export_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ============================================================================
// WRITE CSV
// ============================================================================

// Get column headers
$firstRow = mysqli_fetch_assoc($result);
if ($firstRow) {
    // Write headers
    fputcsv($output, array_keys($firstRow));
    
    // Write first row
    fputcsv($output, array_values($firstRow));
    
    // Write remaining rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, array_values($row));
    }
}

fclose($output);
mysqli_close($conn);
exit;