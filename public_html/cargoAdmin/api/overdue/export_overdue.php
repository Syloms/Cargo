<?php
/**
 * ============================================================================
 * EXPORT OVERDUE BOOKINGS TO CSV - Admin Download
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

$severityFilter = isset($_GET['severity']) ? $_GET['severity'] : 'all';
$paymentFilter = isset($_GET['payment']) ? $_GET['payment'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================================
// BUILD QUERY
// ============================================================================

$sql = "
    SELECT 
        b.id AS booking_id,
        b.pickup_date,
        b.return_date,
        b.return_time,
        b.total_amount,
        b.status,
        b.created_at,
        b.vehicle_type,
        
        u_renter.fullname AS renter_name,
        u_renter.email AS renter_email,
        u_renter.phone AS renter_phone,
        
        u_owner.fullname AS owner_name,
        u_owner.email AS owner_email,
        u_owner.phone AS owner_phone,
        
        COALESCE(c.brand, m.brand) AS brand,
        COALESCE(c.model, m.model) AS model,
        COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
        COALESCE(c.plate_number, m.plate_number) AS plate_number,
        COALESCE(c.main_image, m.main_image) AS vehicle_image,
        
        CONCAT(
            TIMESTAMPDIFF(DAY, 
                CONCAT(b.return_date, ' ', b.return_time), 
                NOW()
            )
        ) AS days_overdue,
        
        CONCAT(
            TIMESTAMPDIFF(HOUR, 
                CONCAT(b.return_date, ' ', b.return_time), 
                NOW()
            )
        ) AS hours_overdue,
        
        CASE 
            WHEN TIMESTAMPDIFF(DAY, CONCAT(b.return_date, ' ', b.return_time), NOW()) >= 2 
            THEN 'severely_overdue'
            ELSE 'overdue'
        END AS overdue_status,
        
        b.late_fee_amount,
        b.late_fee_charged,
        
        COALESCE(lf.payment_status, 'unpaid') AS payment_status,
        lf.payment_reference,
        lf.payment_proof_url,
        lf.verified_at,
        lf.verified_by,
        lf.admin_notes
        
    FROM bookings b
    JOIN users u_renter ON b.user_id = u_renter.id
    JOIN users u_owner ON b.owner_id = u_owner.id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    LEFT JOIN late_fee_payments lf ON b.id = lf.booking_id
    
    WHERE b.status = 'ongoing'
    AND CONCAT(b.return_date, ' ', b.return_time) < NOW()
";

// Apply severity filter
if ($severityFilter === 'overdue') {
    $sql .= " AND TIMESTAMPDIFF(DAY, CONCAT(b.return_date, ' ', b.return_time), NOW()) < 2";
} elseif ($severityFilter === 'severely_overdue') {
    $sql .= " AND TIMESTAMPDIFF(DAY, CONCAT(b.return_date, ' ', b.return_time), NOW()) >= 2";
}

// Apply payment filter
if ($paymentFilter === 'unpaid') {
    $sql .= " AND (b.late_fee_charged = 0 OR b.late_fee_charged IS NULL)";
    $sql .= " AND (lf.payment_status IS NULL OR lf.payment_status = 'unpaid')";
} elseif ($paymentFilter === 'pending_verification') {
    $sql .= " AND lf.payment_status = 'pending'";
} elseif ($paymentFilter === 'paid') {
    $sql .= " AND b.late_fee_charged = 1";
}

// Apply search filter
if (!empty($search)) {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (
        b.id LIKE '%$searchEsc%' OR
        u_renter.fullname LIKE '%$searchEsc%' OR
        u_renter.email LIKE '%$searchEsc%' OR
        u_owner.fullname LIKE '%$searchEsc%' OR
        u_owner.email LIKE '%$searchEsc%' OR
        COALESCE(c.brand, m.brand) LIKE '%$searchEsc%' OR
        COALESCE(c.model, m.model) LIKE '%$searchEsc%' OR
        COALESCE(c.plate_number, m.plate_number) LIKE '%$searchEsc%'
    )";
}

$sql .= " ORDER BY days_overdue DESC, b.return_date ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}

// ============================================================================
// SET CSV HEADERS
// ============================================================================

$filename = 'overdue_bookings_' . date('Y-m-d_His') . '.csv';

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
    'Booking ID',
    'Vehicle',
    'Plate Number',
    'Vehicle Type',
    'Renter Name',
    'Renter Email',
    'Renter Phone',
    'Owner Name',
    'Owner Email',
    'Owner Phone',
    'Return Due Date',
    'Return Due Time',
    'Days Overdue',
    'Hours Overdue',
    'Severity',
    'Late Fee Amount',
    'Payment Status',
    'Payment Reference',
    'Verified Date',
    'Admin Notes',
    'Booking Created',
    'Total Rental Amount'
]);

// ============================================================================
// WRITE CSV DATA
// ============================================================================

while ($row = mysqli_fetch_assoc($result)) {
    $bookingId = '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT);
    $vehicleName = $row['brand'] . ' ' . $row['model'] . ' ' . $row['vehicle_year'];
    $severity = $row['overdue_status'] === 'severely_overdue' ? 'SEVERELY OVERDUE' : 'OVERDUE';
    
    $paymentStatus = 'Unpaid';
    if ($row['late_fee_charged']) {
        $paymentStatus = 'Paid';
    } elseif ($row['payment_status'] === 'pending') {
        $paymentStatus = 'Pending Verification';
    }
    
    $verifiedDate = !empty($row['verified_at']) ? date('Y-m-d H:i:s', strtotime($row['verified_at'])) : 'N/A';
    
    fputcsv($output, [
        $bookingId,
        $vehicleName,
        $row['plate_number'],
        ucfirst($row['vehicle_type']),
        $row['renter_name'],
        $row['renter_email'],
        $row['renter_phone'] ?? 'N/A',
        $row['owner_name'],
        $row['owner_email'],
        $row['owner_phone'] ?? 'N/A',
        date('Y-m-d', strtotime($row['return_date'])),
        date('H:i', strtotime($row['return_time'])),
        $row['days_overdue'],
        $row['hours_overdue'],
        $severity,
        number_format($row['late_fee_amount'], 2),
        $paymentStatus,
        $row['payment_reference'] ?? 'N/A',
        $verifiedDate,
        $row['admin_notes'] ?? '',
        date('Y-m-d H:i:s', strtotime($row['created_at'])),
        number_format($row['total_amount'], 2)
    ]);
}

fclose($output);
mysqli_close($conn);
exit;
