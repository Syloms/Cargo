<?php
/**
 * Security Deposit Management Admin Panel
 */
session_start();
require_once 'include/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
require_once 'include/admin_profile.php';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$where = ["b.status IN ('approved', 'completed')", "b.security_deposit_amount > 0"];
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $where[] = "b.security_deposit_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($searchQuery)) {
    $where[] = "(u.fullname LIKE ? OR b.id LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

$whereClause = implode(' AND ', $where);

// Count total records
$countSql = "SELECT COUNT(*) as total FROM bookings b 
             LEFT JOIN users u ON b.user_id = u.id 
             WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Fetch deposits
$sql = "SELECT 
            b.id,
            b.user_id,
            b.vehicle_type,
            b.car_id as vehicle_id,
            b.total_amount,
            b.security_deposit_amount,
            b.security_deposit_status,
            b.security_deposit_held_at,
            b.security_deposit_refunded_at,
            b.security_deposit_refund_amount,
            b.security_deposit_deductions,
            b.security_deposit_deduction_reason,
            b.security_deposit_refund_reference,
            b.pickup_date,
            b.return_date,
            b.created_at,
            u.fullname as renter_name,
            u.email as renter_email,
            CASE 
                WHEN b.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model)
                WHEN b.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model)
            END as vehicle_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
        WHERE $whereClause
        ORDER BY b.security_deposit_held_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Deposits - CarGo Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="include/admin-styles.css" rel="stylesheet">
    <link href="include/notifications.css" rel="stylesheet">
    <link href="include/modal-theme-standardized.css" rel="stylesheet">
    <style>
        /* Fix action button spacing */
        .action-btn i {
            margin-right: 6px;
        }
        .action-btn {
            padding: 8px 16px !important;
            white-space: nowrap;
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
                <i class="bi bi-shield-check"></i>
                Security Deposits
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
        <?php
        $statsQuery = "SELECT 
            COUNT(CASE WHEN security_deposit_status = 'held' THEN 1 END) as held_count,
            SUM(CASE WHEN security_deposit_status = 'held' THEN security_deposit_amount ELSE 0 END) as held_amount,
            COUNT(CASE WHEN security_deposit_status = 'refunded' THEN 1 END) as refunded_count,
            SUM(CASE WHEN security_deposit_status = 'refunded' THEN security_deposit_refund_amount ELSE 0 END) as refunded_amount,
            COUNT(CASE WHEN security_deposit_status = 'partial_refund' THEN 1 END) as partial_count,
            COUNT(CASE WHEN security_deposit_status = 'forfeited' THEN 1 END) as forfeited_count
            FROM bookings WHERE status IN ('approved', 'completed') AND security_deposit_amount > 0";
        $statsResult = $conn->query($statsQuery);
        $stats = $statsResult->fetch_assoc();
        ?>

        <div class="stats-grid">
            <!-- Held Deposits -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="bi bi-clock"></i>
                        Pending
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['held_count']); ?></div>
                <div class="stat-label">Held Deposits</div>
                <div class="stat-detail">₱<?php echo number_format($stats['held_amount'] ?? 0, 2); ?></div>
            </div>

            <!-- Refunded -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="bi bi-arrow-up"></i>
                        Complete
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['refunded_count']); ?></div>
                <div class="stat-label">Refunded</div>
                <div class="stat-detail">₱<?php echo number_format($stats['refunded_amount'] ?? 0, 2); ?></div>
            </div>

            <!-- Partial Refund -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                        <i class="bi bi-percent"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="bi bi-dash-circle"></i>
                        Partial
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['partial_count']); ?></div>
                <div class="stat-label">Partial Refunds</div>
                <div class="stat-detail">With Deductions</div>
            </div>

            <!-- Forfeited -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stat-trend down">
                        <i class="bi bi-exclamation-triangle"></i>
                        Lost
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['forfeited_count']); ?></div>
                <div class="stat-label">Forfeited</div>
                <div class="stat-detail">Non-refundable</div>
            </div>
        </div>

            <!-- Table Section -->
        <div class="table-section">
            <div class="section-header">
                <h2 class="section-title">Security Deposits Management</h2>
                <div class="section-actions">
                    <span class="badge bg-info fs-6 me-2"><?php echo number_format($totalRecords); ?> Total</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-bar mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by booking ID or renter name..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="statusFilter" class="form-select">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="held" <?php echo $statusFilter === 'held' ? 'selected' : ''; ?>>Held</option>
                            <option value="refunded" <?php echo $statusFilter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            <option value="partial_refund" <?php echo $statusFilter === 'partial_refund' ? 'selected' : ''; ?>>Partial Refund</option>
                            <option value="forfeited" <?php echo $statusFilter === 'forfeited' ? 'selected' : ''; ?>>Forfeited</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-secondary w-100" onclick="resetFilters()">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Deposits Table -->
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Booking ID</th>
                        <th>Renter</th>
                        <th>Vehicle</th>
                        <th>Deposit Amount</th>
                        <th>Status</th>
                        <th>Held Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php 
                        $num = $offset + 1;
                        while ($deposit = $result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $num++; ?></td>
                                <td><strong>#<?php echo $deposit['id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($deposit['renter_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($deposit['renter_email']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($deposit['vehicle_name'] ?? 'N/A'); ?></strong><br>
                                    <small class="text-muted"><?php echo ucfirst($deposit['vehicle_type']); ?></small>
                                </td>
                                <td><strong>₱<?php echo number_format($deposit['security_deposit_amount'], 2); ?></strong></td>
                                <td>
                                    <?php
                                    $statusBadges = [
                                        'held' => '<span class="status-badge pending">Held</span>',
                                        'refunded' => '<span class="status-badge approved">Refunded</span>',
                                        'partial_refund' => '<span class="status-badge ongoing">Partial</span>',
                                        'forfeited' => '<span class="status-badge rejected">Forfeited</span>',
                                    ];
                                    echo $statusBadges[$deposit['security_deposit_status']] ?? '<span class="status-badge">Unknown</span>';
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($deposit['security_deposit_held_at'] ?? $deposit['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view" onclick="viewDepositDetails(<?php echo $deposit['id']; ?>)" title="View Details">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                        <?php if ($deposit['security_deposit_status'] === 'held'): ?>
                                            <button class="action-btn approve" onclick="processRefund(<?php echo $deposit['id']; ?>)" title="Process Refund">
                                                <i class="bi bi-cash-coin me-1"></i>Process
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h4>No deposits found</h4>
                                <p>No security deposits match your current filters</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <ul class="pagination">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                    <div class="pagination-info">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo number_format($totalRecords); ?> deposits
                    </div>
                </div>
            <?php endif; ?>
        </div>

<!-- Deposit Details Modal -->
<div class="modal fade" id="depositDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="bi bi-shield-check"></i> Security Deposit Details</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="depositDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2">Loading deposit details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Process Refund Modal -->
<div class="modal fade" id="processRefundModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="bi bi-cash-coin"></i> Process Security Deposit Refund</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="refundForm">
                <div class="modal-body">
                    <input type="hidden" id="refund_booking_id" name="booking_id">
                    
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Review the booking and enter any deductions before processing the refund.
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><strong>Deduction Amount (₱)</strong></label>
                        <input type="number" class="form-control" id="deduction_amount" name="deduction_amount" min="0" step="0.01" value="0">
                        <small class="text-muted">Enter amount to deduct from deposit (damages, cleaning, violations, etc.)</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><strong>Deduction Reason</strong></label>
                        <textarea class="form-control" id="deduction_reason" name="deduction_reason" rows="4" placeholder="Enter detailed reason for deduction (if any)"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><strong>Refund Reference Number</strong></label>
                        <input type="text" class="form-control" id="refund_reference" name="refund_reference" placeholder="e.g., GCASH-REF-12345" required>
                        <small class="text-muted">GCash or bank reference number for the refund transaction</small>
                    </div>

                    <div id="refund_calculation" class="alert alert-light border" style="display:none;">
                        <h6 class="mb-3"><i class="bi bi-calculator"></i> Refund Calculation</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Original Deposit:</span>
                            <strong id="calc_original">₱0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Deductions:</span>
                            <strong id="calc_deductions">-₱0.00</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Final Refund Amount:</strong>
                            <strong class="text-success fs-5" id="calc_refund">₱0.00</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Process Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="include/notifications.js"></script>
    <script>
        let currentDepositAmount = 0;

        function viewDepositDetails(bookingId) {
            const modal = new bootstrap.Modal(document.getElementById('depositDetailsModal'));
            modal.show();

            fetch(`api/security_deposit/get_deposit_status.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('depositDetailsContent').innerHTML = renderDepositDetails(data.data);
                    } else {
                        document.getElementById('depositDetailsContent').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('depositDetailsContent').innerHTML = `<div class="alert alert-danger">Error loading details</div>`;
                });
        }

        function renderDepositDetails(data) {
            const statusColors = {
                'held': 'pending',
                'refunded': 'approved',
                'partial_refund': 'ongoing',
                'forfeited': 'rejected'
            };

            let html = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6><i class="bi bi-hash"></i> Booking ID</h6>
                        <p class="mb-0"><strong>#${data.booking_id}</strong></p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-info-circle"></i> Status</h6>
                        <p class="mb-0"><span class="status-badge ${statusColors[data.deposit_status] || ''}">${data.deposit_status.replace('_', ' ').toUpperCase()}</span></p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-cash-stack"></i> Deposit Amount</h6>
                        <p class="mb-0 fs-5"><strong>₱${parseFloat(data.security_deposit).toFixed(2)}</strong></p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-receipt"></i> Total Rental</h6>
                        <p class="mb-0">₱${parseFloat(data.total_amount).toFixed(2)}</p>
                    </div>
            `;

            // Deductions breakdown — always show if any damage/other deductions exist
            if (data.deductions && data.deductions.length > 0) {
                html += `<div class="col-12"><hr></div>
                    <div class="col-12">
                        <h6><i class="bi bi-dash-circle text-danger"></i> Deductions Breakdown</h6>
                        <table class="table table-sm table-bordered mt-2">
                            <thead class="table-light">
                                <tr><th>Type</th><th>Description</th><th class="text-end">Amount</th></tr>
                            </thead><tbody>`;
                data.deductions.forEach(d => {
                    const badge = d.type === 'damage'
                        ? '<span class="badge bg-danger">Damage</span>'
                        : `<span class="badge bg-secondary">${d.type}</span>`;
                    html += `<tr>
                        <td>${badge}</td>
                        <td><small>${d.description || '—'}</small></td>
                        <td class="text-end text-danger fw-bold">₱${parseFloat(d.amount).toFixed(2)}</td>
                    </tr>`;
                });
                html += `</tbody><tfoot class="table-light">
                    <tr><td colspan="2"><strong>Total Deducted</strong></td>
                        <td class="text-end text-danger fw-bold">₱${parseFloat(data.total_deductions).toFixed(2)}</td>
                    </tr></tfoot></table>
                    </div>`;
            }

            if (data.deposit_status !== 'held') {
                html += `
                    <div class="col-md-6">
                        <h6><i class="bi bi-check-circle"></i> Refund Amount</h6>
                        <p class="mb-0 text-success fs-5"><strong>₱${parseFloat(data.refund_amount).toFixed(2)}</strong></p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-calendar-check"></i> Refunded At</h6>
                        <p class="mb-0">${data.refunded_at ? new Date(data.refunded_at).toLocaleString() : 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-key"></i> Reference Number</h6>
                        <p class="mb-0"><code>${data.refund_reference || 'N/A'}</code></p>
                    </div>
                `;
            } else {
                html += `
                    <div class="col-md-6">
                        <h6><i class="bi bi-wallet2"></i> Remaining Balance</h6>
                        <p class="mb-0 text-success fs-5"><strong>₱${parseFloat(data.remaining_deposit).toFixed(2)}</strong></p>
                    </div>
                `;
            }

            html += `</div>`;
            return html;
        }

        function processRefund(bookingId) {
            // Fetch deposit amount first
            fetch(`api/security_deposit/get_deposit_status.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentDepositAmount = parseFloat(data.data.security_deposit);
                        document.getElementById('refund_booking_id').value = bookingId;
                        // Pre-fill deductions from approved damage reports
                        const existingDeductions = parseFloat(data.data.total_deductions) || 0;
                        document.getElementById('deduction_amount').value = existingDeductions.toFixed(2);
                        // Pre-fill reason from damage deduction descriptions
                        const deductionDescs = (data.data.deductions || [])
                            .map(d => d.description).filter(Boolean).join('; ');
                        document.getElementById('deduction_reason').value = deductionDescs;
                        document.getElementById('refund_reference').value = '';
                        updateRefundCalculation();
                        
                        const modal = new bootstrap.Modal(document.getElementById('processRefundModal'));
                        modal.show();
                    }
                });
        }

        function updateRefundCalculation() {
            const deduction = parseFloat(document.getElementById('deduction_amount').value) || 0;
            const refundAmount = currentDepositAmount - deduction;

            document.getElementById('calc_original').textContent = `₱${currentDepositAmount.toFixed(2)}`;
            document.getElementById('calc_deductions').textContent = `-₱${deduction.toFixed(2)}`;
            document.getElementById('calc_refund').textContent = `₱${Math.max(0, refundAmount).toFixed(2)}`;
            document.getElementById('refund_calculation').style.display = 'block';
        }

        document.getElementById('deduction_amount').addEventListener('input', updateRefundCalculation);

        document.getElementById('refundForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

            fetch('api/security_deposit/process_refund.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Security deposit refund processed successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Process Refund';
                }
            })
            .catch(error => {
                alert('Error processing refund');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Process Refund';
            });
        });

        function resetFilters() {
            window.location.href = 'security_deposits.php';
        }

        // Live search
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 500);
        });

        document.getElementById('statusFilter').addEventListener('change', applyFilters);

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            window.location.href = `security_deposits.php?search=${encodeURIComponent(search)}&status=${status}`;
        }
    </script>
</body>
</html>
