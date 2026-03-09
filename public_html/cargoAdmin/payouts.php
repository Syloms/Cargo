<?php
/**
 * ============================================================================
 * PAYOUTS MANAGEMENT - CarGo Admin
 * Manage payouts to car owners
 * ============================================================================
 */

session_start();
include "include/db.php";
include "include/admin_profile.php";

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   PAYOUT STATISTICS
   ========================================================= */

// Pending payouts (escrow released but payout not completed)
$pendingPayouts = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM bookings 
     WHERE escrow_status='released_to_owner' AND payout_status IN ('pending', 'processing')"
))['c'];

// Total pending amount
$pendingAmount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(owner_payout), 0) AS total 
     FROM bookings 
     WHERE escrow_status='released_to_owner' AND payout_status IN ('pending', 'processing')"
))['total'];

// Completed payouts this month
$completedThisMonth = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM payouts 
     WHERE status='completed' 
     AND MONTH(processed_at) = MONTH(NOW()) 
     AND YEAR(processed_at) = YEAR(NOW())"
))['c'];

// Total paid this month
$paidThisMonth = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(net_amount), 0) AS total 
     FROM payouts 
     WHERE status='completed' 
     AND MONTH(processed_at) = MONTH(NOW()) 
     AND YEAR(processed_at) = YEAR(NOW())"
))['total'];

/* =========================================================
   PAGINATION & FILTERS
   ========================================================= */
$limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 10;
$page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$offset = ($page - 1) * $limit;

$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'pending';
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$minAmount = isset($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$maxAmount = isset($_GET['max_amount']) ? floatval($_GET['max_amount']) : 0;

$bookingFocus = isset($_GET['booking_id']) 
    ? intval($_GET['booking_id']) 
    : null;
if ($bookingFocus) {
    $statusFilter = 'pending';
}


/* =========================================================
   BUILD WHERE CLAUSE
   ========================================================= */
$where = " WHERE 1 ";
if ($bookingFocus) {
    $where .= " AND b.id = $bookingFocus ";
}

switch($statusFilter) {
    case 'pending':
    $where .= " 
        AND b.escrow_status IN ('held', 'released_to_owner')
        AND b.payout_status IN ('pending', 'processing') 
    ";
    break;
    case 'completed':
        $where .= " AND p.status = 'completed' ";
        break;
    case 'processing':
        $where .= " AND p.status = 'processing' ";
        break;
    case 'failed':
        $where .= " AND p.status = 'failed' ";
        break;
    case 'all':
        // Show all payouts
        break;
}

// Search filter
if (!empty($searchQuery)) {
    $searchEsc = mysqli_real_escape_string($conn, $searchQuery);
    $where .= " AND (
        u.fullname LIKE '%$searchEsc%' OR
        u.email LIKE '%$searchEsc%' OR
        b.id LIKE '%$searchEsc%' OR
        CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) LIKE '%$searchEsc%'
    )";
}

// Date range filter
if (!empty($dateFrom)) {
    $dateFromEsc = mysqli_real_escape_string($conn, $dateFrom);
    if ($statusFilter == 'pending') {
        $where .= " AND DATE(b.escrow_released_at) >= '$dateFromEsc' ";
    } else {
        $where .= " AND DATE(p.created_at) >= '$dateFromEsc' ";
    }
}

if (!empty($dateTo)) {
    $dateToEsc = mysqli_real_escape_string($conn, $dateTo);
    if ($statusFilter == 'pending') {
        $where .= " AND DATE(b.escrow_released_at) <= '$dateToEsc' ";
    } else {
        $where .= " AND DATE(p.created_at) <= '$dateToEsc' ";
    }
}

// Amount range filter
if ($minAmount > 0) {
    if ($statusFilter == 'pending') {
        $where .= " AND b.owner_payout >= $minAmount ";
    } else {
        $where .= " AND p.net_amount >= $minAmount ";
    }
}

if ($maxAmount > 0) {
    if ($statusFilter == 'pending') {
        $where .= " AND b.owner_payout <= $maxAmount ";
    } else {
        $where .= " AND p.net_amount <= $maxAmount ";
    }
}

// Determine ORDER BY clause based on sort option
$orderBy = "";
switch($sortBy) {
    case 'date_asc':
        $orderBy = $statusFilter == 'pending' ? "b.escrow_released_at ASC" : "p.created_at ASC";
        break;
    case 'date_desc':
        $orderBy = $statusFilter == 'pending' ? "b.escrow_released_at DESC" : "p.created_at DESC";
        break;
    case 'amount_asc':
        $orderBy = $statusFilter == 'pending' ? "b.owner_payout ASC" : "p.net_amount ASC";
        break;
    case 'amount_desc':
        $orderBy = $statusFilter == 'pending' ? "b.owner_payout DESC" : "p.net_amount DESC";
        break;
    case 'owner_asc':
        $orderBy = "u.fullname ASC";
        break;
    case 'owner_desc':
        $orderBy = "u.fullname DESC";
        break;
    default:
        $orderBy = $statusFilter == 'pending' ? "b.escrow_released_at DESC" : "p.created_at DESC";
}

/* =========================================================
   MAIN QUERY - PENDING & COMPLETED PAYOUTS
   ========================================================= */
if ($statusFilter == 'pending') {
    // Show bookings ready for payout
    $sql = "
        SELECT 
            b.id AS booking_id,
            b.owner_id,
            b.owner_payout,
            b.platform_fee,
            b.total_amount,
            b.escrow_status,
            b.payout_status,
            b.escrow_released_at,
            b.return_date,
            b.pickup_date,
            
            -- Owner info
            u.fullname AS owner_name,
            u.email AS owner_email,
            u.gcash_number,
            u.gcash_name,
            
            -- Car info
            COALESCE(c.brand, m.brand) AS brand,
            COALESCE(c.model, m.model) AS model,
            COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
            b.vehicle_type,
            
            -- Renter info
            u2.fullname AS renter_name
            
        FROM bookings b
        JOIN users u ON b.owner_id = u.id
        LEFT JOIN users u2 ON b.user_id = u2.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        $where
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset
    ";
} else {
    // Show payout records
    $sql = "
        SELECT 
            p.id AS payout_id,
            p.booking_id,
            p.owner_id,
            p.amount,
            p.platform_fee,
            p.net_amount,
            p.payout_method,
            p.payout_account,
            p.status,
            p.scheduled_at,
            p.processed_at,
            p.completion_reference,
            p.transfer_proof,
            p.created_at,
            
            -- Booking info
            b.pickup_date,
            b.return_date,
            b.vehicle_type,
            
            -- Owner info
            u.fullname AS owner_name,
            u.email AS owner_email,
            u.gcash_number,
            u.gcash_name,
            
            -- Car info
            COALESCE(c.brand, m.brand) AS brand,
            COALESCE(c.model, m.model) AS model,
            COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year
            
        FROM payouts p
        JOIN bookings b ON p.booking_id = b.id
        JOIN users u ON p.owner_id = u.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        $where
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset
    ";
}

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("SQL ERROR: " . mysqli_error($conn));
}

// Count for pagination
$countSql = "SELECT COUNT(*) AS total FROM (" . str_replace("LIMIT $limit OFFSET $offset", "", $sql) . ") AS count_table";
$countRes = mysqli_query($conn, $countSql);
$totalRows = mysqli_fetch_assoc($countRes)['total'];
$totalPages = max(1, ceil($totalRows / $limit));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payouts Management - CarGo Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="include/admin-styles.css" rel="stylesheet">
  <link href="include/modal-theme-standardized.css" rel="stylesheet">
  <link href="include/notifications.css" rel="stylesheet">
  <style>
    /* Force contact-modal design overrides - Enhanced with icons & larger text */
    .modal-dialog {
      max-width: 900px !important;
    }

    .modal-dialog-scrollable .modal-content {
      max-height: 90vh !important;
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

    .modal-header .btn-close,
    .modal-header .btn-close-white {
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
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    .modal-header .btn-close::after,
    .modal-header .btn-close-white::after {
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

    .modal-header .btn-close:hover,
    .modal-header .btn-close-white:hover {
      background: #e5e7eb !important;
      transform: scale(1.05) !important;
    }

    .modal-header .btn-close:hover::after,
    .modal-header .btn-close-white:hover::after {
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

    /* Info Grid - Contact Modal Style */
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
      letter-spacing: 0.01em !important;
      text-transform: none !important;
    }

    .info-value {
      font-size: 18px !important;
      color: #111827 !important;
      font-weight: 600 !important;
      letter-spacing: -0.2px !important;
    }

    /* Action Card - Contact Modal Style */
    .action-card {
      background: #f9fafb !important;
      padding: 18px !important;
      border: 1px solid #f0f0f0 !important;
      border-radius: 12px !important;
      margin-bottom: 18px !important;
    }

    .action-card h6 {
      font-weight: 650 !important;
      font-size: 14px !important;
      color: #111827 !important;
      margin-bottom: 14px !important;
      letter-spacing: -0.2px !important;
      display: flex !important;
      align-items: center !important;
      gap: 8px !important;
    }

    .action-card h6 i {
      font-size: 16px !important;
      color: #9ca3af !important;
    }

    /* Modal Action Buttons */
    .modal-action-btn,
    button.modal-action-btn {
      width: 100% !important;
      padding: 13px 16px !important;
      border: none !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      font-size: 13px !important;
      cursor: pointer !important;
      transition: all 0.15s ease !important;
      display: flex !important;
      align-items: center !important;
      gap: 8px !important;
      justify-content: center !important;
      letter-spacing: -0.2px !important;
    }

    .modal-action-btn:hover,
    button.modal-action-btn:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    }
  </style>
</head>
<body>

<div class="dashboard-wrapper">
  <?php include 'include/sidebar.php'; ?>

  <main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
      <h1 class="page-title">
        <i class="bi bi-cash-stack"></i>
        Payouts Management
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
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <i class="bi bi-hourglass-split"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            <?= $pendingPayouts ?>
          </div>
        </div>
        <div class="stat-value"><?= formatCurrency($pendingAmount) ?></div>
        <div class="stat-label">Pending Payouts</div>
        <div class="stat-detail"><?= $pendingPayouts ?> owner<?= $pendingPayouts != 1 ? 's' : '' ?> waiting</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <i class="bi bi-check-circle"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            +15%
          </div>
        </div>
        <div class="stat-value"><?= $completedThisMonth ?></div>
        <div class="stat-label">Completed This Month</div>
        <div class="stat-detail"><?= formatCurrency($paidThisMonth) ?> disbursed</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <i class="bi bi-graph-up"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            +8%
          </div>
        </div>
        <div class="stat-value">
          <?php
          $avgPayout = $completedThisMonth > 0 ? $paidThisMonth / $completedThisMonth : 0;
          echo formatCurrency($avgPayout);
          ?>
        </div>
        <div class="stat-label">Avg Payout Amount</div>
        <div class="stat-detail">Per transaction</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <i class="bi bi-clock-history"></i>
          </div>
          <div class="stat-trend down">
            <i class="bi bi-arrow-down"></i>
            -2%
          </div>
        </div>
        <div class="stat-value">2.5 days</div>
        <div class="stat-label">Avg Processing Time</div>
        <div class="stat-detail">From release to payout</div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
      <form method="GET" id="filterForm">
        <div class="filter-row">
          <div class="search-box">
            <input type="text" name="search" id="searchInput" 
                   placeholder="Search by owner, booking ID, or vehicle..." 
                   value="<?= htmlspecialchars($searchQuery) ?>">
            <i class="bi bi-search"></i>
          </div>

          <select class="filter-dropdown" name="status" id="statusFilter" onchange="this.form.submit()">
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Payouts</option>
            <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Payouts</option>
          </select>

          <select class="filter-dropdown" name="sort" id="sortBy" onchange="this.form.submit()">
            <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>📅 Newest First</option>
            <option value="date_asc" <?= $sortBy === 'date_asc' ? 'selected' : '' ?>>📅 Oldest First</option>
            <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>💰 Highest Amount</option>
            <option value="amount_asc" <?= $sortBy === 'amount_asc' ? 'selected' : '' ?>>💰 Lowest Amount</option>
            <option value="owner_asc" <?= $sortBy === 'owner_asc' ? 'selected' : '' ?>>👤 Owner (A-Z)</option>
            <option value="owner_desc" <?= $sortBy === 'owner_desc' ? 'selected' : '' ?>>👤 Owner (Z-A)</option>
          </select>

          <button type="button" class="add-user-btn" onclick="toggleAdvancedFilters()" id="advancedFilterBtn">
            <i class="bi bi-funnel"></i>
            Advanced
          </button>

          <button type="button" class="add-user-btn" onclick="exportPayouts()">
            <i class="bi bi-download"></i>
            Export
          </button>
        </div>

        <!-- Advanced Filters (Hidden by default) -->
        <div id="advancedFilters" style="display: none; margin-top: 15px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label" style="font-size: 13px; font-weight: 600;">Date From</label>
              <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label" style="font-size: 13px; font-weight: 600;">Date To</label>
              <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label" style="font-size: 13px; font-weight: 600;">Min Amount (₱)</label>
              <input type="number" class="form-control form-control-sm" name="min_amount" 
                     placeholder="0.00" step="100" value="<?= $minAmount > 0 ? $minAmount : '' ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label" style="font-size: 13px; font-weight: 600;">Max Amount (₱)</label>
              <input type="number" class="form-control form-control-sm" name="max_amount" 
                     placeholder="0.00" step="100" value="<?= $maxAmount > 0 ? $maxAmount : '' ?>">
            </div>
          </div>
          <div class="row g-3 mt-2">
            <div class="col-md-2">
              <label class="form-label" style="font-size: 13px; font-weight: 600;">Show Per Page</label>
              <select class="form-control form-control-sm" name="limit">
                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
              </select>
            </div>
            <div class="col-md-10 d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-search"></i> Apply Filters
              </button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">
                <i class="bi bi-x-circle"></i> Clear All
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Table Section -->
    <div class="table-section">
      <div class="section-header">
        <h2 class="section-title">
          <?php
          switch($statusFilter) {
            case 'pending': echo 'Pending Payouts'; break;
            case 'processing': echo 'Processing Payouts'; break;
            case 'completed': echo 'Completed Payouts'; break;
            case 'failed': echo 'Failed Payouts'; break;
            default: echo 'All Payouts';
          }
          ?>
        </h2>
        <div class="table-controls">
          <a href="?status=pending" class="table-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">
            Pending (<?= $pendingPayouts ?>)
          </a>
          <a href="?status=completed" class="table-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">
            Completed
          </a>
        </div>
      </div>

      <div class="table-responsive">
        <?php if (mysqli_num_rows($result) == 0): ?>
        <div style="padding: 60px 20px; text-center;">
          <i class="bi bi-inbox" style="font-size: 64px; color: #ddd;"></i>
          <p style="margin-top: 20px; color: #999; font-size: 16px;">No payouts found</p>
        </div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Payout ID</th>
              <th>Owner</th>
              <th>Booking</th>
              <th>Vehicle</th>
              <th>Amount Breakdown</th>
              <th>GCash Account</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): 
             $isFocused = $bookingFocus && $bookingFocus == $row['booking_id'];

              if ($statusFilter == 'pending') {
                // Pending payouts from bookings table
                $bookingId = $row['booking_id'];
                $ownerPayout = floatval($row['owner_payout']);
                $platformFee = floatval($row['platform_fee']);
                $totalAmount = floatval($row['total_amount']);
                $status = 'pending';
                // Use escrow_released_at if available, otherwise use return_date or current date
                $date = $row['escrow_released_at'] ?: ($row['return_date'] ?: date('Y-m-d H:i:s'));
                $payoutId = 'BK-' . str_pad($bookingId, 4, '0', STR_PAD_LEFT);
              } else {
                // Completed/processing payouts from payouts table
                $bookingId = $row['booking_id'];
                $ownerPayout = floatval($row['net_amount']);
                $platformFee = floatval($row['platform_fee']);
                $totalAmount = floatval($row['amount']);
                $status = $row['status'];
                $date = $row['processed_at'] ?: ($row['created_at'] ?: date('Y-m-d H:i:s'));
                $payoutId = 'PO-' . str_pad($row['payout_id'], 4, '0', STR_PAD_LEFT);
              }
              
              $ownerName = htmlspecialchars($row['owner_name']);
              $ownerEmail = htmlspecialchars($row['owner_email']);
              $gcashNumber = $row['gcash_number'] ?? 'Not set';
              $gcashName = $row['gcash_name'] ?? 'Not set';
              
              // DEBUG: Log GCash data for troubleshooting
              // error_log("Booking ID: {$row['booking_id']}, Owner ID: {$row['owner_id']}, GCash Number: " . var_export($row['gcash_number'], true) . ", GCash Name: " . var_export($row['gcash_name'], true));
              $vehicleName = htmlspecialchars($row['brand'] . ' ' . $row['model'] . ' ' . $row['vehicle_year']);
              
              $statusClass = [
                'pending' => 'pending',
                'processing' => 'ongoing',
                'completed' => 'verified',
                'failed' => 'cancelled'
              ][$status] ?? 'pending';
            ?>
            <tr style="<?= $isFocused ? 'background:#fff3cd; border-left:5px solid #000;' : '' ?>">
              <td>
                <strong><?= $payoutId ?></strong><br>
                <small style="color:#999;">Booking #<?= $bookingId ?></small>
              </td>
              
              <td>
                <div class="user-cell">
                  <div class="user-avatar-small">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($ownerName) ?>&background=1a1a1a&color=fff" alt="Owner">
                  </div>
                  <div class="user-info">
                    <span class="user-name"><?= $ownerName ?></span>
                    <span class="user-email"><?= $ownerEmail ?></span>
                  </div>
                </div>
              </td>
              
              <td>
                <strong>#BK-<?= str_pad($bookingId, 4, '0', STR_PAD_LEFT) ?></strong><br>
                <small style="color:#999;">
                  <?= date('M d', strtotime($row['pickup_date'])) ?> - 
                  <?= date('M d, Y', strtotime($row['return_date'])) ?>
                </small>
              </td>
              
              <td>
                <strong><?= $vehicleName ?></strong><br>
                <small style="color:#999;"><?= ucfirst($row['vehicle_type']) ?></small>
              </td>
              
              <td>
                <div style="font-size: 12px;">
                  <div><strong style="color: #000;">Total: <?= formatCurrency($totalAmount) ?></strong></div>
                  <div style="color: #dc3545;">Fee: <?= formatCurrency($platformFee) ?> (10%)</div>
                  <div style="color: #28a745; font-weight: 600;">Net: <?= formatCurrency($ownerPayout) ?></div>
                </div>
              </td>
              
              <td>
                <?php if (!empty($gcashNumber) && $gcashNumber != 'Not set' && $gcashNumber != null): ?>
                <div style="font-size: 12px;">
                  <strong><?= htmlspecialchars($gcashNumber) ?></strong><br>
                  <small style="color:#999;"><?= htmlspecialchars($gcashName) ?></small>
                </div>
                <?php else: ?>
                <div>
                  <span style="color: #dc3545; font-size: 12px; display: block;">⚠️ Not configured</span>
                  <button class="btn btn-sm btn-outline-primary mt-1" 
                          onclick="openOwnerProfile(<?= $row['owner_id'] ?>)" 
                          title="Edit Owner Profile"
                          style="font-size: 10px; padding: 2px 8px;">
                    <i class="bi bi-pencil"></i> Configure
                  </button>
                  <small class="d-block mt-1" style="color: #999; font-size: 10px;">
                    Owner ID: <?= $row['owner_id'] ?>
                  </small>
                </div>
                <?php endif; ?>
              </td>
              
              <td>
                <span class="status-badge <?= $statusClass ?>">
                  <?= ucfirst($status) ?>
                </span>
              </td>
              
              <td>
                <?php if (!empty($date) && strtotime($date) > 0): ?>
                  <strong><?= date('M d, Y', strtotime($date)) ?></strong><br>
                  <small style="color:#999;"><?= date('h:i A', strtotime($date)) ?></small>
                <?php else: ?>
                  <span style="color: #999; font-style: italic;">Pending</span>
                <?php endif; ?>
              </td>
              
              <td>
                <div class="action-buttons">
                  <?php 
                  // Show Process Payout button ONLY when all conditions are met:
                  // 1. Status is pending
                  // 2. Escrow is released to owner
                  // 3. Owner has GCash configured
                  $canProcessPayout = (
                    $status == 'pending' && 
                    $row['escrow_status'] == 'released_to_owner' &&
                    !empty($row['gcash_number']) && 
                    $row['gcash_number'] != 'Not set'
                  );
                  
                  if ($canProcessPayout): 
                  ?>
                  <button class="action-btn approve" 
                          onclick='openPayoutModal(<?= json_encode([
                            "booking_id" => $bookingId,
                            "owner_name" => $ownerName,
                            "owner_payout" => $ownerPayout,
                            "gcash_number" => $gcashNumber,
                            "gcash_name" => $gcashName,
                            "vehicle_name" => $vehicleName
                          ]) ?>)'
                          title="Process Payout">
                    <i class="bi bi-cash-coin"></i>
                  </button>
                  <?php elseif ($status == 'pending'): ?>
                    <?php if ($row['escrow_status'] != 'released_to_owner'): ?>
                    <span class="badge bg-warning text-dark" style="font-size: 11px;">
                      ⏳ Escrow not released
                    </span>
                    <?php elseif (empty($row['gcash_number']) || $row['gcash_number'] == 'Not set'): ?>
                    <span class="badge bg-danger" style="font-size: 11px;">
                      ⚠️ GCash not configured
                    </span>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <button class="action-btn view" 
                          onclick='viewPayoutDetails(<?= json_encode($row) ?>)'
                          title="View Details">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): 
        // Build query string preserving all filters
        $queryParams = [
          'status' => $statusFilter,
          'sort' => $sortBy,
          'search' => $searchQuery,
          'date_from' => $dateFrom,
          'date_to' => $dateTo,
          'min_amount' => $minAmount > 0 ? $minAmount : '',
          'max_amount' => $maxAmount > 0 ? $maxAmount : '',
          'limit' => $limit
        ];
        $queryParams = array_filter($queryParams, function($v) { return $v !== ''; });
      ?>
      <div class="pagination-section">
        <div class="pagination-info">
          Showing <strong><?= $offset + 1 ?></strong> - <strong><?= min($offset + $limit, $totalRows) ?></strong>
          of <strong><?= $totalRows ?></strong> payout<?= $totalRows != 1 ? 's' : '' ?>
        </div>
        <div class="pagination-controls">
          <?php if ($page > 1): 
            $prevParams = $queryParams;
            $prevParams['page'] = $page - 1;
          ?>
          <a href="?<?= http_build_query($prevParams) ?>" class="page-btn">
            <i class="bi bi-chevron-left"></i>
          </a>
          <?php endif; ?>
          
          <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): 
            $pageParams = $queryParams;
            $pageParams['page'] = $i;
          ?>
          <a href="?<?= http_build_query($pageParams) ?>" 
             class="page-btn <?= $i == $page ? 'active' : '' ?>">
            <?= $i ?>
          </a>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): 
            $nextParams = $queryParams;
            $nextParams['page'] = $page + 1;
          ?>
          <a href="?<?= http_build_query($nextParams) ?>" class="page-btn">
            <i class="bi bi-chevron-right"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Payout Modal -->
<div class="modal fade" id="payoutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-payment">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-money-bill-wave"></i> Complete Payout
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="payoutForm" onsubmit="submitPayout(event)">
        <div class="modal-body">
          <div id="payoutInfo" class="mb-4"></div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">GCash Reference Number</label>
            <input type="text" class="form-control" id="payoutReference" name="reference" required
                   placeholder="Enter GCash transaction reference" minlength="8">
            <small class="text-muted">Enter the 13-digit reference number from GCash</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">GCash Number (Optional Override)</label>
            <input type="text" class="form-control" id="gcashNumber" name="gcash_number" 
                   placeholder="09XX XXX XXXX" pattern="^09\d{9}$">
            <small class="text-muted">Leave empty to use owner's saved GCash number</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">Proof of Transfer (Optional)</label>
            <input type="file" class="form-control" id="payoutProof" name="proof" accept="image/*">
            <small class="text-muted">Upload screenshot of GCash transfer</small>
          </div>
          
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>Important:</strong> Make sure you've completed the GCash transfer before submitting.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times"></i> Cancel
          </button>
          <button type="submit" class="btn btn-success" id="submitPayoutBtn">
            <i class="fas fa-check-circle"></i> Complete Payout
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" id="detailsContent"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let currentBookingId = null;

function openPayoutModal(payoutData) {
  currentBookingId = payoutData.booking_id;
  
  const infoHtml = `
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
      <h6 class="mb-3">Payout Details</h6>
      <div class="row">
        <div class="col-6">
          <small class="text-muted">Owner</small>
          <div class="fw-bold">${payoutData.owner_name}</div>
        </div>
        <div class="col-6">
          <small class="text-muted">Vehicle</small>
          <div class="fw-bold">${payoutData.vehicle_name}</div>
        </div>
      </div>
      <hr>
      <div class="row">
        <div class="col-6">
          <small class="text-muted">GCash Number</small>
          <div class="fw-bold">${payoutData.gcash_number}</div>
        </div>
        <div class="col-6">
          <small class="text-muted">Account Name</small>
          <div class="fw-bold">${payoutData.gcash_name}</div>
        </div>
      </div>
      <hr>
      <div class="text-center">
        <small class="text-muted">Amount to Transfer</small>
        <h3 class="text-success mb-0">₱${parseFloat(payoutData.owner_payout).toLocaleString('en-PH', {minimumFractionDigits: 2})}</h3>
      </div>
    </div>
  `;
  
  document.getElementById('payoutInfo').innerHTML = infoHtml;
  document.getElementById('gcashNumber').value = payoutData.gcash_number;
  
  const modal = new bootstrap.Modal(document.getElementById('payoutModal'));
  modal.show();
}

function submitPayout(e) {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  formData.append('booking_id', currentBookingId);
  
  const submitBtn = document.getElementById('submitPayoutBtn');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
  
  fetch('api/payment/complete_payout.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Payout';
    
    if (data.success) {
      alert('✅ ' + data.message);
      bootstrap.Modal.getInstance(document.getElementById('payoutModal')).hide();
      location.reload();
    } else {
      alert('❌ ' + data.message);
    }
  })
  .catch(err => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Payout';
    alert('Network error: ' + err.message);
  });
}

function viewPayoutDetails(data) {
  const gcashNumber = data.gcash_number || 'Not set';
  const gcashName = data.gcash_name || 'Not set';
  const isGcashConfigured = gcashNumber !== 'Not set';
  
  // Build details modal content
  const content = `
    <div class="modal-header">
      <h5 class="modal-title">
        <i class="fas fa-receipt"></i> Payout Details
      </h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <!-- Owner Information -->
      <div class="mb-4">
        <h6 class="text-primary mb-3"><i class="fas fa-user-tie"></i> Owner Information</h6>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Full Name:</span>
            <span class="info-value">${data.owner_name}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Email:</span>
            <span class="info-value">${data.owner_email}</span>
          </div>
        </div>
      </div>

      <!-- GCash Information -->
      <div class="mb-4">
        <h6 class="text-primary mb-3">
          <i class="fas fa-wallet"></i> GCash Account Information
          ${isGcashConfigured ? '<span class="badge bg-success ms-2">Configured</span>' : '<span class="badge bg-danger ms-2">Not Configured</span>'}
        </h6>
        ${isGcashConfigured ? `
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">GCash Number:</span>
              <span class="info-value">
                <strong>${gcashNumber}</strong>
                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('${gcashNumber}'); alert('Copied!')" title="Copy">
                  <i class="fas fa-copy"></i>
                </button>
              </span>
            </div>
            <div class="info-item">
              <span class="info-label">Account Name:</span>
              <span class="info-value">
                <strong>${gcashName}</strong>
                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('${gcashName}'); alert('Copied!')" title="Copy">
                  <i class="fas fa-copy"></i>
                </button>
              </span>
            </div>
          </div>
        ` : `
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>GCash account not configured!</strong><br>
            The owner needs to update their GCash details before payout can be processed.
          </div>
        `}
      </div>

      <!-- Booking Information -->
      <div class="mb-4">
        <h6 class="text-primary mb-3"><i class="fas fa-calendar-check"></i> Booking Information</h6>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Booking ID:</span>
            <span class="info-value"><strong>#BK-${String(data.booking_id).padStart(4, '0')}</strong></span>
          </div>
          <div class="info-item">
            <span class="info-label">Vehicle:</span>
            <span class="info-value">${data.brand || ''} ${data.model || ''}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Rental Period:</span>
            <span class="info-value">${data.pickup_date || 'N/A'} to ${data.return_date || 'N/A'}</span>
          </div>
        </div>
      </div>
      
      <!-- Payout Breakdown -->
      <div class="mb-4">
        <h6 class="text-success mb-3"><i class="fas fa-money-bill-wave"></i> Payout Breakdown</h6>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Total Booking Amount:</span>
            <span class="info-value"><strong>₱${parseFloat(data.total_amount || data.amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></span>
          </div>
          <div class="info-item">
            <span class="info-label">Platform Fee (10%):</span>
            <span class="info-value text-danger"><strong>-₱${parseFloat(data.platform_fee || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></span>
          </div>
          <div class="info-item">
            <span class="info-label">Net Payout to Owner:</span>
            <span class="info-value text-success"><strong class="fs-5">₱${parseFloat(data.owner_payout || data.net_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="fas fa-times"></i> Close
      </button>
      ${isGcashConfigured && data.escrow_status === 'released_to_owner' ? `
        <button type="button" class="btn btn-success" onclick="bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide(); openPayoutModal(${JSON.stringify({
          booking_id: data.booking_id,
          owner_name: data.owner_name,
          owner_payout: data.owner_payout || data.net_amount,
          gcash_number: gcashNumber,
          gcash_name: gcashName,
          vehicle_name: (data.brand || '') + ' ' + (data.model || '')
        })})">
          <i class="bi bi-cash-coin"></i> Process Payout Now
        </button>
      ` : ''}
    </div>
  `;
  
  document.getElementById('detailsContent').innerHTML = content;
  new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function toggleAdvancedFilters() {
  const advancedFilters = document.getElementById('advancedFilters');
  const btn = document.getElementById('advancedFilterBtn');
  
  if (advancedFilters.style.display === 'none') {
    advancedFilters.style.display = 'block';
    btn.classList.add('active');
    btn.innerHTML = '<i class="bi bi-funnel-fill"></i> Hide Filters';
  } else {
    advancedFilters.style.display = 'none';
    btn.classList.remove('active');
    btn.innerHTML = '<i class="bi bi-funnel"></i> Advanced';
  }
}

function clearFilters() {
  window.location.href = 'payouts.php?status=pending';
}

function exportPayouts() {
  const params = new URLSearchParams(window.location.search);
  window.location.href = `export_payouts.php?${params.toString()}`;
}

// Auto-submit search after typing stops
let searchTimeout;
document.getElementById('searchInput').addEventListener('keyup', function() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    document.getElementById('filterForm').submit();
  }, 500);
});

// Show advanced filters if any advanced filter is active
window.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const hasAdvancedFilters = urlParams.get('date_from') || urlParams.get('date_to') || 
                              urlParams.get('min_amount') || urlParams.get('max_amount') ||
                              urlParams.get('limit');
  
  if (hasAdvancedFilters) {
    document.getElementById('advancedFilters').style.display = 'block';
    document.getElementById('advancedFilterBtn').classList.add('active');
    document.getElementById('advancedFilterBtn').innerHTML = '<i class="bi bi-funnel-fill"></i> Hide Filters';
  }
});

function openOwnerProfile(ownerId) {
  // Open users page with the owner's profile for editing
  window.location.href = `users.php?user_id=${ownerId}&highlight=gcash`;
}

function copyToClipboard(inputId) {
  const input = document.getElementById(inputId);
  input.select();
  document.execCommand('copy');
  
  // Show feedback
  const btn = event.target.closest('button');
  const originalHTML = btn.innerHTML;
  btn.innerHTML = '<i class="bi bi-check"></i>';
  btn.classList.add('btn-success');
  btn.classList.remove('btn-outline-secondary');
  
  setTimeout(() => {
    btn.innerHTML = originalHTML;
    btn.classList.remove('btn-success');
    btn.classList.add('btn-outline-secondary');
  }, 1500);
}
</script>
<script src="include/notifications.js"></script>
</body>
</html>

<?php
function formatCurrency($amount) {
  return '₱' . number_format($amount, 2);
}

function formatNumber($number) {
  return number_format($number);
}

$conn->close();
?>