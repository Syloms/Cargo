    <?php
    session_start();
    // Configuration loaded via header.php

    require_once 'include/db.php';
    require_once 'include/admin_profile.php';

    // Get statistics
    $stats = [];

    // Total policies
    $result = $conn->query("SELECT COUNT(*) as total FROM insurance_policies");
    $stats['total_policies'] = $result->fetch_assoc()['total'];

    // Active policies
    $result = $conn->query("SELECT COUNT(*) as total FROM insurance_policies WHERE status = 'active'");
    $stats['active_policies'] = $result->fetch_assoc()['total'];

    // Total claims
    $result = $conn->query("SELECT COUNT(*) as total FROM insurance_claims");
    $stats['total_claims'] = $result->fetch_assoc()['total'];

    // Pending claims
    $result = $conn->query("SELECT COUNT(*) as total FROM insurance_claims WHERE status IN ('submitted', 'under_review')");
    $stats['pending_claims'] = $result->fetch_assoc()['total'];

    // Total premium collected
    $result = $conn->query("SELECT SUM(premium_amount) as total FROM insurance_policies WHERE status IN ('active', 'claimed', 'expired')");
    $stats['total_premium'] = $result->fetch_assoc()['total'] ?? 0;

    // Total claims amount
    $result = $conn->query("SELECT SUM(approved_amount) as total FROM insurance_claims WHERE status = 'approved'");
    $stats['total_claims_amount'] = $result->fetch_assoc()['total'] ?? 0;

    // Pagination
    $limit = 10;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Management - CarGo Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="include/admin-styles.css" rel="stylesheet">
    <link href="include/notifications.css" rel="stylesheet">
    <link href="include/modal-theme-standardized.css" rel="stylesheet">
    
    <style>
    /* Additional inline styles for Insurance page */
    .fade-in {
    animation: fadeIn 0.5s ease-in;
    }

    @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
    }

    /* Tab styles */
    .tabs-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .tabs-header {
    display: flex;
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    padding: 0;
    }

    .tab-button {
    flex: 1;
    padding: 16px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #666;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
    }

    .tab-button:hover {
    background: #e9ecef;
    color: #1a1a1a;
    }

    .tab-button.active {
    color: #1a1a1a;
    background: white;
    border-bottom-color: #1a1a1a;
    }

    .tab-content {
    display: none;
    padding: 24px;
    }

    .tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
    }

    /* =================================================================
    MODAL STYLING (Bootstrap 5 Enhanced)
    ================================================================= */

    /* Modal customizations */
    /* Force contact-modal design overrides */
    .modal-header {
    background: #ffffff !important;
    color: #111827 !important;
    padding: 40px 40px 32px 40px !important;
    border-bottom: none !important;
    }

    .modal-dialog {
    max-width: 1000px !important;
    }

    .modal-dialog-scrollable .modal-content {
    max-height: 90vh !important;
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
    border-left: none !important;
    border-right: 1px solid #f0f0f0 !important;
    border-bottom: 1px solid #f0f0f0 !important;
    transition: none !important;
    }

    .info-item:nth-child(2n) {
    border-right: none !important;
    }

    .info-item:hover {
    transform: none !important;
    box-shadow: none !important;
    }

    .info-label {
    font-size: 15px !important;
    color: #9ca3af !important;
    text-transform: none !important;
    font-weight: 500 !important;
    margin-bottom: 6px !important;
    letter-spacing: 0.01em !important;
    }

    .info-value {
    font-size: 18px !important;
    color: #111827 !important;
    font-weight: 600 !important;
    letter-spacing: -0.2px !important;
    }

    .section-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 24px 0 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e9ecef;
    }

    /* Pagination */
    .pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    }

    .pagination-info {
    font-size: 14px;
    color: #666;
    }

    .pagination-controls {
    display: flex;
    gap: 8px;
    }

    .pagination-btn {
    padding: 8px 16px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    }

    .pagination-btn:hover:not(:disabled) {
    background: #1a1a1a;
    color: white;
    border-color: #1a1a1a;
    }

    .pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    }

    .pagination-btn.active {
    background: #1a1a1a;
    color: white;
    border-color: #1a1a1a;
    }

    /* Export button */
    .export-section {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    }

    .btn-export {
    padding: 10px 20px;
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    }

    .btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
    }

    .action-card {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
    }

    .action-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
    }

    .action-card h6 {
    margin-bottom: 15px;
    font-weight: 600;
    color: #1a1a1a;
    }

    .action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    }

    /* Force contact-modal button design */
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
    background: #f3f4f6 !important;
    color: #374151 !important;
    }

    .modal-action-btn:hover,
    button.modal-action-btn:hover {
    background: #e5e7eb !important;
    color: #111827 !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    }

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
    }

    tr[style*="cursor: pointer"]:hover td {
    background-color: #f8f9fa;
    }
    </style>
    </head>
    <body>

    <div class="dashboard-wrapper">
    <?php include 'include/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar fade-in">
        <h1 class="page-title">
            <i class="bi bi-shield-check"></i>
            Insurance Management
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
            <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="stat-trend">
                <i class="bi bi-arrow-up"></i> +12%
            </div>
            </div>
            <div class="stat-value"><?php echo number_format($stats['total_policies']); ?></div>
            <div class="stat-label">Total Policies</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-trend">
                <i class="bi bi-arrow-up"></i> +8%
            </div>
            </div>
            <div class="stat-value"><?php echo number_format($stats['active_policies']); ?></div>
            <div class="stat-label">Active Policies</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="stat-trend down">
                <i class="bi bi-arrow-down"></i> -3%
            </div>
            </div>
            <div class="stat-value"><?php echo number_format($stats['total_claims']); ?></div>
            <div class="stat-label">Total Claims</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-trend">
                <i class="bi bi-arrow-up"></i> +5%
            </div>
            </div>
            <div class="stat-value"><?php echo number_format($stats['pending_claims']); ?></div>
            <div class="stat-label">Pending Claims</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <span class="currency-symbol">₱</span>
            </div>
            <div class="stat-trend">
                <i class="bi bi-arrow-up"></i> +15%
            </div>
            </div>
            <div class="stat-value">₱<?php echo number_format($stats['total_premium'], 0); ?></div>
            <div class="stat-label">Premium Collected</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ffa751 0%, #ffe259 100%); color: white;">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-trend down">
                <i class="bi bi-arrow-down"></i> -2%
            </div>
            </div>
            <div class="stat-value">₱<?php echo number_format($stats['total_claims_amount'], 0); ?></div>
            <div class="stat-label">Claims Approved</div>
        </div>
        </div>

        <!-- Filter & Tabs Section -->
        <div class="table-section fade-in" style="animation-delay: 0.2s;">
        <div class="tabs-container">
            <div class="tabs-header">
            <button class="tab-button active" data-tab="policies">
                <i class="bi bi-file-text"></i> Insurance Policies
            </button>
            <button class="tab-button" data-tab="claims">
                <i class="bi bi-file-medical"></i> Insurance Claims
            </button>
            <button class="tab-button" data-tab="providers">
                <i class="bi bi-building"></i> Providers
            </button>
            </div>

            <!-- Policies Tab -->
            <div class="tab-content active" id="policies-tab">
            <div class="export-section">
                <button class="btn-export" onclick="exportPolicies()">
                <i class="bi bi-download"></i> Export Policies
                </button>
            </div>
            
            <div class="filter-section">
                <div class="filter-row">
                <div class="search-box">
                    <input type="text" id="policy-search" placeholder="🔍 Search by policy number, renter name...">
                    <i class="bi bi-search"></i>
                </div>
                <select class="filter-dropdown" id="policy-status-filter">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="claimed">Claimed</option>
                </select>
                <button class="export-btn" onclick="loadPolicies()">
                    <i class="bi bi-search"></i> Search
                </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="policies-table">
                <thead>
                    <tr>
                    <th><i class="bi bi-hash me-1"></i>Policy #</th>
                    <th><i class="bi bi-person me-1"></i>Renter</th>
                    <th><i class="bi bi-car-front me-1"></i>Vehicle</th>
                    <th><i class="bi bi-shield me-1"></i>Coverage</th>
                    <th><span class="currency-symbol me-1">₱</span>Premium</th>
                    <th><i class="bi bi-calendar me-1"></i>Period</th>
                    <th><i class="bi bi-info-circle me-1"></i>Status</th>
                    <th><i class="bi bi-gear me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody id="policies-tbody">
                    <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                    </tr>
                </tbody>
                </table>
            </div>
            
            <div id="policies-pagination" class="pagination-container"></div>
            </div>

            <!-- Claims Tab -->
            <div class="tab-content" id="claims-tab">
            <div class="export-section">
                <button class="btn-export" onclick="exportClaims()">
                <i class="bi bi-download"></i> Export Claims
                </button>
            </div>
            
            <div class="filter-section">
                <div class="filter-row">
                <select class="filter-dropdown" id="claim-status-filter">
                    <option value="all">All Status</option>
                    <option value="submitted">Submitted</option>
                    <option value="under_review">Under Review</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="paid">Paid</option>
                </select>
                <select class="filter-dropdown" id="claim-priority-filter">
                    <option value="all">All Priority</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
                <button class="export-btn" onclick="loadClaims()">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="claims-table">
                <thead>
                    <tr>
                    <th>Claim #</th>
                    <th>Policy #</th>
                    <th>Claimant</th>
                    <th>Type</th>
                    <th>Claimed</th>
                    <th>Approved</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Action</th>
                    </tr>
                </thead>
                <tbody id="claims-tbody">
                    <tr>
                    <td colspan="9" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                    </tr>
                </tbody>
                </table>
            </div>
            
            <div id="claims-pagination" class="pagination-container"></div>
            </div>

            <!-- Providers Tab -->
            <div class="tab-content" id="providers-tab">
            <div class="export-section">
                <button class="btn-export" onclick="exportProviders()">
                <i class="bi bi-download"></i> Export Providers
                </button>
            </div>
            
            <div class="table-responsive">
                <table>
                <thead>
                    <tr>
                    <th>Provider Name</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                    </tr>
                </thead>
                <tbody id="providers-tbody">
                    <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                    </tr>
                </tbody>
                </table>
            </div>
            
            <div id="providers-pagination" class="pagination-container"></div>
            </div>
        </div>
        </div>
    </main>
    </div>

    <!-- Policy Details Modal (Quick Preview) -->
    <div class="modal fade" id="policy-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-shield-check"></i> Policy Quick View</h3>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="policy-modal-body">
            <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
        </div>
    </div>
    </div>

    <!-- Policy Full Details Modal -->
    <div class="modal fade" id="policy-full-details-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-file-earmark-text-fill"></i> Complete Policy Details</h3>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="policy-full-details-body">
            <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
        </div>
    </div>
    </div>

    <!-- Claim Details Modal -->
    <div class="modal fade" id="claim-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-file-medical"></i> Claim Details</h3>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="claim-modal-body">
            <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
        <div class="modal-footer" id="claim-modal-footer"></div>
        </div>
    </div>
    </div>

    <!-- Provider Edit Modal -->
    <div class="modal fade" id="provider-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-building"></i> Edit Provider</h3>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="provider-modal-body">
            <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
        </div>
    </div>
    </div>

    <!-- Claim Actions Modal -->
    <div class="modal fade" id="claim-actions-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-cogs"></i> Manage Insurance Claim</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="claim-actions-modal-body">
            <!-- Loaded via JavaScript -->
        </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="include/notifications.js"></script>
    <script>
    const API_BASE = 'api/insurance';

    // Pagination state
    let policiesPage = 1;
    let claimsPage = 1;
    let providersPage = 1;

    // Tab switching with animation
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.tab;
            
            // Update buttons
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            button.classList.add('active');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Load data
            if (tabName === 'policies') loadPolicies();
            else if (tabName === 'claims') loadClaims();
            else if (tabName === 'providers') loadProviders();
        });
    });

    // Export functions
    function exportPolicies() {
        const status = document.getElementById('policy-status-filter').value;
        const search = document.getElementById('policy-search').value;
        window.location.href = `${API_BASE}/admin/export_policies.php?status=${status}&search=${encodeURIComponent(search)}`;
    }

    function exportClaims() {
        const status = document.getElementById('claim-status-filter').value;
        const priority = document.getElementById('claim-priority-filter').value;
        window.location.href = `${API_BASE}/admin/export_claims.php?status=${status}&priority=${priority}`;
    }

    function exportProviders() {
        window.location.href = `${API_BASE}/admin/export_providers.php`;
    }

    // Modal functions using Bootstrap
    function closeModal(modalId) {
        const modalElement = document.getElementById(modalId);
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }

    function openModal(modalId) {
        const modalElement = document.getElementById(modalId);
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }

    // Render pagination
    function renderPagination(containerId, currentPage, totalPages, loadFunction) {
        const container = document.getElementById(containerId);
        if (!container || totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        const startRecord = ((currentPage - 1) * 20) + 1;
        const endRecord = Math.min(currentPage * 20, totalPages * 20);
        
        let html = `
            <div class="pagination-info">
                Showing ${startRecord} - ${endRecord} of ${totalPages * 20} records
            </div>
            <div class="pagination-controls">
                <button class="pagination-btn" onclick="${loadFunction}(1)" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="bi bi-chevron-double-left"></i>
                </button>
                <button class="pagination-btn" onclick="${loadFunction}(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="bi bi-chevron-left"></i>
                </button>
        `;
        
        // Page numbers
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="${loadFunction}(${i})">
                    ${i}
                </button>
            `;
        }
        
        html += `
                <button class="pagination-btn" onclick="${loadFunction}(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                    <i class="bi bi-chevron-right"></i>
                </button>
                <button class="pagination-btn" onclick="${loadFunction}(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>
                    <i class="bi bi-chevron-double-right"></i>
                </button>
            </div>
        `;
        
        container.innerHTML = html;
    }

    // Load policies
    function loadPolicies(page = 1) {
        policiesPage = page;
        const status = document.getElementById('policy-status-filter').value;
        const search = document.getElementById('policy-search').value;
        
        $.ajax({
            url: `${API_BASE}/admin/get_all_policies.php`,
            method: 'GET',
            data: { status, search, page: policiesPage },
            dataType: 'json',
            success: function(response) {
                const tbody = document.getElementById('policies-tbody');
                
                if (response.success && response.data && response.data.length > 0) {
                    // Render pagination
                    if (response.pagination) {
                        renderPagination('policies-pagination', response.pagination.page, response.pagination.total_pages, 'loadPolicies');
                    }
                    
                    tbody.innerHTML = response.data.map(policy => `
                    <tr style="cursor: pointer;" onclick="viewPolicy(${policy.policy_id})" title="Click to view policy details">
                        <td><strong>${policy.policy_number}</strong></td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small">
                                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(policy.renter.name)}&background=1a1a1a&color=fff">
                                </div>
                                <div class="user-info">
                                    <span class="user-name">${policy.renter.name}</span>
                                    <span class="user-email">${policy.renter.email}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong style="text-transform: capitalize;">${policy.vehicle.type || 'N/A'}</strong><br>
                            <small style="color: #999;">${policy.vehicle.name || 'N/A'}</small>
                        </td>
                        <td>
                            <strong style="text-transform: uppercase;">${policy.coverage_type}</strong><br>
                            <small style="color: #999;">₱${formatNumber(policy.coverage_limit)} limit</small>
                        </td>
                        <td><strong style="color: #1a1a1a;">₱${formatNumber(policy.premium_amount)}</strong></td>
                        <td>
                            ${formatDate(policy.policy_start)} - ${formatDate(policy.policy_end)}<br>
                            <small style="color: #999;">${policy.days_remaining} days remaining</small>
                        </td>
                        <td><span class="status-badge ${policy.status}">${policy.status}</span></td>
                        <td onclick="event.stopPropagation();">
                            <button class="action-btn view" onclick="viewPolicy(${policy.policy_id})" title="Quick view">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="action-btn primary" onclick="viewPolicyFullDetails(${policy.policy_id})" title="Full details">
                                <i class="bi bi-file-text"></i>
                            </button>
                            <button class="action-btn success" onclick="downloadPolicyPDF(${policy.policy_id})" title="Download PDF">
                                <i class="bi bi-file-pdf"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div style="padding: 40px 20px;">
                                    <i class="bi bi-inbox" style="font-size: 48px; color: #ddd;"></i>
                                    <p style="margin-top: 16px; color: #999; font-size: 14px;">No policies found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            },
            error: function(xhr, status, error) {
                console.error('Policies Error:', error, xhr.responseText);
                document.getElementById('policies-tbody').innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div style="padding: 40px 20px;">
                                <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #dc3545;"></i>
                                <p style="margin-top: 16px; color: #dc3545; font-size: 14px;">Error loading policies. Please try again later.</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        });
    }

    // Load claims
    function loadClaims(page = 1) {
        claimsPage = page;
        const status = document.getElementById('claim-status-filter').value;
        const priority = document.getElementById('claim-priority-filter').value;
        
        console.log('Loading claims...');
        $.ajax({
            url: `${API_BASE}/admin/get_all_claims.php`,
            method: 'GET',
            data: { status, priority, page: claimsPage },
            dataType: 'json',
            success: function(response) {
                console.log('Claims loaded:', response);
                const tbody = document.getElementById('claims-tbody');
                
                if (response.success && response.data && response.data.length > 0) {
                    // Render pagination
                    if (response.pagination) {
                        renderPagination('claims-pagination', response.pagination.page, response.pagination.total_pages, 'loadClaims');
                    }
                    
                    tbody.innerHTML = response.data.map(claim => `
                    <tr style="cursor: pointer;" onclick="openClaimActionModal(${claim.claim_id}, '${claim.status}')" title="Click to view actions">
                        <td>
                            <strong>${claim.claim_number}</strong><br>
                            <small style="color: #999;">${formatDate(claim.created_at)}</small>
                        </td>
                        <td><strong>${claim.policy_number}</strong></td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small">
                                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(claim.claimant.name)}&background=1a1a1a&color=fff">
                                </div>
                                <span>${claim.claimant.name}</span>
                            </div>
                        </td>
                        <td><span class="status-badge">${claim.claim_type}</span></td>
                        <td><strong style="color: #1a1a1a;">₱${formatNumber(claim.claimed_amount)}</strong></td>
                        <td><strong style="color: #198754;">₱${formatNumber(claim.approved_amount)}</strong></td>
                        <td><span class="status-badge ${claim.priority}">${claim.priority}</span></td>
                        <td><span class="status-badge ${claim.status}">${claim.status}</span></td>
                        <td onclick="event.stopPropagation();">
                            <button class="action-btn view" onclick="openClaimActionModal(${claim.claim_id}, '${claim.status}')" title="Open Actions">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div style="padding: 40px 20px;">
                                    <i class="bi bi-inbox" style="font-size: 48px; color: #ddd;"></i>
                                    <p style="margin-top: 16px; color: #999; font-size: 14px;">No claims found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            },
            error: function(xhr, status, error) {
                console.error('Claims Error:', error, xhr.responseText);
                document.getElementById('claims-tbody').innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div style="padding: 40px 20px;">
                                <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #dc3545;"></i>
                                <p style="margin-top: 16px; color: #dc3545; font-size: 14px;">Error loading claims. Please try again later.</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        });
    }

    // Load providers
    function loadProviders(page = 1) {
        providersPage = page;
        $.ajax({
            url: `${API_BASE}/admin/get_providers.php`,
            method: 'GET',
            data: { page: providersPage },
            dataType: 'json',
            success: function(response) {
                const tbody = document.getElementById('providers-tbody');
                
                if (response.success && response.data && response.data.length > 0) {
                    // Render pagination
                    if (response.pagination) {
                        renderPagination('providers-pagination', response.pagination.page, response.pagination.total_pages, 'loadProviders');
                    }
                    
                    tbody.innerHTML = response.data.map(provider => `
                    <tr>
                        <td><strong>${provider.provider_name}</strong></td>
                        <td><i class="bi bi-telephone me-1"></i>${provider.contact_phone}</td>
                        <td><i class="bi bi-envelope me-1"></i>${provider.contact_email}</td>
                        <td><span class="status-badge ${provider.is_active ? 'approved' : 'cancelled'}">${provider.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td>
                            <button class="action-btn view" onclick="viewProvider(${provider.id})" title="Edit Provider">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div style="padding: 40px 20px;">
                                    <i class="bi bi-inbox" style="font-size: 48px; color: #ddd;"></i>
                                    <p style="margin-top: 16px; color: #999; font-size: 14px;">No providers found. Add your first insurance provider.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            },
            error: function(xhr, status, error) {
                console.error('Providers Error:', error, xhr.responseText);
                document.getElementById('providers-tbody').innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <div style="padding: 40px 20px;">
                                <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #dc3545;"></i>
                                <p style="margin-top: 16px; color: #dc3545; font-size: 14px;">Error loading providers. Please try again later.</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        });
    }

    // View policy details (Quick preview)
    function viewPolicy(policyId) {
        const modalBody = document.getElementById('policy-modal-body');
        
        openModal('policy-modal');
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        
        $.ajax({
            url: `${API_BASE}/admin/get_policy_details.php`,
            method: 'GET',
            data: { id: policyId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const policy = response.data;
                    
                    // Calculate status badge color
                    let statusColor = 'info';
                    if (policy.status === 'active') statusColor = 'success';
                    else if (policy.status === 'expired') statusColor = 'secondary';
                    else if (policy.status === 'claimed') statusColor = 'warning';
                    
                    // Format dates nicely
                    const startDate = new Date(policy.policy_start);
                    const endDate = new Date(policy.policy_end);
                    const issuedDate = new Date(policy.issued_at);
                    
                    modalBody.innerHTML = `
                        <!-- Policy Overview Card -->
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; border-radius: 12px; color: white; margin-bottom: 24px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                                <div>
                                    <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;">Policy Number</div>
                                    <div style="font-size: 24px; font-weight: 700; margin-top: 4px;">${policy.policy_number}</div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="status-badge ${statusColor}" style="font-size: 14px; padding: 8px 16px;">
                                        <i class="bi bi-shield-check me-1"></i>${policy.status.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 20px;">
                                <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
                                    <div style="font-size: 11px; opacity: 0.9; text-transform: uppercase;">Coverage Type</div>
                                    <div style="font-size: 16px; font-weight: 600; margin-top: 4px;">${policy.coverage_type.toUpperCase()}</div>
                                </div>
                                <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
                                    <div style="font-size: 11px; opacity: 0.9; text-transform: uppercase;">Premium</div>
                                    <div style="font-size: 16px; font-weight: 600; margin-top: 4px;">₱${formatNumber(policy.premium_amount)}</div>
                                </div>
                                <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
                                    <div style="font-size: 11px; opacity: 0.9; text-transform: uppercase;">Days Left</div>
                                    <div style="font-size: 16px; font-weight: 600; margin-top: 4px;">
                                        ${policy.days_remaining > 0 ? policy.days_remaining + ' days' : 'Expired'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Policy Details Grid -->
                        <div class="section-title"><i class="bi bi-file-text me-2"></i>Policy Details</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-shield-fill-check me-1"></i>Coverage Limit</div>
                                <div class="info-value">₱${formatNumber(policy.coverage_limit)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-cash-coin me-1"></i>Deductible</div>
                                <div class="info-value">₱${formatNumber(policy.deductible)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-calendar-check me-1"></i>Start Date</div>
                                <div class="info-value">${formatDate(policy.policy_start)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-calendar-x me-1"></i>End Date</div>
                                <div class="info-value">${formatDate(policy.policy_end)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-clock-history me-1"></i>Issued Date</div>
                                <div class="info-value">${formatDate(policy.issued_at)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-hourglass-split me-1"></i>Policy Duration</div>
                                <div class="info-value">${Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24))} days</div>
                            </div>
                        </div>
                        
                        <!-- Renter Information -->
                        <div class="section-title"><i class="bi bi-person-fill me-2"></i>Renter Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-person-badge me-1"></i>Name</div>
                                <div class="info-value">${policy.renter.name}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-envelope me-1"></i>Email</div>
                                <div class="info-value" style="word-break: break-all;">${policy.renter.email}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-telephone me-1"></i>Contact</div>
                                <div class="info-value">${policy.renter.contact || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <!-- Owner Information (NEW) -->
                        <div class="section-title"><i class="bi bi-person-check-fill me-2"></i>Vehicle Owner Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-person-badge me-1"></i>Name</div>
                                <div class="info-value">${policy.owner.name}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-envelope me-1"></i>Email</div>
                                <div class="info-value" style="word-break: break-all;">${policy.owner.email}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-telephone me-1"></i>Contact</div>
                                <div class="info-value">${policy.owner.contact || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <!-- Vehicle Information -->
                        <div class="section-title"><i class="bi bi-car-front-fill me-2"></i>Vehicle Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-tag me-1"></i>Vehicle Type</div>
                                <div class="info-value" style="text-transform: capitalize;">${policy.vehicle.type || 'Unknown'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-car-front me-1"></i>Vehicle</div>
                                <div class="info-value">${policy.vehicle.brand || 'Unknown'} ${policy.vehicle.model || ''}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-calendar3 me-1"></i>Year</div>
                                <div class="info-value">${policy.vehicle.year || 'N/A'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-credit-card-2-front me-1"></i>Plate Number</div>
                                <div class="info-value">${policy.vehicle.plate || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <!-- Booking Information (NEW) -->
                        <div class="section-title"><i class="bi bi-calendar2-check-fill me-2"></i>Booking Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-hash me-1"></i>Booking ID</div>
                                <div class="info-value">#${policy.booking.id}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-info-circle me-1"></i>Booking Status</div>
                                <div class="info-value"><span class="status-badge ${policy.booking.status}">${policy.booking.status}</span></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-calendar-event me-1"></i>Pickup Date</div>
                                <div class="info-value">${formatDate(policy.booking.pickup_date)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-calendar-event-fill me-1"></i>Return Date</div>
                                <div class="info-value">${formatDate(policy.booking.return_date)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><span class="currency-symbol me-1">₱</span>Booking Amount</div>
                                <div class="info-value">₱${formatNumber(policy.booking.amount)}</div>
                            </div>
                        </div>
                        
                        <!-- Provider Information -->
                        <div class="section-title"><i class="bi bi-building me-2"></i>Insurance Provider</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-building-fill me-1"></i>Provider Name</div>
                                <div class="info-value">${policy.provider.name}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-telephone-fill me-1"></i>Phone</div>
                                <div class="info-value">${policy.provider.phone}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="bi bi-envelope-fill me-1"></i>Email</div>
                                <div class="info-value" style="word-break: break-all;">${policy.provider.email}</div>
                            </div>
                        </div>
                        
                        <!-- Claims Summary -->
                        <div class="section-title"><i class="bi bi-file-medical-fill me-2"></i>Claims Summary</div>
                        <div class="info-grid">
                            <div class="info-item" style="background: ${policy.claims_summary.total_claims > 0 ? '#fff3cd' : '#f8f9fa'};">
                                <div class="info-label"><i class="bi bi-file-earmark-text me-1"></i>Total Claims</div>
                                <div class="info-value">${policy.claims_summary.total_claims}</div>
                            </div>
                            <div class="info-item" style="background: ${policy.claims_summary.approved_claims > 0 ? '#d1e7dd' : '#f8f9fa'};">
                                <div class="info-label"><i class="bi bi-check-circle me-1"></i>Approved Claims</div>
                                <div class="info-value">${policy.claims_summary.approved_claims}</div>
                            </div>
                            <div class="info-item" style="background: ${policy.claims_summary.total_claimed_amount > 0 ? '#f8d7da' : '#f8f9fa'};">
                                <div class="info-label"><i class="bi bi-cash-stack me-1"></i>Total Claimed Amount</div>
                                <div class="info-value">₱${formatNumber(policy.claims_summary.total_claimed_amount)}</div>
                            </div>
                            <div class="info-item" style="background: #e7f1ff;">
                                <div class="info-label"><i class="bi bi-calculator me-1"></i>Remaining Coverage</div>
                                <div class="info-value">₱${formatNumber(policy.coverage_limit - policy.claims_summary.total_claimed_amount)}</div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-card" style="margin-top: 24px; background: linear-gradient(to right, #f8f9fa, #e9ecef); border: 2px solid #dee2e6;">
                            <h6 style="margin-bottom: 16px; color: #1a1a1a;"><i class="bi bi-tools"></i> Quick Actions</h6>
                            <div class="action-grid">
                                <button class="modal-action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;" onclick="viewPolicyFullDetails(${policyId})">
                                    <i class="bi bi-file-text-fill"></i> Full Details
                                </button>
                                <button class="modal-action-btn" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;" onclick="downloadPolicyPDF(${policyId})">
                                    <i class="bi bi-file-pdf-fill"></i> Download PDF
                                </button>
                                <button class="modal-action-btn" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;" onclick="sendPolicyEmail(${policyId})">
                                    <i class="bi bi-envelope-fill"></i> Email Policy
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger" style="display: flex; align-items: center; gap: 12px;">
                            <i class="bi bi-exclamation-triangle-fill" style="font-size: 24px;"></i>
                            <div>${response.message}</div>
                        </div>
                    `;
                }
            },
            error: function(xhr) {
                modalBody.innerHTML = `
                    <div class="alert alert-danger" style="display: flex; align-items: center; gap: 12px;">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 24px;"></i>
                        <div>Error loading policy details. Please try again later.</div>
                    </div>
                `;
                console.error('Error loading policy:', xhr.responseText);
            }
        });
    }

    // View full policy details (Enhanced modal)
    function viewPolicyFullDetails(policyId) {
        // Close the quick preview modal
        closeModal('policy-modal');
        
        // Open the full details modal
        const modalBody = document.getElementById('policy-full-details-body');
        
        openModal('policy-full-details-modal');
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        
        $.ajax({
            url: `${API_BASE}/admin/get_policy_details.php`,
            method: 'GET',
            data: { id: policyId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const policy = response.data;
                    modalBody.innerHTML = `
                        <div class="alert alert-info" style="border-left: 4px solid #1976d2;">
                            <i class="bi bi-info-circle"></i> <strong>Complete Policy Information</strong><br>
                            This view contains all detailed information about the insurance policy.
                        </div>
                        
                        <div class="section-title">📋 Policy Overview</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Policy Number</div>
                                <div class="info-value">${policy.policy_number}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Booking ID</div>
                                <div class="info-value">#${policy.booking.id}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value"><span class="status-badge ${policy.status}">${policy.status}</span></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Coverage Type</div>
                                <div class="info-value">${policy.coverage_type || 'Basic'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Policy Start</div>
                                <div class="info-value">${formatDate(policy.policy_start)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Policy End</div>
                                <div class="info-value">${formatDate(policy.policy_end)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Days Remaining</div>
                                <div class="info-value" style="color: ${policy.days_remaining < 0 ? '#dc3545' : policy.days_remaining < 7 ? '#ff9800' : '#28a745'};">
                                    ${policy.days_remaining} days ${policy.days_remaining < 0 ? '(Expired)' : ''}
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Issued Date</div>
                                <div class="info-value">${formatDate(policy.issued_at)}</div>
                            </div>
                        </div>
                        
                        <div class="section-title">💰 Financial Details</div>
                        <div class="info-grid">
                            <div class="info-item" style="border-left-color: #28a745;">
                                <div class="info-label">Premium Amount</div>
                                <div class="info-value" style="color: #28a745; font-size: 20px;">₱${formatNumber(policy.premium_amount)}</div>
                            </div>
                            <div class="info-item" style="border-left-color: #1976d2;">
                                <div class="info-label">Coverage Limit</div>
                                <div class="info-value" style="color: #1976d2; font-size: 20px;">₱${formatNumber(policy.coverage_limit)}</div>
                            </div>
                            <div class="info-item" style="border-left-color: #ff9800;">
                                <div class="info-label">Deductible</div>
                                <div class="info-value">₱${formatNumber(policy.deductible)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Terms Accepted</div>
                                <div class="info-value">${policy.terms_accepted ? '✓ Yes' : '✗ No'}</div>
                            </div>
                        </div>
                        
                        <div class="section-title">🛡️ Coverage Breakdown</div>
                        <table class="table table-bordered">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th>Coverage Type</th>
                                    <th style="text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="bi bi-car-front-fill text-primary"></i> Collision Coverage</td>
                                    <td style="text-align: right; font-weight: 600;">₱${formatNumber(policy.collision_coverage || 0)}</td>
                                </tr>
                                <tr>
                                    <td><i class="bi bi-people-fill text-success"></i> Third-Party Liability</td>
                                    <td style="text-align: right; font-weight: 600;">₱${formatNumber(policy.liability_coverage || 0)}</td>
                                </tr>
                                <tr>
                                    <td><i class="bi bi-shield-fill-exclamation text-danger"></i> Theft Coverage</td>
                                    <td style="text-align: right; font-weight: 600;">₱${formatNumber(policy.theft_coverage || 0)}</td>
                                </tr>
                                <tr>
                                    <td><i class="bi bi-heart-pulse-fill text-warning"></i> Personal Injury</td>
                                    <td style="text-align: right; font-weight: 600;">₱${formatNumber(policy.personal_injury_coverage || 0)}</td>
                                </tr>
                                <tr>
                                    <td><i class="bi bi-wrench text-info"></i> Roadside Assistance</td>
                                    <td style="text-align: right; font-weight: 600;">${policy.roadside_assistance ? 'Included ✓' : 'Not Included ✗'}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr style="background: #e8f5e9; font-weight: bold;">
                                    <td>Total Coverage Limit</td>
                                    <td style="text-align: right; color: #28a745; font-size: 18px;">₱${formatNumber(policy.coverage_limit)}</td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <div class="section-title">👤 Owner Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value">${policy.owner?.name || 'N/A'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value">${policy.owner?.email || 'N/A'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Number</div>
                                <div class="info-value">${policy.owner?.contact || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <div class="section-title">🧑 Renter Information (Insured Driver)</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value">${policy.renter.name}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email Address</div>
                                <div class="info-value">${policy.renter.email}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Number</div>
                                <div class="info-value">${policy.renter.contact}</div>
                            </div>
                        </div>
                        
                        <div class="section-title">🚗 Vehicle Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Vehicle Details</div>
                                <div class="info-value">${policy.vehicle.brand} ${policy.vehicle.model} ${policy.vehicle.year}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Plate Number</div>
                                <div class="info-value" style="font-family: monospace; font-weight: 700;">${policy.vehicle.plate}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Vehicle Type</div>
                                <div class="info-value">${policy.vehicle_type || 'Car'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Vehicle ID</div>
                                <div class="info-value">#${policy.vehicle_id}</div>
                            </div>
                        </div>
                        
                        <div class="section-title">🏢 Insurance Provider</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Provider Name</div>
                                <div class="info-value">${policy.provider.name}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Email</div>
                                <div class="info-value">${policy.provider.email}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Phone</div>
                                <div class="info-value">${policy.provider.phone}</div>
                            </div>
                        </div>
                        
                        <div class="section-title">📊 Claims Summary</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Total Claims Filed</div>
                                <div class="info-value" style="font-size: 24px; color: #1976d2;">${policy.claims_summary.total_claims}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Approved Claims</div>
                                <div class="info-value" style="font-size: 24px; color: #28a745;">${policy.claims_summary.approved_claims}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Total Claimed Amount</div>
                                <div class="info-value" style="font-size: 20px; color: #dc3545;">₱${formatNumber(policy.claims_summary.total_claimed_amount)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Claim Rate</div>
                                <div class="info-value">${policy.claims_summary.total_claims > 0 ? ((policy.claims_summary.approved_claims / policy.claims_summary.total_claims) * 100).toFixed(1) + '%' : '0%'}</div>
                            </div>
                        </div>
                        
                        <div class="action-card" style="margin-top: 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <h6 style="color: white;"><i class="bi bi-download"></i> Download & Share</h6>
                            <div class="action-grid">
                                <button class="modal-action-btn" style="background: white; color: #7b1fa2;" onclick="downloadPolicyPDF(${policyId})">
                                    <i class="bi bi-file-pdf"></i> Download PDF Certificate
                                </button>
                                <button class="modal-action-btn" style="background: white; color: #2e7d32;" onclick="sendPolicyEmail(${policyId})">
                                    <i class="bi bi-envelope"></i> Email to Owner & Renter
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    modalBody.innerHTML = `<div class="alert alert-danger">${response.message}</div>`;
                }
            },
            error: function() {
                modalBody.innerHTML = '<div class="alert alert-danger">Error loading policy details</div>';
            }
        });
    }

    // Download policy PDF
    function downloadPolicyPDF(policyId) {
        if (!policyId) {
            alert('Error: Policy ID is required');
            return;
        }
        window.open(`${API_BASE}/generate_policy_pdf_simple.php?policy_id=${policyId}&download=1`, '_blank');
    }

    // Send policy email
    function sendPolicyEmail(policyId) {
        if (!confirm('Send policy certificate to owner and renter via email?\n\nThis will send a professional email with complete policy details to both the vehicle owner and the renter.')) return;
        
        // Show loading indicator
        const originalButton = event.target.closest('button');
        const originalHTML = originalButton.innerHTML;
        originalButton.disabled = true;
        originalButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';
        
        $.ajax({
            url: `${API_BASE}/send_policy_email.php`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                policy_id: policyId
            }),
            success: function(response) {
                originalButton.disabled = false;
                originalButton.innerHTML = originalHTML;
                
                if (response.success) {
                    let message = '✅ Email sent successfully!\n\n';
                    if (response.sent_to && response.sent_to.length > 0) {
                        message += 'Sent to:\n' + response.sent_to.join('\n');
                    }
                    if (response.errors && response.errors.length > 0) {
                        message += '\n\n⚠️ Some errors occurred:\n' + response.errors.join('\n');
                    }
                    alert(message);
                } else {
                    alert('❌ Error: ' + (response.message || 'Failed to send email'));
                }
            },
            error: function(xhr) {
                originalButton.disabled = false;
                originalButton.innerHTML = originalHTML;
                
                let errorMsg = '❌ Error sending policy email';
                try {
                    const response = JSON.parse(xhr.responseText);
                    // Show debug info if available
                    if (response.debug) {
                        errorMsg = '🔍 DEBUG INFO:\n' + JSON.stringify(response, null, 2);
                    } else {
                        errorMsg += ':\n' + (response.message || 'Unknown error');
                    }
                } catch (e) {
                    errorMsg += ':\nServer error - ' + xhr.status + '\n\nResponse: ' + xhr.responseText.substring(0, 500);
                }
                alert(errorMsg);
                console.error('Email error:', xhr.responseText);
            }
        });
    }

    // Open claim actions modal
    function openClaimActionModal(claimId, status) {
        console.log('Opening claim modal for ID:', claimId);
        fetch(`${API_BASE}/admin/get_claim_details.php?id=${claimId}`)
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('API Response:', data);
                if (data.success) {
                    const claim = data.data;
                    const statusClass = {
                        'submitted': 'warning',
                        'under_review': 'info',
                        'approved': 'success',
                        'rejected': 'danger',
                        'paid': 'success'
                    }[claim.status] || 'secondary';

                    const priorityClass = {
                        'urgent': 'danger',
                        'high': 'warning',
                        'normal': 'info',
                        'low': 'secondary'
                    }[claim.priority] || 'secondary';
                    
                    let actionsHtml = `
                        <div class="action-card">
                            <h6><i class="bi bi-info-circle"></i> Claim Overview</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Claim #:</strong> ${claim.claim_number}</p>
                                    <p><strong>Policy #:</strong> ${claim.policy_number}</p>
                                    <p><strong>Claimant:</strong> ${claim.claimant.name}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${claim.status}</span></p>
                                    <p><strong>Priority:</strong> <span class="status-badge ${priorityClass}">${claim.priority}</span></p>
                                    <p><strong>Claimed Amount:</strong> ₱${formatNumber(claim.claimed_amount)}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-card">
                            <h6><i class="bi bi-eye"></i> View Information</h6>
                            <div class="action-grid">
                                <button class="modal-action-btn" style="background: #e3f2fd; color: #1976d2;" onclick="viewClaim(${claim.id})">
                                    <i class="bi bi-file-text"></i> View Full Details
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Status-specific actions
                    if (claim.status === 'submitted' || claim.status === 'under_review') {
                        actionsHtml += `
                            <div class="action-card">
                                <h6><i class="bi bi-pencil"></i> Claim Actions</h6>
                                <div class="action-grid">
                                    <button class="modal-action-btn" style="background: #28a745; color: white;" onclick="approveClaim(${claim.id})">
                                        <i class="bi bi-check-lg"></i> Approve Claim
                                    </button>
                                    <button class="modal-action-btn" style="background: #dc3545; color: white;" onclick="rejectClaim(${claim.id})">
                                        <i class="bi bi-x-lg"></i> Reject Claim
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                    
                    document.getElementById('claim-actions-modal-body').innerHTML = actionsHtml;
                    openModal('claim-actions-modal');
                } else {
                    console.error('API Error:', data.message);
                    alert('Error loading claim details: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                alert('Network error: ' + err.message);
            });
    }

    // View claim details
    function viewClaim(claimId) {
        const modalBody = document.getElementById('claim-modal-body');
        const modalFooter = document.getElementById('claim-modal-footer');
        
        openModal('claim-modal');
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        modalFooter.innerHTML = '';
        
        $.ajax({
            url: `${API_BASE}/admin/get_claim_details.php`,
            method: 'GET',
            data: { id: claimId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const claim = response.data;
                    modalBody.innerHTML = `
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Claim Number</div>
                                <div class="info-value">${claim.claim_number}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value"><span class="status-badge ${claim.status}">${claim.status}</span></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Priority</div>
                                <div class="info-value"><span class="status-badge ${claim.priority}">${claim.priority}</span></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Claim Type</div>
                                <div class="info-value">${claim.claim_type}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Incident Date</div>
                                <div class="info-value">${formatDate(claim.incident_date)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Incident Location</div>
                                <div class="info-value">${claim.incident_location || 'N/A'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Claimed Amount</div>
                                <div class="info-value">₱${formatNumber(claim.claimed_amount)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Approved Amount</div>
                                <div class="info-value">₱${formatNumber(claim.approved_amount)}</div>
                            </div>
                        </div>
                        
                        <div class="section-title">Incident Description</div>
                        <div style="padding: 16px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                            ${claim.incident_description}
                        </div>
                        
                        ${claim.evidence_photos && claim.evidence_photos.length > 0 ? `
                        <div class="section-title">📸 Evidence Photos / Damage Images</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                            ${claim.evidence_photos.map((photo, index) => `
                                <div style="position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                    <img src="${photo}" alt="Evidence Photo ${index + 1}" style="width: 100%; height: 200px; object-fit: cover; cursor: pointer;" onclick="window.open('${photo}', '_blank')">
                                    <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); padding: 8px; color: white; font-size: 12px; text-align: center;">
                                        Photo ${index + 1}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        ` : ''}
                        
                        ${claim.police_report_number ? `
                        <div class="section-title">🚔 Police Report</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Report Number</div>
                                <div class="info-value">${claim.police_report_number}</div>
                            </div>
                            ${claim.police_report_file ? `
                            <div class="info-item">
                                <div class="info-label">Report File</div>
                                <div class="info-value">
                                    <a href="${claim.police_report_file}" target="_blank" style="color: #1976d2; text-decoration: none;">
                                        <i class="bi bi-file-pdf"></i> View Report
                                    </a>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        ` : ''}
                        
                        <div class="section-title">Claimant Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Name</div>
                                <div class="info-value">${claim.claimant.name}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value">${claim.claimant.email}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact</div>
                                <div class="info-value">${claim.claimant.contact}</div>
                            </div>
                        </div>
                        
                        ${claim.review_notes ? `
                            <div class="section-title">Review Notes</div>
                            <div style="padding: 16px; background: #f8f9fa; border-radius: 8px;">
                                ${claim.review_notes}
                            </div>
                        ` : ''}
                        
                        ${claim.rejection_reason ? `
                            <div class="section-title">Rejection Reason</div>
                            <div style="padding: 16px; background: #ffebee; border-radius: 8px; color: #c62828;">
                                ${claim.rejection_reason}
                            </div>
                        ` : ''}
                    `;
                    
                    // Add action buttons if claim is pending
                    if (claim.status === 'submitted' || claim.status === 'under_review') {
                        modalFooter.innerHTML = `
                            <button class="btn btn-success" onclick="approveClaimFromModal(${claimId})">
                                <i class="bi bi-check-lg"></i> Approve Claim
                            </button>
                            <button class="btn btn-danger" onclick="rejectClaimFromModal(${claimId})">
                                <i class="bi bi-x-lg"></i> Reject Claim
                            </button>
                        `;
                    }
                } else {
                    modalBody.innerHTML = `<div class="alert alert-danger">${response.message}</div>`;
                }
            },
            error: function() {
                modalBody.innerHTML = '<div class="alert alert-danger">Error loading claim details</div>';
            }
        });
    }

    // Approve claim from modal
    function approveClaimFromModal(claimId) {
        const approvedAmount = prompt('Enter approved amount:');
        if (!approvedAmount) return;
        
        const reviewNotes = prompt('Enter review notes (optional):') || '';
        
        $.ajax({
            url: `${API_BASE}/admin/approve_claim.php`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                claim_id: claimId,
                approved_amount: parseFloat(approvedAmount),
                review_notes: reviewNotes,
                admin_id: 1
            }),
            success: function(response) {
                if (response.success) {
                    alert('✓ Claim approved successfully!');
                    closeModal('claim-modal');
                    loadClaims();
                } else {
                    alert('✗ Error: ' + response.message);
                }
            },
            error: function() {
                alert('✗ Error approving claim');
            }
        });
    }

    // Reject claim from modal
    function rejectClaimFromModal(claimId) {
        const rejectionReason = prompt('Enter rejection reason:');
        if (!rejectionReason) return;
        
        $.ajax({
            url: `${API_BASE}/admin/reject_claim.php`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                claim_id: claimId,
                rejection_reason: rejectionReason,
                admin_id: 1
            }),
            success: function(response) {
                if (response.success) {
                    alert('✓ Claim rejected successfully!');
                    closeModal('claim-modal');
                    loadClaims();
                } else {
                    alert('✗ Error: ' + response.message);
                }
            },
            error: function() {
                alert('✗ Error rejecting claim');
            }
        });
    }

    // Approve claim (quick action from table)
    function approveClaim(claimId) {
        const approvedAmount = prompt('Enter approved amount:');
        if (!approvedAmount) return;
        
        const reviewNotes = prompt('Enter review notes (optional):') || '';
        
        $.ajax({
            url: `${API_BASE}/admin/approve_claim.php`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                claim_id: claimId,
                approved_amount: parseFloat(approvedAmount),
                review_notes: reviewNotes,
                admin_id: 1
            }),
            success: function(response) {
                if (response.success) {
                    alert('✓ Claim approved successfully!');
                    loadClaims();
                } else {
                    alert('✗ Error: ' + response.message);
                }
            },
            error: function() {
                alert('✗ Error approving claim');
            }
        });
    }

    // Reject claim (quick action from table)
    function rejectClaim(claimId) {
        const rejectionReason = prompt('Enter rejection reason:');
        if (!rejectionReason) return;
        
        $.ajax({
            url: `${API_BASE}/admin/reject_claim.php`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                claim_id: claimId,
                rejection_reason: rejectionReason,
                admin_id: 1
            }),
            success: function(response) {
                if (response.success) {
                    alert('✓ Claim rejected successfully!');
                    loadClaims();
                } else {
                    alert('✗ Error: ' + response.message);
                }
            },
            error: function() {
                alert('✗ Error rejecting claim');
            }
        });
    }

    // View/Edit provider
    function viewProvider(providerId) {
        const modalBody = document.getElementById('provider-modal-body');
        
        openModal('provider-modal');
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        
        $.ajax({
            url: `${API_BASE}/admin/get_provider_details.php`,
            method: 'GET',
            data: { id: providerId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const provider = response.data;
                    modalBody.innerHTML = `
                        <form id="provider-form">
                            <input type="hidden" id="provider-id" value="${provider.id}">
                            
                            <div class="mb-3">
                                <label for="provider-name" class="form-label">Provider Name</label>
                                <input type="text" class="form-control" id="provider-name" value="${provider.provider_name}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="provider-phone" class="form-label">Contact Phone</label>
                                <input type="text" class="form-control" id="provider-phone" value="${provider.contact_phone}">
                            </div>
                            
                            <div class="mb-3">
                                <label for="provider-email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="provider-email" value="${provider.contact_email}">
                            </div>
                            
                            <div class="mb-3">
                                <label for="provider-status" class="form-label">Status</label>
                                <select class="form-control" id="provider-status">
                                    <option value="active" ${provider.status === 'active' ? 'selected' : ''}>Active</option>
                                    <option value="inactive" ${provider.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="section-title">Statistics</div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Total Policies</div>
                                    <div class="info-value">${provider.statistics.total_policies}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Active Policies</div>
                                    <div class="info-value">${provider.statistics.active_policies}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Total Premiums</div>
                                    <div class="info-value">₱${formatNumber(provider.statistics.total_premiums)}</div>
                                </div>
                            </div>
                            
                            <div class="modal-footer mt-4">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('provider-modal')">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    `;
                    
                    // Handle form submission
                    document.getElementById('provider-form').addEventListener('submit', function(e) {
                        e.preventDefault();
                        updateProvider();
                    });
                } else {
                    modalBody.innerHTML = `<div class="alert alert-danger">${response.message}</div>`;
                }
            },
            error: function() {
                modalBody.innerHTML = '<div class="alert alert-danger">Error loading provider details</div>';
            }
        });
    }

    // Update provider
    function updateProvider() {
        const providerId = document.getElementById('provider-id').value;
        const providerName = document.getElementById('provider-name').value;
        const providerPhone = document.getElementById('provider-phone').value;
        const providerEmail = document.getElementById('provider-email').value;
        const providerStatus = document.getElementById('provider-status').value;
        
        $.ajax({
            url: `${API_BASE}/admin/update_provider.php`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                id: parseInt(providerId),
                provider_name: providerName,
                contact_phone: providerPhone,
                contact_email: providerEmail,
                status: providerStatus
            }),
            success: function(response) {
                if (response.success) {
                    alert('✓ Provider updated successfully!');
                    closeModal('provider-modal');
                    loadProviders();
                } else {
                    alert('✗ Error: ' + response.message);
                }
            },
            error: function() {
                alert('✗ Error updating provider');
            }
        });
    }

    // Helper functions
    function formatNumber(num) {
        return parseFloat(num).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    // Load initial data
    loadPolicies();
    </script>
    </body>
    </html>