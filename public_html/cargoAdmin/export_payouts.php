<?php
/**
 * ============================================================================
 * EXPORT PAYOUTS TO CSV - Admin Download
 * ============================================================================
 */

session_start();
require_once 'include/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

// ============================================================================
// GET FILTERS FROM URL
// ============================================================================

$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$minAmount = isset($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$maxAmount = isset($_GET['max_amount']) ? floatval($_GET['max_amount']) : 0;

// ============================================================================
// BUILD QUERY
// ============================================================================

if ($statusFilter == 'pending') {
    // Pending payouts - from bookings table
    $sql = "
        SELECT 
            b.id AS booking_id,
            CONCAT('#BK-', LPAD(b.id, 4, '0')) AS payout_id,
            b.total_amount,
            b.platform_fee,
            b.owner_payout,
            b.status AS booking_status,
            b.escrow_status,
            b.pickup_date,
            b.return_date,
            b.escrow_released_at,
            b.vehicle_type,
            
            u_owner.id AS owner_id,
            u_owner.fullname AS owner_name,
            u_owner.email AS owner_email,
            u_owner.phone AS owner_phone,
            u_owner.gcash_number,
            u_owner.gcash_name,
            
            u_renter.fullname AS renter_name,
            u_renter.email AS renter_email,
            
            COALESCE(c.brand, m.brand) AS brand,
            COALESCE(c.model, m.model) AS model,
            COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
            COALESCE(c.plate_number, m.plate_number) AS plate_number,
            
            'pending' AS status,
            COALESCE(b.escrow_released_at, b.return_date) AS processed_at
            
        FROM bookings b
        JOIN users u_owner ON b.owner_id = u_owner.id
        JOIN users u_renter ON b.user_id = u_renter.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        
        WHERE b.status = 'completed'
        AND b.escrow_status = 'released_to_owner'
        AND b.id NOT IN (SELECT booking_id FROM payouts WHERE status = 'completed')
    ";
} else {
    // Completed/processing/failed payouts - from payouts table
    $sql = "
        SELECT 
            p.id AS payout_id,
            p.booking_id,
            p.amount AS total_amount,
            p.platform_fee,
            p.net_amount AS owner_payout,
            p.payment_method,
            p.reference_number,
            p.status,
            p.processed_at,
            p.completed_at,
            p.notes,
            
            b.pickup_date,
            b.return_date,
            b.vehicle_type,
            b.status AS booking_status,
            b.escrow_status,
            
            u_owner.id AS owner_id,
            u_owner.fullname AS owner_name,
            u_owner.email AS owner_email,
            u_owner.phone AS owner_phone,
            u_owner.gcash_number,
            u_owner.gcash_name,
            
            u_renter.fullname AS renter_name,
            u_renter.email AS renter_email,
            
            COALESCE(c.brand, m.brand) AS brand,
            COALESCE(c.model, m.model) AS model,
            COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
            COALESCE(c.plate_number, m.plate_number) AS plate_number
            
        FROM payouts p
        JOIN bookings b ON p.booking_id = b.id
        JOIN users u_owner ON p.owner_id = u_owner.id
        JOIN users u_renter ON b.user_id = u_renter.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        
        WHERE 1=1
    ";
    
    if ($statusFilter !== 'all') {
        $sql .= " AND p.status = '" . mysqli_real_escape_string($conn, $statusFilter) . "'";
    }
}

// Apply search filter
if (!empty($search)) {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (
        u_owner.fullname LIKE '%$searchEsc%' OR
        u_owner.email LIKE '%$searchEsc%' OR
        COALESCE(c.brand, m.brand) LIKE '%$searchEsc%' OR
        COALESCE(c.model, m.model) LIKE '%$searchEsc%' OR
        COALESCE(c.plate_number, m.plate_number) LIKE '%$searchEsc%'
    )";
}

// Apply date filter
if (!empty($dateFrom)) {
    $sql .= " AND DATE(" . ($statusFilter == 'pending' ? 'b.escrow_released_at' : 'p.processed_at') . ") >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
}
if (!empty($dateTo)) {
    $sql .= " AND DATE(" . ($statusFilter == 'pending' ? 'b.escrow_released_at' : 'p.processed_at') . ") <= '" . mysqli_real_escape_string($conn, $dateTo) . "'";
}

// Apply amount filter
if ($minAmount > 0) {
    $sql .= " AND " . ($statusFilter == 'pending' ? 'b.owner_payout' : 'p.net_amount') . " >= $minAmount";
}
if ($maxAmount > 0) {
    $sql .= " AND " . ($statusFilter == 'pending' ? 'b.owner_payout' : 'p.net_amount') . " <= $maxAmount";
}

// Apply sorting
switch ($sortBy) {
    case 'date_asc':
        $sql .= " ORDER BY " . ($statusFilter == 'pending' ? 'b.escrow_released_at' : 'p.processed_at') . " ASC";
        break;
    case 'amount_desc':
        $sql .= " ORDER BY " . ($statusFilter == 'pending' ? 'b.owner_payout' : 'p.net_amount') . " DESC";
        break;
    case 'amount_asc':
        $sql .= " ORDER BY " . ($statusFilter == 'pending' ? 'b.owner_payout' : 'p.net_amount') . " ASC";
        break;
    case 'owner_asc':
        $sql .= " ORDER BY u_owner.fullname ASC";
        break;
    case 'owner_desc':
        $sql .= " ORDER BY u_owner.fullname DESC";
        break;
    default:
        $sql .= " ORDER BY " . ($statusFilter == 'pending' ? 'b.escrow_released_at' : 'p.processed_at') . " DESC";
}

$result = mysqli_query($conn, $sql);

if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}

// ============================================================================
// SET CSV HEADERS
// ============================================================================

$filename = 'payouts_export_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ============================================================================
// WRITE CSV HEADERS
// ============================================================================

fputcsv($output, [
    'Payout ID',
    'Booking ID',
    'Owner Name',
    'Owner Email',
    'Owner Phone',
    'GCash Number',
    'GCash Name',
    'Renter Name',
    'Renter Email',
    'Vehicle',
    'Plate Number',
    'Rental Period',
    'Total Amount',
    'Platform Fee (10%)',
    'Owner Payout',
    'Status',
    'Payment Method',
    'Reference Number',
    'Processed Date',
    'Completed Date',
    'Notes'
]);

// ============================================================================
// WRITE CSV DATA
// ============================================================================

while ($row = mysqli_fetch_assoc($result)) {
    $payoutId = $statusFilter == 'pending' 
        ? '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT)
        : '#PO-' . str_pad($row['payout_id'], 4, '0', STR_PAD_LEFT);
    
    $bookingId = '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT);
    $vehicleName = $row['brand'] . ' ' . $row['model'] . ' ' . $row['vehicle_year'];
    $rentalPeriod = date('M d', strtotime($row['pickup_date'])) . ' - ' . date('M d, Y', strtotime($row['return_date']));
    
    $processedDate = !empty($row['processed_at']) ? date('Y-m-d H:i:s', strtotime($row['processed_at'])) : 'N/A';
    $completedDate = !empty($row['completed_at']) ? date('Y-m-d H:i:s', strtotime($row['completed_at'])) : 'N/A';
    
    fputcsv($output, [
        $payoutId,
        $bookingId,
        $row['owner_name'],
        $row['owner_email'],
        $row['owner_phone'] ?? 'N/A',
        $row['gcash_number'] ?? 'Not set',
        $row['gcash_name'] ?? 'Not set',
        $row['renter_name'],
        $row['renter_email'],
        $vehicleName,
        $row['plate_number'],
        $rentalPeriod,
        number_format($row['total_amount'], 2),
        number_format($row['platform_fee'], 2),
        number_format($row['owner_payout'], 2),
        ucfirst($row['status']),
        $row['payment_method'] ?? 'N/A',
        $row['reference_number'] ?? 'N/A',
        $processedDate,
        $completedDate,
        $row['notes'] ?? ''
    ]);
}

fclose($output);
mysqli_close($conn);
exit;
