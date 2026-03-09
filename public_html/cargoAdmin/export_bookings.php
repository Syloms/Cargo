<?php
/**
 * ============================================================================
 * EXPORT BOOKINGS TO CSV - Admin Download
 * ============================================================================
 */

session_start();
require_once "include/db.php";

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

// Read filters (same as bookings_table.php)
$search  = $_GET["search"] ?? "";
$status  = $_GET["status"] ?? "";
$payment = $_GET["payment"] ?? "";
$date    = $_GET["date"] ?? "";

$where = "WHERE 1";

// Search filter
if ($search !== "") {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (
        u1.fullname LIKE '%$s%' OR 
        u2.fullname LIKE '%$s%' OR 
        c.brand LIKE '%$s%' OR 
        c.model LIKE '%$s%'
    )";
}

// Status filter
if ($status !== "") {
    $where .= " AND b.status = '$status' ";
}

// Payment filter
if ($payment !== "") {
    $where .= " AND b.payment_status = '$payment' ";
}

// Date filter
switch ($date) {
    case "last_month":
        $where .= " AND MONTH(b.created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH) ";
        break;

    case "3_months":
        $where .= " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ";
        break;

    case "year":
        $where .= " AND YEAR(b.created_at) = YEAR(CURRENT_DATE) ";
        break;
}

// Main query
$sql = "
SELECT 
    b.*,
    c.brand, c.model,
    u1.fullname AS renter_name,
    u2.fullname AS owner_name
FROM bookings b
LEFT JOIN cars c ON b.car_id = c.id
LEFT JOIN users u1 ON b.user_id = u1.id
LEFT JOIN users u2 ON b.owner_id = u2.id
$where
ORDER BY b.id DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}

// CSV Headers
$filename = 'bookings_export_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Output buffer
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Title Row
fputcsv($output, [
    "Booking ID", "Renter", "Owner",
    "Car", "Pickup Date", "Return Date",
    "Total Amount", "Status", "Payment Status"
]);

// CSV Data Rows
while ($row = mysqli_fetch_assoc($result)) {

    $bookingId = "#BK-" . str_pad($row['id'], 4, "0", STR_PAD_LEFT);

    fputcsv($output, [
        $bookingId,
        $row["renter_name"],
        $row["owner_name"],
        $row["brand"] . " " . $row["model"],
        $row["pickup_date"],
        $row["return_date"],
        "₱" . number_format($row["total_amount"], 2),
        ucfirst($row["status"]),
        ucfirst($row["payment_status"])
    ]);
}

fclose($output);
mysqli_close($conn);
exit;
