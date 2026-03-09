<?php
/**
 * ============================================================================
 * REFUNDS MANAGEMENT - CarGo Admin Panel
 * Manage refund requests from renters
 * ============================================================================
 */

session_start();
require_once 'include/db.php';
require_once 'include/admin_profile.php';

// Auth check
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// ============================================================================
// HELPER FUNCTION: Execute query with error handling
// ============================================================================
function executeQuery($conn, $query, $errorContext = '') {
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed ($errorContext): " . mysqli_error($conn) . "<br><br>Query: " . $query);
    }
    return $result;
}

// ============================================================================
// STATISTICS QUERIES
// ============================================================================

// Pending refunds (urgent)
$pendingQuery = "SELECT COUNT(*) as count FROM refunds WHERE status = 'pending'";
$pendingResult = executeQuery($conn, $pendingQuery, 'Pending Count');
$pendingCount = mysqli_fetch_assoc($pendingResult)['count'];

// Approved (awaiting completion)
$approvedQuery = "SELECT COUNT(*) as count FROM refunds WHERE status = 'approved'";
$approvedResult = executeQuery($conn, $approvedQuery, 'Approved Count');
$approvedCount = mysqli_fetch_assoc($approvedResult)['count'];

// Processing
$processingQuery = "SELECT COUNT(*) as count FROM refunds WHERE status = 'processing'";
$processingResult = executeQuery($conn, $processingQuery, 'Processing Count');
$processingCount = mysqli_fetch_assoc($processingResult)['count'];

// Completed (total refunded amount)
$completedQuery = "
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(refund_amount), 0) as total_refunded
    FROM refunds 
    WHERE status = 'completed'
";
$completedResult = executeQuery($conn, $completedQuery, 'Completed Stats');
$completedData = mysqli_fetch_assoc($completedResult);
$completedCount = $completedData['count'];
$totalRefunded = $completedData['total_refunded'];

// ============================================================================
// FILTERS & PAGINATION
// ============================================================================

// Optional admin debug (does not expose credentials)
$debugMode = (isset($_GET['debug']) && $_GET['debug'] === '1');
$debugInfo = [];
if ($debugMode) {
    // Show which DB we are connected to and if refunds table has data
    $dbNameRes = @mysqli_query($conn, 'SELECT DATABASE() AS db');
    $dbNameRow = $dbNameRes ? mysqli_fetch_assoc($dbNameRes) : null;

    $debugInfo['connected_database'] = $dbNameRow['db'] ?? null;
    $debugInfo['php_host'] = $_SERVER['HTTP_HOST'] ?? null;

    // Check table existence
    $tableRes = @mysqli_query($conn, "SHOW TABLES LIKE 'refunds'");
    $debugInfo['refunds_table_exists'] = ($tableRes && mysqli_num_rows($tableRes) > 0);

    if ($debugInfo['refunds_table_exists']) {
        $cntRes = @mysqli_query($conn, 'SELECT COUNT(*) AS cnt FROM refunds');
        $cntRow = $cntRes ? mysqli_fetch_assoc($cntRes) : null;
        $debugInfo['refunds_row_count'] = (int)($cntRow['cnt'] ?? 0);

        $sampleRes = @mysqli_query($conn, "SELECT id, refund_id, status, booking_id, user_id, created_at FROM refunds ORDER BY id DESC LIMIT 5");
        $samples = [];
        if ($sampleRes) {
            while ($s = mysqli_fetch_assoc($sampleRes)) {
                $samples[] = $s;
            }
        }
        $debugInfo['refunds_latest_5'] = $samples;
    }
}

$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

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
        u_renter.email LIKE '%$searchEsc%' OR
        r.account_number LIKE '%$searchEsc%'
    )";
}

// ============================================================================
// MAIN QUERY - Get Refunds (FIXED)
// ============================================================================

$query = "
    SELECT 
        r.*,
        
        -- Renter info
        u_renter.fullname AS renter_name,
        u_renter.email AS renter_email,
        u_renter.phone AS renter_phone,
        
        -- Owner info (get from booking)
        u_owner.fullname AS owner_name,
        u_owner.email AS owner_email,
        
        -- Booking info
        b.id AS booking_id,
        b.owner_id,
        b.status AS booking_status,
        b.pickup_date,
        b.return_date,
        b.total_amount AS booking_amount,
        b.vehicle_type,
        
        -- Car info (supports both cars and motorcycles)
        COALESCE(c.brand, m.brand) AS car_brand,
        COALESCE(c.model, m.model) AS car_model,
        COALESCE(c.car_year, m.motorcycle_year) AS car_year,
        COALESCE(c.image, m.image) AS car_image,
        CONCAT(
            COALESCE(c.brand, m.brand), ' ',
            COALESCE(c.model, m.model), ' ',
            COALESCE(c.car_year, m.motorcycle_year)
        ) AS car_full_name,
        
        -- Payment info
        p.payment_method AS original_payment_method,
        p.payment_reference AS original_payment_reference,
        
        -- Admin who processed
        admin.fullname AS processed_by_name,
        
        -- Days pending
        DATEDIFF(NOW(), r.created_at) AS days_pending
        
    FROM refunds r
    LEFT JOIN users u_renter ON r.user_id = u_renter.id
    LEFT JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN users u_owner ON b.owner_id = u_owner.id
    LEFT JOIN payments p ON r.payment_id = p.id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    LEFT JOIN admin ON r.processed_by = admin.id
    $where
    ORDER BY 
        CASE 
            WHEN r.status = 'pending' THEN 1
            WHEN r.status = 'approved' THEN 2
            WHEN r.status = 'processing' THEN 3
            ELSE 4
        END,
        r.created_at DESC
    LIMIT $limit OFFSET $offset
";

$result = executeQuery($conn, $query, 'Main Refunds Query');

// Count total for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM refunds r
    LEFT JOIN users u_renter ON r.user_id = u_renter.id
    $where
";
$countResult = executeQuery($conn, $countQuery, 'Count Query');
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = max(1, ceil($totalRows / $limit));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refunds Management - CarGo Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="include/admin-styles.css" rel="stylesheet">
    <link href="include/notifications.css" rel="stylesheet">
    <link href="include/modal-theme-standardized.css" rel="stylesheet">
    <style>
    /* Force contact-modal design overrides - Enhanced with icons & larger text */
    .modal-dialog {
      max-width: 700px !important;
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
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
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
      transform: scale(1.05) !important;
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
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <?php include 'include/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="bi bi-arrow-counterclockwise"></i>
                Refunds Management
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

        <?php if ($debugMode): ?>
            <div class="alert alert-warning" style="margin: 16px 0;">
                <strong>Refunds Debug</strong>
                <pre style="white-space: pre-wrap; margin: 8px 0 0;"><?= htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') ?></pre>
                <div style="font-size: 12px; color:#666;">Tip: remove <code>?debug=1</code> from the URL when done.</div>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <!-- Pending -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="bi bi-exclamation-circle"></i>
                        Urgent
                    </div>
                </div>
                <div class="stat-value"><?= $pendingCount ?></div>
                <div class="stat-label">Pending Review</div>
            </div>

            <!-- Approved -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="bi bi-arrow-up"></i>
                        Active
                    </div>
                </div>
                <div class="stat-value"><?= $approvedCount ?></div>
                <div class="stat-label">Approved Refunds</div>
            </div>

            <!-- Processing -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="bi bi-sync"></i>
                        In Progress
                    </div>
                </div>
                <div class="stat-value"><?= $processingCount ?></div>
                <div class="stat-label">Processing</div>
            </div>

            <!-- Completed -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                        <i class="bi bi-check-all"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="bi bi-arrow-up"></i>
                        +<?= $completedCount ?>
                    </div>
                </div>
                <div class="stat-value">₱<?= number_format($totalRefunded, 2) ?></div>
                <div class="stat-label">Total Refunded</div>
                <div class="stat-detail"><?= $completedCount ?> completed</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-row">
                <div class="search-box">
                    <input type="text" name="search" id="searchInput" placeholder="Search by Refund ID, renter, or account..." value="<?= htmlspecialchars($search) ?>">
                    <i class="bi bi-search"></i>
                </div>

                <select name="status" class="filter-dropdown" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>

                <button type="button" class="export-btn" onclick="exportRefunds()">
                    <i class="bi bi-download"></i>
                    Export
                </button>
            </form>
        </div>

        <!-- Refunds Table -->
        <div class="table-section">
            <div class="section-header">
                <h2 class="section-title">Refund Requests</h2>
                <div class="table-controls">
                    <a href="?status=all" class="table-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">
                        All (<?= $totalRows ?>)
                    </a>
                    <a href="?status=pending" class="table-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                        Pending (<?= $pendingCount ?>)
                    </a>
                    <a href="?status=approved" class="table-btn <?= $statusFilter === 'approved' ? 'active' : '' ?>">
                        Approved (<?= $approvedCount ?>)
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Refund ID</th>
                            <th>Renter</th>
                            <th>Booking</th>
                            <th>Car Details</th>
                            <th>Refund Amount</th>
                            <th>Method</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Days Pending</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <i class="bi bi-inbox" style="font-size: 48px; color: #ddd;"></i>
                                <p style="margin-top: 16px; color: #999;">No refund requests found</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): 
                            $statusClass = [
                                'pending' => 'pending',
                                'approved' => 'verified',
                                'processing' => 'pending',
                                'completed' => 'approved',
                                'rejected' => 'rejected'
                            ][$row['status']] ?? 'pending';
                            
                            $finalAmount = $row['refund_amount'];
                            $urgencyClass = $row['days_pending'] > 5 ? 'text-danger fw-bold' : '';
                        ?>
                        <tr>
                            <!-- Refund ID -->
                            <td>
                                <strong>#RF-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></strong><br>
                                <small style="color:#999;"><?= date('M d, Y', strtotime($row['created_at'])) ?></small>
                            </td>

                            <!-- Renter -->
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-small">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['renter_name']) ?>&background=1a1a1a&color=fff">
                                    </div>
                                    <div class="user-info">
                                        <span class="user-name"><?= htmlspecialchars($row['renter_name']) ?></span>
                                        <span class="user-email"><?= htmlspecialchars($row['renter_email']) ?></span>
                                    </div>
                                </div>
                            </td>

                            <!-- Booking -->
                            <td>
                                <strong>#BK-<?= str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT) ?></strong><br>
                                <small style="color:#999;">
                                    <?= date('M d', strtotime($row['pickup_date'])) ?> - 
                                    <?= date('M d', strtotime($row['return_date'])) ?>
                                </small>
                            </td>

                            <!-- Car -->
                            <td>
                                <strong><?= htmlspecialchars($row['car_full_name']) ?></strong><br>
                                <small style="color:#999;">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($row['owner_name']) ?>
                                </small>
                            </td>

                            <!-- Amount -->
                            <td>
                                <strong style="font-size: 15px; color: #dc3545;">
                                    ₱<?= number_format($finalAmount, 2) ?>
                                </strong>
                            </td>

                            <!-- Method -->
                            <td>
                                <span class="payment-badge <?= $row['refund_method'] === 'gcash' ? 'paid' : 'unpaid' ?>">
                                    <?= strtoupper($row['refund_method']) ?>
                                </span>
                                <br>
                                <small style="color:#666; font-family: monospace;">
                                    <?= htmlspecialchars($row['account_number']) ?>
                                </small>
                            </td>

                            <!-- Reason -->
                            <td>
                                <small>
                                    <?php
                                    $reasons = [
                                        'cancelled_by_user' => '🚫 Cancelled by User',
                                        'cancelled_by_owner' => '👤 Cancelled by Owner',
                                        'car_unavailable' => '🚗 Car Unavailable',
                                        'double_booking' => '📅 Double Booking',
                                        'payment_error' => '💳 Payment Error',
                                        'other' => '📝 Other'
                                    ];
                                    echo $reasons[$row['refund_reason']] ?? ucfirst(str_replace('_', ' ', $row['refund_reason']));
                                    ?>
                                </small>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>

                            <!-- Days Pending -->
                            <td>
                                <span class="<?= $urgencyClass ?>">
                                    <?= $row['days_pending'] ?> day<?= $row['days_pending'] > 1 ? 's' : '' ?>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view" onclick="viewRefundDetails(<?= $row['id'] ?>)" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <?php if ($row['status'] === 'pending'): ?>
                                    <button class="action-btn approve" onclick="approveRefund(<?= $row['id'] ?>)" title="Approve">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button class="action-btn reject" onclick="rejectRefund(<?= $row['id'] ?>)" title="Reject">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if (in_array($row['status'], ['approved', 'processing'])): ?>
                                    <button class="action-btn edit" onclick="completeRefund(<?= $row['id'] ?>)" title="Mark as Completed">
                                        <i class="bi bi-check-all"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-section">
                <div class="pagination-info">
                    Showing <strong><?= $offset + 1 ?></strong> - 
                    <strong><?= min($offset + $limit, $totalRows) ?></strong> 
                    of <strong><?= $totalRows ?></strong> refunds
                </div>
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>&search=<?= urlencode($search) ?>" class="page-btn">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <?php endif; ?>

                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                    <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&search=<?= urlencode($search) ?>" 
                       class="page-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>&search=<?= urlencode($search) ?>" class="page-btn">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Refund Details Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" id="refundModalContent">
            <!-- Loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Complete Refund Modal -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Complete Refund Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="complete_refund_id">
                
                <div class="mb-3">
                    <label class="form-label">Refund Reference Number *</label>
                    <input type="text" class="form-control" id="refund_reference" 
                           placeholder="Enter GCash/Bank reference number" required>
                    <small class="text-muted">Transaction ID from the refund transfer</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Admin Notes</label>
                    <textarea class="form-control" id="complete_notes" rows="3" 
                              placeholder="Optional notes about the refund transfer..."></textarea>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Marking as completed will update the renter's payment history and close this refund request.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitComplete()">
                    <i class="bi bi-check-circle"></i> Mark as Completed
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// VIEW REFUND DETAILS
function viewRefundDetails(refundId) {
    console.log("VIEW REFUND:", refundId);

    fetch(`api/refund/get_refund_details.php?id=${refundId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error("HTTP " + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log("REFUND DATA:", data);

            if (!data.success) {
                alert(data.message || "Failed to load refund details");
                return;
            }

            const refund = data.refund;

            document.getElementById('refundModalContent').innerHTML = `
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Refund Details - #RF-${String(refund.id).padStart(4, '0')}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Renter</h6>
                            <p><strong>${refund.renter_name}</strong></p>
                            <p>${refund.renter_email}</p>
                            <p>${refund.renter_phone || 'N/A'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Refund Account</h6>
                            <p><strong>${refund.refund_method.toUpperCase()}</strong></p>
                            <p>${refund.account_number}</p>
                            <p>${refund.account_name}</p>
                        </div>
                    </div>

                    <hr>

                    <h6>Booking</h6>
                    <p><strong>Car:</strong> ${refund.car_full_name}</p>
                    <p><strong>Owner:</strong> ${refund.owner_name}</p>
                    <p><strong>Rental:</strong> ${refund.pickup_date} → ${refund.return_date}</p>

                    <hr>

                    <h6>Refund</h6>
                    <p><strong>Amount:</strong> ₱${parseFloat(refund.refund_amount).toLocaleString()}</p>
                    <p><strong>Reason:</strong> ${refund.refund_reason.replace(/_/g, ' ')}</p>
                    ${refund.reason_details ? `<p><strong>Details:</strong> ${refund.reason_details}</p>` : ''}

                    ${refund.rejection_reason ? `
                        <div class="alert alert-danger mt-2">
                            <strong>Rejected:</strong> ${refund.rejection_reason}
                        </div>
                    ` : ''}

                    ${refund.completion_reference ? `
                        <div class="alert alert-success mt-2">
                            <strong>Completion Ref:</strong> ${refund.completion_reference}
                        </div>
                    ` : ''}
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            `;

            new bootstrap.Modal(
                document.getElementById('refundModal')
            ).show();
        })
        .catch(err => {
            console.error("VIEW ERROR:", err);
            alert("Error loading refund details");
        });
}


// APPROVE REFUND
function approveRefund(refundId) {
    const notes = prompt('Admin notes (optional):', '');

    if (!confirm('Approve this refund request?')) {
        return;
    }

    const formData = new FormData();
    formData.append('refund_id', refundId);
    formData.append('action', 'approve');
    formData.append('admin_notes', notes);

    fetch('api/refund/process_refund.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Refund approved successfully!');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to approve refund'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error occurred');
    });
}

// REJECT REFUND
function rejectRefund(refundId) {
    const reason = prompt('Reason for rejection:', '');
    
    if (!reason || reason.trim() === '') {
        alert('Rejection reason is required');
        return;
    }

    if (!confirm(`Are you sure you want to REJECT this refund request?\n\nReason: ${reason}`)) {
        return;
    }

    const formData = new FormData();
    formData.append('refund_id', refundId);
    formData.append('action', 'reject');
    formData.append('rejection_reason', reason);

    fetch('api/refund/process_refund.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('❌ Refund rejected');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to reject refund'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error occurred');
    });
}

// COMPLETE REFUND
function completeRefund(refundId) {
    document.getElementById('complete_refund_id').value = refundId;
    document.getElementById('refund_reference').value = '';
    document.getElementById('complete_notes').value = '';
    
    new bootstrap.Modal(document.getElementById('completeModal')).show();
}

// SUBMIT COMPLETE
function submitComplete() {
    const refundId = document.getElementById('complete_refund_id').value;
    const reference = document.getElementById('refund_reference').value.trim();
    const notes = document.getElementById('complete_notes').value.trim();

    if (!reference) {
        alert('Refund reference number is required');
        document.getElementById('refund_reference').focus();
        return;
    }

    if (!confirm(`Mark this refund as COMPLETED?\n\nReference: ${reference}`)) {
        return;
    }

    const formData = new FormData();
    formData.append('refund_id', refundId);
    formData.append('action', 'complete');
    formData.append('refund_reference', reference);
    formData.append('admin_notes', notes);

    fetch('api/refund/process_refund.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Refund marked as completed!');
            bootstrap.Modal.getInstance(document.getElementById('completeModal')).hide();
            location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to complete refund'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error occurred');
    });
}

// EXPORT REFUNDS
function exportRefunds() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'api/refund/export_refunds.php?' + params.toString();
}

// LIVE SEARCH
let searchTimeout;
document.getElementById('searchInput').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});
</script>
<script src="include/notifications.js"></script>
</body>
</html>