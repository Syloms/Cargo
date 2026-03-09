<?php
session_start();
// Configuration loaded via header.php

require_once "include/db.php";
require_once "include/admin_profile.php";


$countPending = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) AS c FROM bookings WHERE status='pending'"
))['c'];

$countActive = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) AS c FROM bookings WHERE status='approved'"
))['c'];


// Count Pending
$pending = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE status='pending'")
)['c'];

// Count Confirmed / Approved
$confirmed = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE status='approved'")
)['c'];

// Count Ongoing
$ongoing = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE status='ongoing'")
)['c'];

// Count Cancelled / Rejected
$cancelled = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE status='rejected'")
)['c'];

// Pagination
$limit = 10;
$page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$offset = ($page - 1) * $limit;

// Filters
$statusFilter  = isset($_GET["status"]) ? trim($_GET["status"]) : "";
$paymentFilter = isset($_GET["payment"]) ? trim($_GET["payment"]) : "";
$search        = isset($_GET["search"]) ? trim($_GET["search"]) : "";

// Escape search
$searchEsc = mysqli_real_escape_string($conn, $search);

// -----------------------------
// BUILD WHERE CLAUSE
// -----------------------------
$where = " WHERE 1 ";

if ($statusFilter !== "" && $statusFilter !== "all") {
    $where .= " AND b.status = '" . mysqli_real_escape_string($conn, $statusFilter) . "' ";
}

if ($search !== "") {
    $where .= "
        AND (
            u1.fullname LIKE '%$searchEsc%' OR
            u2.fullname LIKE '%$searchEsc%' OR
            c.brand LIKE '%$searchEsc%' OR
            c.model LIKE '%$searchEsc%' OR
            m.brand LIKE '%$searchEsc%' OR
            m.model LIKE '%$searchEsc%'
        )
    ";
}

// -----------------------------
// MAIN BOOKING QUERY
// -----------------------------
$sql = "
SELECT 
    b.*,

    -- Vehicle info (car OR motorcycle)
    COALESCE(c.brand, m.brand) AS brand,
    COALESCE(c.model, m.model) AS model,
    COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
    COALESCE(c.price_per_day, m.price_per_day) AS price_per_day,

    u1.fullname AS renter_name,
    u2.fullname AS owner_name,
    
    -- Payment info
    p.payment_status,
    p.id AS payment_id

FROM bookings b

LEFT JOIN cars c 
    ON b.vehicle_type = 'car' AND b.car_id = c.id

LEFT JOIN motorcycles m 
    ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id

LEFT JOIN users u1 ON b.user_id = u1.id
LEFT JOIN users u2 ON b.owner_id = u2.id
LEFT JOIN payments p ON b.id = p.booking_id

$where
ORDER BY b.created_at DESC
LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("SQL ERROR: " . mysqli_error($conn) . "<br>Query: <pre>$sql</pre>");
}

// -----------------------------
// COUNT QUERY (FOR PAGINATION)
// SAME WHERE FILTERS
// -----------------------------
$countSql = "
SELECT COUNT(*) AS total
FROM bookings b
LEFT JOIN cars c ON b.car_id = c.id
LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
LEFT JOIN users u1 ON b.user_id = u1.id
LEFT JOIN users u2 ON b.owner_id = u2.id
$where
";

$countRes = mysqli_query($conn, $countSql);

if (!$countRes) {
    die("COUNT SQL ERROR: " . mysqli_error($conn) . "<br>Query: <pre>$countSql</pre>");
}

$totalRows = mysqli_fetch_assoc($countRes)['total'];
$totalPages = max(1, ceil($totalRows / $limit));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bookings Management - CarGo Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="include/admin-styles.css" rel="stylesheet">
  <link href="include/notifications.css" rel="stylesheet">
  <link href="include/modal-theme-standardized.css" rel="stylesheet">
</head>
<body>

<div class="dashboard-wrapper">
  <!-- Include Sidebar -->
  <?php include 'include/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
      <h1 class="page-title">
        <i class="bi bi-book"></i>
        Bookings Management
      </h1>
      <div class="user-profile">
    <div class="notification-dropdown">
        <button class="notification-btn" title="Notifications">
            <i class="bi bi-bell"></i>
            <span class="notification-badge"></span>
        </button>
    </div>
    <div class="user-avatar">
        <img src="<?= $currentAdminAvatarUrl ?>" alt="<?= htmlspecialchars($currentAdminName) ?>" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($currentAdminName) ?>&background=1a1a1a&color=fff';">
    </div>
</div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <!-- Pending -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <i class="bi bi-clock-history"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            +12%
          </div>
        </div>
        <div class="stat-value"><?= $pending ?></div>
        <div class="stat-label">Pending Bookings</div>
      </div>

      <!-- Confirmed -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <i class="bi bi-check-circle"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            +18%
          </div>
        </div>
        <div class="stat-value"><?= $confirmed ?></div>
        <div class="stat-label">Confirmed Bookings</div>
      </div>

      <!-- Ongoing -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <i class="bi bi-car-front-fill"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            +8%
          </div>
        </div>
        <div class="stat-value"><?= $ongoing ?></div>
        <div class="stat-label">Ongoing Rentals</div>
      </div>

      <!-- Cancelled -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <i class="bi bi-x-circle"></i>
          </div>
          <div class="stat-trend down">
            <i class="bi bi-arrow-down"></i>
            -5%
          </div>
        </div>
        <div class="stat-value"><?= $cancelled ?></div>
        <div class="stat-label">Cancelled Bookings</div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
      <div class="filter-row">
        <div class="search-box">
          <input type="text" id="searchInput" placeholder="Search by renter, owner, or car type..." value="<?= htmlspecialchars($search) ?>">
          <i class="bi bi-search"></i>
        </div>

        <select class="filter-dropdown" id="statusFilter">
          <option value="">All Status</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Confirmed</option>
          <option value="ongoing" <?= $statusFilter === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
          <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
          <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>

        <select class="filter-dropdown" id="paymentFilter">
          <option value="">Payment Status</option>
          <option value="paid">Paid</option>
          <option value="unpaid">Unpaid</option>
          <option value="partial">Partial</option>
        </select>

        <select class="filter-dropdown" id="dateFilter">
          <option value="">This Month</option>
          <option value="last_month">Last Month</option>
          <option value="3_months">Last 3 Months</option>
          <option value="year">This Year</option>
        </select>

        <button class="export-btn" onclick="exportBookings()">
          <i class="bi bi-download"></i>
          Export
        </button>
      </div>
    </div>

    <!-- Table Section -->
    <div class="table-section">
      <div class="section-header">
        <h2 class="section-title">All Bookings</h2>
        <div class="table-controls">
          <!-- ALL -->
          <a href="bookings.php" class="table-btn <?= ($statusFilter == "" || $statusFilter == "all") ? 'active' : '' ?>">
              All (<?= $totalRows ?>)
          </a>

          <!-- PENDING -->
          <a href="bookings.php?status=pending" class="table-btn <?= ($statusFilter == "pending") ? 'active' : '' ?>">
              Pending (<?= $countPending ?>)
          </a>

          <!-- ACTIVE = APPROVED / ONGOING -->
          <a href="bookings.php?status=approved" class="table-btn <?= ($statusFilter == "approved") ? 'active' : '' ?>">
              Active (<?= $countActive ?>)
          </a>
        </div>
      </div>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Renter</th>
              <th>Owner</th>
              <th>Vehicle Details</th>
              <th>Rental Period</th>
              <th>Pickup Info</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="bookingsTableBody">
<?php 
if (mysqli_num_rows($result) == 0) { ?>
    <tr>
        <td colspan="10" class="text-center py-4">
            <div style="padding: 40px 20px;">
              <i class="bi bi-inbox" style="font-size: 48px; color: #ddd;"></i>
              <p style="margin-top: 16px; color: #999; font-size: 14px;">No bookings found matching your criteria.</p>
            </div>
        </td>
    </tr>
<?php }

while ($row = mysqli_fetch_assoc($result)): 
    $bookingId = "#BK-" . str_pad($row['id'], 4, "0", STR_PAD_LEFT);
    $renter = $row['renter_name'];
    $owner  = $row['owner_name'];
    $car    = $row['brand'] . " " . $row['model'];
    
    // Calculate days
    $pickup = strtotime($row['pickup_date']);
    $return = strtotime($row['return_date']);
    $days = max(1, ceil(($return - $pickup) / 86400));

    // Status color classes
    $statusClass = [
        "pending" => "pending",
        "approved" => "confirmed",
        "ongoing" => "confirmed",
        "completed" => "approved",
        "rejected" => "cancelled",
        "cancelled" => "cancelled"
    ][$row["status"]] ?? "pending";

    // Determine payment status and class
    $paymentStatus = $row['payment_status'] ?? 'unpaid';
    $paymentClass = "unpaid"; // default
    $paymentLabel = "Unpaid";
    
    if ($paymentStatus === 'verified' || $paymentStatus === 'paid') {
        $paymentClass = "paid";
        $paymentLabel = "Paid";
    } elseif ($paymentStatus === 'pending') {
        $paymentClass = "pending";
        $paymentLabel = "Pending";
    } elseif ($paymentStatus === 'refunded') {
        $paymentClass = "refunded";
        $paymentLabel = "Refunded";
    }
?>
    <tr>
        <td>
          <strong><?= $bookingId ?></strong><br>
          <small style="color:#999;"><?= date("M d, Y", strtotime($row['created_at'])) ?></small>
        </td>

        <!-- Renter -->
        <td>
            <div class="user-cell">
                <div class="user-avatar-small">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($renter) ?>&background=1a1a1a&color=fff">
                </div>
                <span><?= htmlspecialchars($renter) ?></span>
            </div>
        </td>

        <!-- Owner -->
        <td>
            <div class="user-cell">
                <div class="user-avatar-small">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($owner) ?>&background=1a1a1a&color=fff">
                </div>
                <span><?= htmlspecialchars($owner) ?></span>
            </div>
        </td>

        <!-- Car Details -->
        <td>
            <strong><?= htmlspecialchars($car) ?></strong><br>
            <small style="color:#999;"><?= $row['vehicle_year'] ?> • ₱<?= number_format($row['price_per_day'], 0) ?>/day</small>
        </td>

        <!-- Rental Period -->
        <td>
            <strong><?= date("M d", strtotime($row['pickup_date'])) ?> - <?= date("M d, Y", strtotime($row['return_date'])) ?></strong><br>
            <small style="color:#999;"><?= $days ?> day<?= $days > 1 ? 's' : '' ?></small>
        </td>

        <!-- Pickup Info -->
        <td>
            <strong><?= date("M d, Y", strtotime($row['pickup_date'])) ?></strong><br>
            <small style="color:#999;"><?= $row['pickup_time'] ?></small>
        </td>

        <!-- Amount -->
        <td>
          <strong style="font-size: 15px;">₱<?= number_format($row['total_amount'], 2) ?></strong>
        </td>

        <!-- Status -->
        <td>
            <span class="status-badge <?= $statusClass ?>">
                <?= ucfirst($row['status']) ?>
            </span>
        </td>

        <!-- Payment -->
        <td>
            <span class="payment-badge <?= $paymentClass ?>"><?= $paymentLabel ?></span>
        </td>

        <!-- Actions -->
        <td>
            <div class="action-buttons">
                <!-- View (Modal) -->
                <button 
                    class="action-btn view" 
                    data-id="<?= $row['id'] ?>" 
                    onclick="openBookingModal(<?= $row['id'] ?>)"
                    title="View Details">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </td>
    </tr>
<?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination-section">
        <div class="pagination-info">
            Showing 
            <strong><?= $offset + 1 ?></strong> -
            <strong><?= min($offset + $limit, $totalRows) ?></strong>
            of 
            <strong><?= $totalRows ?></strong>
            bookings
        </div>

        <div class="pagination-controls">
            <!-- Previous -->
            <?php if ($page > 1): ?>
            <a href="?page=<?= max(1, $page - 1) ?><?= $statusFilter ? '&status='.$statusFilter : '' ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="page-btn">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php else: ?>
            <span class="page-btn disabled">
                <i class="bi bi-chevron-left"></i>
            </span>
            <?php endif; ?>

            <!-- Page buttons -->
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++): 
            ?>
                <a href="?page=<?= $i ?><?= $statusFilter ? '&status='.$statusFilter : '' ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                   class="page-btn <?= ($i == $page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= min($totalPages, $page + 1) ?><?= $statusFilter ? '&status='.$statusFilter : '' ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="page-btn">
                <i class="bi bi-chevron-right"></i>
            </a>
            <?php else: ?>
            <span class="page-btn disabled">
                <i class="bi bi-chevron-right"></i>
            </span>
            <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Booking Details Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" id="modalContent"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function openBookingModal(id) {
    fetch("fetch_booking_details.php?id=" + id)
        .then(response => {
            if (!response.ok) {
                throw new Error("Failed to load modal content");
            }
            return response.text();
        })
        .then(html => {
            document.getElementById("modalContent").innerHTML = html;
            let modal = new bootstrap.Modal(document.getElementById('bookingModal'));
            modal.show();
        })
        .catch(err => {
            console.error(err);
            alert("Error loading booking details.");
        });
}

function updateStatus(bookingId, newStatus) {
    const action = newStatus === 'approved' ? 'approve' : 'reject';
    const message = `Are you sure you want to ${action} this booking?`;
    
    if (!confirm(message)) return;
    
    // Add your backend API call here
    alert(`Booking ${bookingId} ${action}d successfully!`);
    location.reload();
}

function loadBookings() {
    let search  = document.getElementById("searchInput").value;
    let status  = document.getElementById("statusFilter").value;
    let payment = document.getElementById("paymentFilter").value;
    let date    = document.getElementById("dateFilter").value;

    let params = new URLSearchParams({
        search: search,
        status: status,
        payment: payment,
        date: date
    });

    window.location.href = "bookings.php?" + params.toString();
}

// Live search with debounce
let searchTimeout;
document.getElementById("searchInput").addEventListener("keyup", function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadBookings, 500);
});

// Dropdown filters
document.getElementById("statusFilter").addEventListener("change", loadBookings);
document.getElementById("paymentFilter").addEventListener("change", loadBookings);
document.getElementById("dateFilter").addEventListener("change", loadBookings);

function exportBookings() {
    let search  = document.getElementById("searchInput").value;
    let status  = document.getElementById("statusFilter").value;
    let payment = document.getElementById("paymentFilter").value;
    let date    = document.getElementById("dateFilter").value;

    let params = new URLSearchParams({
        search: search,
        status: status,
        payment: payment,
        date: date
    });

    window.location.href = "export_bookings.php?" + params.toString();
}
</script>

<style>
/* Additional Styles for Enhanced UI */
.page-btn.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

.table-responsive table tbody tr {
  transition: all 0.2s ease;
}

.table-responsive table tbody tr:hover {
  background: #f8f9fa;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.action-btn {
  transition: all 0.2s ease;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-card {
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.filter-dropdown, .search-box input {
  transition: all 0.2s ease;
}

.filter-dropdown:focus, .search-box input:focus {
  box-shadow: 0 0 0 3px rgba(26, 26, 26, 0.1);
}

/* Force contact-modal design overrides - Enhanced with icons & larger text */
.modal-dialog {
  max-width: 800px !important;
}

.modal-dialog-scrollable .modal-content {
  max-height: 85vh !important;
}

.modal-header {
  background: #ffffff !important;
  color: #111827 !important;
  padding: 40px 40px 32px 40px !important;
  border-bottom: none !important;
}

.modal-header h3,
.modal-header h5,
.modal-header .modal-title {
  font-size: 32px !important;
  font-weight: 700 !important;
  color: #111827 !important;
  letter-spacing: -0.5px !important;
  font-family: 'Sora', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
  display: flex !important;
  align-items: center !important;
  gap: 14px !important;
}

.modal-header .modal-title i,
.modal-header h3 i,
.modal-header h5 i {
  font-size: 34px !important;
  color: #6b7280 !important;
}

.modal-header .btn-close {
  width: 40px !important;
  height: 40px !important;
  background: #f3f4f6 !important;
  border-radius: 10px !important;
  opacity: 1 !important;
  filter: none !important;
  padding: 0 !important;
  background-image: none !important;
  position: relative !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
}

.modal-header .btn-close::after {
  content: '✕' !important;
  position: absolute !important;
  top: 50% !important;
  left: 50% !important;
  transform: translate(-50%, -50%) !important;
  font-size: 20px !important;
  color: #6b7280 !important;
  font-weight: 400 !important;
  line-height: 1 !important;
}

.modal-header .btn-close:hover {
  background: #e5e7eb !important;
}

.modal-header .btn-close:hover::after {
  color: #111827 !important;
}

.modal-body {
  padding: 40px !important;
  font-family: 'Sora', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
  font-size: 18px !important;
  line-height: 1.7 !important;
}

.modal-body p {
  font-size: 18px !important;
  line-height: 1.7 !important;
  margin-bottom: 14px !important;
}

.modal-body .text-muted,
.modal-body small {
  font-size: 16px !important;
  color: #6b7280 !important;
}

.modal-body strong {
  font-weight: 600 !important;
  color: #111827 !important;
}

.modal-body h6,
.modal-body .section-title {
  font-size: 20px !important;
  font-weight: 650 !important;
  color: #111827 !important;
  margin-bottom: 16px !important;
  margin-top: 28px !important;
  display: flex !important;
  align-items: center !important;
  gap: 10px !important;
}

.modal-body h6:first-child,
.modal-body .section-title:first-child {
  margin-top: 0 !important;
}

.modal-body h6 i,
.modal-body .section-title i {
  font-size: 22px !important;
  color: #9ca3af !important;
}

.modal-footer {
  padding: 28px 40px 40px 40px !important;
  border-top: 1px solid #f0f0f0 !important;
  background: #ffffff !important;
  gap: 14px !important;
}

.modal-footer .btn {
  font-size: 16px !important;
  font-weight: 600 !important;
  padding: 16px 32px !important;
  border-radius: 12px !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 10px !important;
}

.modal-footer .btn i {
  font-size: 18px !important;
}

.info-grid {
  display: grid !important;
  grid-template-columns: repeat(2, 1fr) !important;
  gap: 0 !important;
  border: 1px solid #f0f0f0 !important;
  border-radius: 14px !important;
  overflow: hidden !important;
  margin-bottom: 24px !important;
}

.info-item {
  padding: 20px 24px !important;
  background: #ffffff !important;
  border-radius: 0 !important;
  border-right: 1px solid #f0f0f0 !important;
  border-bottom: 1px solid #f0f0f0 !important;
}

.info-item:nth-child(2n) {
  border-right: none !important;
}

.info-label {
  font-size: 15px !important;
  color: #9ca3af !important;
  font-weight: 500 !important;
  margin-bottom: 6px !important;
}

.info-value {
  font-size: 18px !important;
  color: #111827 !important;
  font-weight: 600 !important;
  letter-spacing: -0.2px !important;
}
</style>
<script src="include/notifications.js"></script>
</body>
</html>
