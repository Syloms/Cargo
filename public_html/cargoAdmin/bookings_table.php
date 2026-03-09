<?php
require_once "include/db.php";

$search  = $_GET["search"] ?? "";
$status  = $_GET["status"] ?? "";
$payment = $_GET["payment"] ?? "";
$date    = $_GET["date"] ?? "";

$where = "WHERE 1";

// Search
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
    c.brand, c.model, c.car_year,
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

while ($row = mysqli_fetch_assoc($result)) {

    $bookingId = "#BK-" . str_pad($row['id'], 4, "0", STR_PAD_LEFT);
    ?>

<tr>
    <td><?= $bookingId ?></td>
    <td><?= $row['renter_name'] ?></td>
    <td><?= $row['owner_name'] ?></td>
    <td><?= $row['brand'] . " " . $row['model'] ?></td>
    <td><?= $row['pickup_date'] . " - " . $row['return_date'] ?></td>
    <td><?= $row['total_amount'] ?></td>
    <td><?= $row['status'] ?></td>
    <td><?= $row['payment_status'] ?></td>
    <td>
        <button onclick="openBookingModal(<?= $row['id'] ?>)" class="action-btn view">
            <i class="bi bi-eye"></i>
        </button>
    </td>
</tr>

<?php } ?>
