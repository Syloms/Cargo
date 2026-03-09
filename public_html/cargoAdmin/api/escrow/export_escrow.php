<?php
/**
 * ============================================================================
 * EXPORT ESCROW DATA - FIXED
 * Export escrow transactions to CSV
 * ============================================================================
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../include/db.php';

// Get filters from query string
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause - FIXED FOR ACTUAL ENUM VALUES
$where = " WHERE 1 ";

switch($statusFilter) {
    case 'held':
        $where .= " AND b.escrow_status = 'held' AND (b.escrow_hold_reason IS NULL OR b.escrow_hold_reason = '') ";
        break;
    case 'released':
        $where .= " AND b.escrow_status IN ('released_to_owner', 'released') ";
        break;
    case 'on_hold':
        // Check for hold reason instead of enum value
        $where .= " AND b.escrow_status = 'held' AND b.escrow_hold_reason IS NOT NULL AND b.escrow_hold_reason != '' ";
        break;
    case 'refunded':
        $where .= " AND b.escrow_status = 'refunded' ";
        break;
    case 'pending_release':
        $where .= " AND b.escrow_status = 'held' AND b.status = 'completed' ";
        break;
    case 'all':
        $where .= " AND b.escrow_status IN ('held', 'released_to_owner', 'released', 'refunded', 'pending') ";
        break;
}

if (!empty($search)) {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (
        b.id LIKE '%$searchEsc%' OR
        u_renter.fullname LIKE '%$searchEsc%' OR
        u_owner.fullname LIKE '%$searchEsc%' OR
        COALESCE(c.brand, m.brand) LIKE '%$searchEsc%'
    )";
}

// Query escrow data
$query = "
    SELECT 
        b.id AS booking_id,
        b.total_amount,
        b.platform_fee,
        b.owner_payout,
        b.status AS booking_status,
        b.escrow_status,
        b.escrow_hold_reason,
        b.escrow_hold_details,
        b.created_at,
        b.escrow_held_at,
        b.escrow_released_at,
        b.escrow_refunded_at,
        b.payment_verified_at,
        
        CASE 
            WHEN b.escrow_status = 'held' THEN 
                DATEDIFF(NOW(), COALESCE(b.escrow_held_at, b.payment_verified_at, b.created_at))
            WHEN b.escrow_status IN ('released_to_owner', 'released') THEN 
                DATEDIFF(b.escrow_released_at, COALESCE(b.escrow_held_at, b.payment_verified_at, b.created_at))
            ELSE 0
        END AS days_in_escrow,
        
        u_renter.fullname AS renter_name,
        u_renter.email AS renter_email,
        u_owner.fullname AS owner_name,
        u_owner.email AS owner_email,
        
        COALESCE(c.brand, m.brand) AS brand,
        COALESCE(c.model, m.model) AS model,
        COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
        COALESCE(c.plate_number, m.plate_number) AS plate_number,
        
        p.payment_method,
        p.payment_reference
        
    FROM bookings b
    JOIN users u_renter ON b.user_id = u_renter.id
    JOIN users u_owner ON b.owner_id = u_owner.id
    LEFT JOIN payments p ON b.id = p.booking_id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    $where
    ORDER BY b.created_at DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Set headers for CSV download
$filename = 'escrow_report_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
fputcsv($output, [
    'Booking ID',
    'Renter Name',
    'Renter Email',
    'Owner Name',
    'Owner Email',
    'Vehicle',
    'Plate Number',
    'Total Amount',
    'Platform Fee',
    'Owner Payout',
    'Escrow Status',
    'On Hold',
    'Hold Reason',
    'Booking Status',
    'Days in Escrow',
    'Payment Method',
    'Payment Reference',
    'Created Date',
    'Released Date',
    'Refunded Date'
]);

// Write data rows
while ($row = mysqli_fetch_assoc($result)) {
    $bookingId = '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT);
    $vehicleName = $row['brand'] . ' ' . $row['model'] . ' ' . $row['vehicle_year'];
    
    // Determine actual escrow status
    $isOnHold = !empty($row['escrow_hold_reason']);
    $escrowStatusDisplay = $isOnHold ? 'On Hold' : ucfirst(str_replace('_', ' ', $row['escrow_status']));
    
    fputcsv($output, [
        $bookingId,
        $row['renter_name'],
        $row['renter_email'],
        $row['owner_name'],
        $row['owner_email'],
        $vehicleName,
        $row['plate_number'],
        number_format($row['total_amount'], 2),
        number_format($row['platform_fee'], 2),
        number_format($row['owner_payout'], 2),
        $escrowStatusDisplay,
        $isOnHold ? 'Yes' : 'No',
        $row['escrow_hold_reason'] ?? 'N/A',
        ucfirst($row['booking_status']),
        $row['days_in_escrow'],
        strtoupper($row['payment_method'] ?? 'N/A'),
        $row['payment_reference'] ?? 'N/A',
        date('Y-m-d H:i:s', strtotime($row['created_at'])),
        $row['escrow_released_at'] ? date('Y-m-d H:i:s', strtotime($row['escrow_released_at'])) : 'N/A',
        $row['escrow_refunded_at'] ? date('Y-m-d H:i:s', strtotime($row['escrow_refunded_at'])) : 'N/A'
    ]);
}

fclose($output);
mysqli_close($conn);
exit;
?>