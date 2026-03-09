<?php
/**
 * ============================================================================
 * EXPORT REPORTS TO CSV - Admin Download
 * ============================================================================
 */

session_start();
require_once "../include/db.php";

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

// Get filters
$filterType = $_GET['type'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "
SELECT 
    r.id,
    r.report_type,
    r.reported_id,
    r.reason,
    r.details,
    r.status,
    r.priority,
    r.created_at,
    reporter.fullname AS reporter_name,
    reporter.email AS reporter_email
FROM reports r
LEFT JOIN users reporter ON r.reporter_id = reporter.id
WHERE 1=1
";

if ($filterType !== 'all') {
    $query .= " AND r.report_type = '" . mysqli_real_escape_string($conn, $filterType) . "'";
}

if ($filterStatus !== 'all') {
    $query .= " AND r.status = '" . mysqli_real_escape_string($conn, $filterStatus) . "'";
}

$query .= " ORDER BY r.created_at DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}

// Set headers for CSV download
$filename = 'reports_export_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV headers
fputcsv($output, ['ID', 'Type', 'Reported ID', 'Reason', 'Details', 'Status', 'Priority', 'Reporter', 'Email', 'Date']);

// CSV data
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['id'],
        $row['report_type'],
        $row['reported_id'],
        $row['reason'],
        $row['details'],
        $row['status'],
        $row['priority'] ?? 'medium',
        $row['reporter_name'],
        $row['reporter_email'],
        $row['created_at']
    ]);
}

fclose($output);
mysqli_close($conn);
exit;