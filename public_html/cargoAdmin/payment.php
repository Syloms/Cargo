<?php
session_start();
include "include/db.php";
include "include/admin_profile.php";

/* =========================================================
   PAYMENT STATISTICS
   ========================================================= */

// Regular payments - Pending verification
$pendingVerification = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM payments p
     WHERE p.payment_status='pending'
     AND NOT EXISTS (
         SELECT 1 FROM payment_transactions pt 
         WHERE pt.booking_id = p.booking_id 
         AND pt.reference_id = p.payment_reference
         AND pt.transaction_type = 'late_fee_payment'
     )"
))['c'];

// Late fee payments - Pending verification (from late_fee_payments table)
$pendingLateFees = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM late_fee_payments 
     WHERE payment_status='pending'"
))['c'];

// Funds in escrow (verified)
$escrowed = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(amount) AS total FROM payments WHERE payment_status='verified'"
))['total'] ?? 0;

// Total verified payments this month
$verifiedThisMonth = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM payments 
     WHERE payment_status='verified' 
     AND MONTH(created_at) = MONTH(NOW()) 
     AND YEAR(created_at) = YEAR(NOW())"
))['c'];

// Total payment amount this month
$totalPaymentsThisMonth = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(amount), 0) AS total FROM payments 
     WHERE payment_status IN ('verified', 'released')
     AND MONTH(created_at) = MONTH(NOW()) 
     AND YEAR(created_at) = YEAR(NOW())"
))['total'] ?? 0;

/* =========================================================
   PAGINATION
   ========================================================= */
$limit = 10;
$page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$offset = ($page - 1) * $limit;

/* =========================================================
   FILTERS
   ========================================================= */
$allowedStatuses = [
    'pending','verified','processing','released',
    'completed','rejected','failed','refunded'
];

$statusFilter = '';
if (isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true)) {
    $statusFilter = $_GET['status'];
}

// Payment type filter (regular or late_fee)
$paymentType = isset($_GET['type']) ? trim($_GET['type']) : 'regular';
$allowedTypes = ['regular', 'late_fee'];
if (!in_array($paymentType, $allowedTypes)) {
    $paymentType = 'regular';
}

// Build WHERE clause based on payment type
if ($paymentType === 'late_fee') {
    // For late fee payments, query the late_fee_payments table
    $where = " WHERE 1 ";
    $where .= " AND lfp.payment_method = 'gcash' ";
    
    if ($statusFilter !== "" && $statusFilter !== "all") {
        $where .= " AND lfp.payment_status = '" . mysqli_real_escape_string($conn, $statusFilter) . "' ";
    }
} else {
    // For regular payments, query the payments table
    $where = " WHERE 1 ";
    $where .= " AND p.payment_method = 'gcash' ";
    
    // Regular payments: exclude late fee payments
    $where .= " AND NOT EXISTS (
        SELECT 1 FROM payment_transactions pt 
        WHERE pt.booking_id = p.booking_id 
        AND pt.transaction_type = 'payment'
        AND JSON_EXTRACT(pt.metadata, '$.payment_type') = 'late_fee_payment'
    ) ";
    
    if ($statusFilter !== "" && $statusFilter !== "all") {
        $where .= " AND p.payment_status = '" . mysqli_real_escape_string($conn, $statusFilter) . "' ";
    }
}

/* =========================================================
   MAIN QUERY - FIXED WITH MOTORCYCLE SUPPORT
   ========================================================= */

if ($paymentType === 'late_fee') {
    // Query late_fee_payments table
    $sql = "SELECT 
    lfp.id, lfp.booking_id, lfp.user_id, lfp.total_amount as amount, 
    lfp.late_fee_amount, lfp.rental_amount, lfp.is_rental_paid,
    lfp.payment_method, lfp.payment_reference, 
    lfp.payment_status, lfp.verification_notes, lfp.verified_by, lfp.verified_at, lfp.created_at,
    b.pickup_date, b.return_date, b.status AS booking_status, b.total_amount AS booking_total,
    b.escrow_status, b.platform_fee, b.owner_payout, b.payout_status, b.vehicle_type,
    b.late_fee_payment_status, b.overdue_days,
    u1.id AS renter_id, u1.fullname AS renter_name, u1.email AS renter_email, u1.phone AS renter_phone,
    u2.id AS owner_id, u2.fullname AS owner_name,
    COALESCE(c.brand, m.brand, 'Unknown') AS brand,
    COALESCE(c.model, m.model, 'Unknown') AS model,
    COALESCE(c.car_year, m.motorcycle_year, '') AS car_year,
    COALESCE(c.plate_number, m.plate_number, '') AS plate_number,
    COALESCE(c.image, m.image, '') AS image,
    CASE 
      WHEN b.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model)
      WHEN b.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model)
      ELSE 'Unknown'
    END AS vehicle_name
    FROM late_fee_payments lfp
    JOIN bookings b ON lfp.booking_id = b.id
    JOIN users u1 ON lfp.user_id = u1.id
    JOIN users u2 ON b.owner_id = u2.id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    $where
    ORDER BY lfp.created_at DESC
    LIMIT $limit OFFSET $offset";
} else {
    // Query regular payments table
    $sql = "SELECT 
    /* ================= PAYMENTS ================= */
    p.id,
    p.booking_id,
    p.amount,
    p.payment_method,
    p.payment_reference,
    p.payment_status,
    p.created_at,

    /* ================= BOOKINGS ================= */
    b.status AS booking_status,
    b.platform_fee,
    b.owner_payout,
    b.escrow_status,
    b.payout_status,
    b.pickup_date,
    b.return_date,
    b.vehicle_type,

    /* ================= USERS ================= */
    u1.id AS renter_id,
    u1.fullname AS renter_name,
    u1.email AS renter_email,
    u1.phone AS renter_phone,
    u2.id AS owner_id,
    u2.fullname AS owner_name,
    u2.email AS owner_email,

    /* ================= VEHICLE (CAR OR MOTORCYCLE) ================= */
    COALESCE(c.brand, m.brand) AS brand,
    COALESCE(c.model, m.model) AS model,
    COALESCE(c.car_year, m.motorcycle_year) AS car_year,
    COALESCE(c.plate_number, m.plate_number) AS plate_number

FROM payments p
JOIN bookings b ON p.booking_id = b.id
JOIN users u1 ON p.user_id = u1.id
JOIN users u2 ON b.owner_id = u2.id

-- Support both cars and motorcycles
LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id

$where
ORDER BY p.created_at DESC
LIMIT $limit OFFSET $offset";
}

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("SQL ERROR: " . mysqli_error($conn));
}

/* =========================================================
   COUNT FOR PAGINATION
   ========================================================= */
if ($paymentType === 'late_fee') {
    $countSql = "SELECT COUNT(*) AS total 
    FROM late_fee_payments lfp 
    JOIN bookings b ON lfp.booking_id = b.id
    $where";
} else {
    $countSql = "SELECT COUNT(*) AS total 
    FROM payments p 
    JOIN bookings b ON p.booking_id = b.id
    $where";
}

$countRes = mysqli_query($conn, $countSql);
$totalRows = mysqli_fetch_assoc($countRes)['total'];
$totalPages = max(1, ceil($totalRows / $limit));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Management - CarGo Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="include/admin-styles.css" rel="stylesheet">
  <link href="include/notifications.css" rel="stylesheet">
  <link href="include/modal-theme-standardized.css" rel="stylesheet">
  <style>
    /* Modern Black & White Payment Dashboard */
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #fafafa;
    }

    .payment-dashboard {
      background: #fafafa;
      min-height: 100vh;
    }

    /* Modern Stats Cards */
    .modern-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2rem;
    }

    .modern-stat-card {
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 12px;
      padding: 1.75rem;
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .modern-stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: #000;
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s ease;
    }

    .modern-stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
      border-color: #000;
    }

    .modern-stat-card:hover::before {
      transform: scaleX(1);
    }

    .stat-header-modern {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }

    .stat-icon-modern {
      width: 56px;
      height: 56px;
      background: #000;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 24px;
      transition: all 0.3s ease;
    }

    .modern-stat-card:hover .stat-icon-modern {
      transform: rotate(5deg) scale(1.05);
    }

    /* Payment Type Tabs */
    .payment-type-tabs {
      display: flex;
      gap: 16px;
      background: white;
      padding: 20px;
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .payment-type-tab {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      padding: 20px;
      background: #f8f9fa;
      border: 2px solid transparent;
      border-radius: 12px;
      text-decoration: none;
      color: #666;
      font-weight: 600;
      font-size: 15px;
      transition: all 0.3s ease;
      position: relative;
    }

    .payment-type-tab:hover {
      background: #e9ecef;
      border-color: #dee2e6;
      color: #333;
      transform: translateY(-2px);
    }

    .payment-type-tab.active {
      background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
      border-color: #1a1a1a;
      color: white;
      box-shadow: 0 4px 12px rgba(26, 26, 26, 0.2);
    }

    .payment-type-tab i {
      font-size: 20px;
    }

    .payment-type-tab .tab-badge {
      position: absolute;
      top: 8px;
      right: 8px;
      background: #dc3545;
      color: white;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 700;
    }

    .payment-type-tab.active .tab-badge {
      background: #fff;
      color: #dc3545;
    }

    .payment-type-tab .tab-badge.warning {
      background: #ff9800;
    }

    .payment-type-tab.active .tab-badge.warning {
      background: #fff;
      color: #ff9800;
    }

    .stat-badge {
      padding: 6px 12px;
      background: #f5f5f5;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-value-modern {
      font-size: 2.25rem;
      font-weight: 800;
      color: #000;
      margin-bottom: 0.5rem;
      letter-spacing: -0.02em;
    }

    .stat-label-modern {
      font-size: 0.875rem;
      color: #666;
      font-weight: 500;
      letter-spacing: 0.01em;
    }

    /* Modern Filter Section */
    .modern-filter-section {
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .filter-grid {
      display: grid;
      grid-template-columns: 1fr auto auto auto;
      gap: 1rem;
      align-items: center;
    }

    .modern-search-box {
      position: relative;
    }

    .modern-search-input {
      width: 100%;
      padding: 0.875rem 1rem 0.875rem 3rem;
      border: 1px solid #e5e5e5;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s ease;
      background: #fafafa;
    }

    .modern-search-input:focus {
      outline: none;
      border-color: #000;
      background: white;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
    }

    .modern-search-box i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      font-size: 16px;
    }

    .modern-filter-dropdown {
      padding: 0.875rem 1rem;
      border: 1px solid #e5e5e5;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      background: #fafafa;
      min-width: 200px;
    }

    .modern-filter-dropdown:focus {
      outline: none;
      border-color: #000;
      background: white;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
    }

    .modern-btn {
      padding: 0.875rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .modern-btn-primary {
      background: #000;
      color: white;
    }

    .modern-btn-primary:hover {
      background: #1a1a1a;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .modern-btn-secondary {
      background: white;
      color: #000;
      border: 1px solid #e5e5e5;
    }

    .modern-btn-secondary:hover {
      background: #fafafa;
      border-color: #000;
    }

    /* Modern Tab Navigation */
    .modern-tabs {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
      border-bottom: 1px solid #e5e5e5;
    }

    .modern-tab {
      padding: 1rem 1.5rem;
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      font-size: 0.875rem;
      font-weight: 600;
      color: #666;
      cursor: pointer;
      transition: all 0.2s ease;
      position: relative;
      margin-bottom: -1px;
    }

    .modern-tab.active {
      color: #000;
      border-bottom-color: #000;
    }

    .modern-tab:hover:not(.active) {
      color: #000;
      background: #fafafa;
    }

    .tab-count {
      margin-left: 0.5rem;
      padding: 2px 8px;
      background: #f5f5f5;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 700;
    }

    .modern-tab.active .tab-count {
      background: #000;
      color: white;
    }

    /* Modern Payment Cards */
    .payment-cards-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(520px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2rem;
    }

    .modern-payment-card {
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 12px;
      padding: 1.75rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .modern-payment-card::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 4px;
      background: #000;
      transform: scaleY(0);
      transition: transform 0.3s ease;
    }

    .modern-payment-card:hover {
      border-color: #000;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
      transform: translateY(-2px);
    }

    .modern-payment-card:hover::before {
      transform: scaleY(1);
    }

    .payment-card-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1.25rem;
      padding-bottom: 1.25rem;
      border-bottom: 1px solid #f5f5f5;
    }

    .payment-id-section {
      flex: 1;
    }

    .payment-id-modern {
      font-weight: 800;
      font-size: 1.125rem;
      color: #000;
      margin-bottom: 0.375rem;
      letter-spacing: -0.01em;
    }

    .payment-meta {
      font-size: 0.8125rem;
      color: #666;
      margin-bottom: 0.25rem;
    }

    .payment-amount-section {
      text-align: right;
    }

    .payment-amount-modern {
      font-size: 1.875rem;
      font-weight: 800;
      color: #000;
      margin-bottom: 0.25rem;
      letter-spacing: -0.02em;
    }

    .payment-fee-breakdown {
      font-size: 0.75rem;
      color: #666;
      display: flex;
      flex-direction: column;
      gap: 0.125rem;
    }

    .fee-item {
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
    }

    .payment-details-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 1.25rem;
    }

    .detail-modern {
      display: flex;
      flex-direction: column;
      gap: 0.375rem;
    }

    .detail-label-modern {
      font-size: 0.6875rem;
      color: #999;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }

    .detail-value-modern {
      font-size: 0.875rem;
      font-weight: 600;
      color: #000;
    }

    .modern-badges {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-bottom: 1.25rem;
    }

    .modern-badge {
      padding: 0.5rem 0.875rem;
      border-radius: 6px;
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
    }

    .modern-badge.pending {
      background: #f5f5f5;
      color: #666;
      border: 1px solid #e5e5e5;
    }

    .modern-badge.verified {
      background: #000;
      color: white;
    }

    .modern-badge.held {
      background: #fafafa;
      color: #000;
      border: 1px solid #e5e5e5;
    }

    .modern-badge.released_to_owner {
      background: #f5f5f5;
      color: #666;
      border: 1px dashed #ccc;
    }

    .modern-badge.completed,
    .modern-badge.released {
      background: #000;
      color: white;
    }

    .modern-badge.rejected,
    .modern-badge.cancelled {
      background: white;
      color: #000;
      border: 1px solid #000;
    }

    .payment-actions-modern {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .action-btn-modern {
      flex: 1;
      min-width: 140px;
      padding: 0.75rem;
      border: 1px solid #e5e5e5;
      border-radius: 8px;
      background: white;
      font-size: 0.8125rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: all 0.2s ease;
    }

    .action-btn-modern:hover {
      border-color: #000;
      background: #fafafa;
      transform: translateY(-1px);
    }

    .action-btn-modern.primary {
      background: #000;
      color: white;
      border-color: #000;
    }

    .action-btn-modern.primary:hover {
      background: #1a1a1a;
    }

    .action-btn-modern.danger {
      border-color: #e5e5e5;
      color: #000;
    }

    .action-btn-modern.danger:hover {
      border-color: #000;
      background: #000;
      color: white;
    }

    /* Modern Modal */
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

    .modern-empty-state i {
      font-size: 4rem;
      color: #e5e5e5;
      margin-bottom: 1.5rem;
    }

    .modern-empty-state h3 {
      font-size: 1.25rem;
      font-weight: 700;
      color: #000;
      margin-bottom: 0.5rem;
    }

    .modern-empty-state p {
      font-size: 0.9375rem;
      color: #666;
      margin: 0;
    }

    /* Modern Pagination */
    .modern-pagination {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid #e5e5e5;
    }

    .pagination-info-modern {
      font-size: 0.875rem;
      color: #666;
    }

    .pagination-controls-modern {
      display: flex;
      gap: 0.5rem;
    }

    .page-btn-modern {
      width: 40px;
      height: 40px;
      border: 1px solid #e5e5e5;
      border-radius: 8px;
      background: white;
      color: #666;
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .page-btn-modern:hover {
      background: #fafafa;
      border-color: #000;
      color: #000;
    }

    .page-btn-modern.active {
      background: #000;
      color: white;
      border-color: #000;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .filter-grid {
        grid-template-columns: 1fr;
      }

      .payment-cards-container {
        grid-template-columns: 1fr;
      }

      .payment-details-grid {
        grid-template-columns: 1fr;
      }

      .info-grid-modern {
        grid-template-columns: 1fr;
      }

      .modern-stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<div class="dashboard-wrapper">
  <?php include 'include/sidebar.php' ?>

  <main class="main-content payment-dashboard">
    <div class="top-bar">
      <h1 class="page-title">
        <i class="bi bi-credit-card"></i>
        Payment Management
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

    <div class="container-fluid p-4">
      <!-- Payment Type Tabs -->
      <div class="payment-type-tabs" style="margin-bottom: 30px;">
        <a href="?type=regular&status=<?= $statusFilter ?>" 
           class="payment-type-tab <?= $paymentType === 'regular' ? 'active' : '' ?>">
          <i class="bi bi-wallet2"></i>
          <span>Regular Payments</span>
          <?php if ($pendingVerification > 0): ?>
            <span class="tab-badge"><?= $pendingVerification ?></span>
          <?php endif; ?>
        </a>
        <a href="?type=late_fee&status=<?= $statusFilter ?>" 
           class="payment-type-tab <?= $paymentType === 'late_fee' ? 'active' : '' ?>">
          <i class="bi bi-exclamation-triangle"></i>
          <span>Late Fee Payments</span>
          <?php if ($pendingLateFees > 0): ?>
            <span class="tab-badge warning"><?= $pendingLateFees ?></span>
          <?php endif; ?>
        </a>
      </div>

      <!-- Modern Stats Grid -->
      <div class="modern-stats-grid">
        <div class="modern-stat-card">
          <div class="stat-header-modern">
            <div class="stat-icon-modern">
              <i class="bi bi-clock-history"></i>
            </div>
            <span class="stat-badge">Urgent</span>
          </div>
          <div class="stat-value-modern"><?= $paymentType === 'late_fee' ? $pendingLateFees : $pendingVerification ?></div>
          <div class="stat-label-modern"><?= $paymentType === 'late_fee' ? 'Late Fee Pending' : 'Pending Verification' ?></div>
        </div>

        <div class="modern-stat-card">
          <div class="stat-header-modern">
            <div class="stat-icon-modern">
              <i class="bi bi-shield-check"></i>
            </div>
            <span class="stat-badge">Secured</span>
          </div>
          <div class="stat-value-modern">₱<?= number_format($escrowed, 2) ?></div>
          <div class="stat-label-modern">Funds in Escrow</div>
        </div>

        <div class="modern-stat-card">
          <div class="stat-header-modern">
            <div class="stat-icon-modern">
              <i class="bi bi-check-circle-fill"></i>
            </div>
            <span class="stat-badge">This Month</span>
          </div>
          <div class="stat-value-modern"><?= $verifiedThisMonth ?></div>
          <div class="stat-label-modern">Verified Payments</div>
        </div>

        <div class="modern-stat-card">
          <div class="stat-header-modern">
            <div class="stat-icon-modern">
              <span class="currency-symbol">₱</span>
            </div>
            <span class="stat-badge">Revenue</span>
          </div>
          <div class="stat-value-modern">₱<?= number_format($totalPaymentsThisMonth, 2) ?></div>
          <div class="stat-label-modern">Total This Month</div>
        </div>
      </div>

      <!-- Modern Filter Section -->
      <div class="modern-filter-section">
        <div class="filter-grid">
          <div class="modern-search-box">
            <i class="bi bi-search"></i>
            <input type="text" class="modern-search-input" placeholder="Search by reference, customer, or car...">
          </div>
          
          <select class="modern-filter-dropdown" id="statusFilter" onchange="filterPayments()">
            <option value="">All Payments</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
            <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
            <option value="released" <?= $statusFilter === 'released' ? 'selected' : '' ?>>Released</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>

          <button class="modern-btn modern-btn-secondary">
            <i class="bi bi-filter"></i>
            Filter
          </button>

          <button class="modern-btn modern-btn-primary">
            <i class="bi bi-download"></i>
            Export
          </button>
        </div>
      </div>

      <!-- Modern Tab Navigation -->
      <div class="modern-tabs">
        <button class="modern-tab <?= $statusFilter === '' ? 'active' : '' ?>" onclick="filterPayments('')">
          All Payments
          <span class="tab-count"><?= $totalRows ?></span>
        </button>
        <button class="modern-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>" onclick="filterPayments('pending')">
          Pending
          <span class="tab-count"><?= $paymentType === 'late_fee' ? $pendingLateFees : $pendingVerification ?></span>
        </button>
        <button class="modern-tab <?= $statusFilter === 'verified' ? 'active' : '' ?>" onclick="filterPayments('verified')">
          Verified
          <span class="tab-count"><?php 
            if ($paymentType === 'late_fee') {
              echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM late_fee_payments WHERE payment_status='verified'"))['c'];
            } else {
              echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments WHERE payment_status='verified'"))['c'];
            }
          ?></span>
        </button>
        <button class="modern-tab <?= $statusFilter === 'released' ? 'active' : '' ?>" onclick="filterPayments('released')">
          Released
          <span class="tab-count"><?php 
            if ($paymentType === 'late_fee') {
              echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM late_fee_payments WHERE payment_status='released'"))['c'];
            } else {
              echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments WHERE payment_status='released'"))['c'];
            }
          ?></span>
        </button>
      </div>

      <!-- Payment Cards -->
      <?php if (mysqli_num_rows($result) > 0): ?>
      <div class="payment-cards-container">
        <?php 
        mysqli_data_seek($result, 0);
        while ($row = mysqli_fetch_assoc($result)): 
          $paymentId = "#PAY-" . str_pad($row['id'], 4, "0", STR_PAD_LEFT);
          $bookingId = "#BK-" . str_pad($row['booking_id'], 4, "0", STR_PAD_LEFT);
          $vehicleType = ucfirst($row['vehicle_type'] ?? 'car');
          $carName = $row['brand'] . " " . $row['model'] . " " . $row['car_year'];
          
          // Calculate fees dynamically if not set (for pending payments or old data)
          $amount = (float)$row['amount'];
          $platformFee = ($row['platform_fee'] && $row['platform_fee'] > 0) 
                          ? (float)$row['platform_fee'] 
                          : round($amount * 0.10, 2);
          $ownerPayout = ($row['owner_payout'] && $row['owner_payout'] > 0) 
                          ? (float)$row['owner_payout'] 
                          : round($amount - $platformFee, 2);
        ?>
        <div class="modern-payment-card">
          <div class="payment-card-header">
            <div class="payment-id-section">
              <div class="payment-id-modern"><?= $paymentId ?></div>
              <div class="payment-meta"><?= $row['renter_name'] ?></div>
              <div class="payment-meta"><?= $carName ?> <small>(<?= $vehicleType ?>)</small></div>
            </div>
            <div class="payment-amount-section">
              <div class="payment-amount-modern">₱<?= number_format($amount, 2) ?></div>
              <div class="payment-fee-breakdown">
                <div class="fee-item">
                  <span>Platform Fee:</span>
                  <strong>₱<?= number_format($platformFee, 2) ?></strong>
                </div>
                <div class="fee-item">
                  <span>Owner Gets:</span>
                  <strong>₱<?= number_format($ownerPayout, 2) ?></strong>
                </div>
              </div>
            </div>
          </div>

          <div class="payment-details-grid">
            <div class="detail-modern">
              <span class="detail-label-modern">Booking ID</span>
              <span class="detail-value-modern"><?= $bookingId ?></span>
            </div>
            <div class="detail-modern">
              <span class="detail-label-modern">Payment Method</span>
              <span class="detail-value-modern">GCASH</span>

            </div>
            <div class="detail-modern">
              <span class="detail-label-modern">Owner</span>
              <span class="detail-value-modern"><?= $row['owner_name'] ?></span>
            </div>
            <div class="detail-modern">
              <span class="detail-label-modern">Submitted</span>
              <span class="detail-value-modern"><?= date("M d, Y h:i A", strtotime($row['created_at'])) ?></span>
            </div>
          </div>

          <div class="modern-badges">
            <?php
            $statusLabels = [
              'pending' => 'Pending Verification',
              'verified' => 'Verified',
              'released' => 'Released',
              'rejected' => 'Rejected',
              'refunded' => 'Refunded'
            ];
            $statusClass = $row['payment_status'];
            $statusLabel = $statusLabels[$statusClass] ?? ucfirst($statusClass);
            ?>
            <span class="modern-badge <?= $statusClass ?>">
              <?= $statusLabel ?>
            </span>

            <?php if ($row['escrow_status']): ?>
            <span class="modern-badge <?= $row['escrow_status'] ?>">
              <?php if ($row['escrow_status'] === 'held'): ?>
                🔒 Escrow Held
              <?php elseif ($row['escrow_status'] === 'released_to_owner'): ?>
                🔓 Released to Owner
              <?php else: ?>
                <?= ucfirst(str_replace('_', ' ', $row['escrow_status'])) ?>
              <?php endif; ?>
            </span>
            <?php endif; ?>

            <?php if ($row['payout_status']): ?>
            <span class="modern-badge <?= $row['payout_status'] ?>">
              <?php if ($row['payout_status'] === 'pending'): ?>
                ⏳ Payout Pending
              <?php elseif ($row['payout_status'] === 'processing'): ?>
                🔄 Processing
              <?php elseif ($row['payout_status'] === 'completed'): ?>
                ✅ Payout Completed
              <?php else: ?>
                <?= ucfirst($row['payout_status']) ?>
              <?php endif; ?>
            </span>
            <?php endif; ?>
          </div>

          <div class="payment-actions-modern">
            <button class="action-btn-modern" onclick='viewPaymentDetails(<?= json_encode($row) ?>)'>
              <i class="bi bi-eye"></i>
              View Details
            </button>

            <?php if ($row['payment_status'] === 'pending'): ?>
            <button class="action-btn-modern primary" onclick="verifyPayment(<?= $row['id'] ?>, 'verify', '<?= $paymentType ?>')">
              <i class="bi bi-check-circle"></i>
              Verify
            </button>
            <button class="action-btn-modern danger" onclick="verifyPayment(<?= $row['id'] ?>, 'reject', '<?= $paymentType ?>')">
              <i class="bi bi-x-circle"></i>
              Reject
            </button>
            <?php endif; ?>

            <?php 
            // Show info badges for next steps after verification
            if ($row['payment_status'] === 'verified'): 
            ?>
              <?php if ($row['escrow_status'] === 'held' && $row['booking_status'] === 'completed'): ?>
                <span class="modern-badge" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #81c784;">
                  ✓ Ready for escrow release
                </span>
              <?php elseif ($row['escrow_status'] === 'released_to_owner'): ?>
                <span class="modern-badge" style="background: #fff3e0; color: #e65100; border: 1px solid #ffb74d;">
                  → Go to Payouts page to complete
                </span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
      <div class="modern-empty-state">
        <i class="bi bi-inbox"></i>
        <h3>No payments found</h3>
        <p>There are no payment transactions matching your criteria</p>
      </div>
      <?php endif; ?>

      <!-- Modern Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="modern-pagination">
        <div class="pagination-info-modern">
          Showing <strong><?= $offset + 1 ?></strong> - <strong><?= min($offset + $limit, $totalRows) ?></strong>
          of <strong><?= $totalRows ?></strong> payments
        </div>
        <div class="pagination-controls-modern">
          <a href="?page=<?= max(1, $page - 1) ?>&status=<?= $statusFilter ?>&type=<?= $paymentType ?>" class="page-btn-modern">
            <i class="bi bi-chevron-left"></i>
          </a>
          <?php for ($i = 1; $i <= min($totalPages, 5); $i++): ?>
          <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&type=<?= $paymentType ?>" class="page-btn-modern <?= $i == $page ? 'active' : '' ?>">
            <?= $i ?>
          </a>
          <?php endfor; ?>
          <a href="?page=<?= min($totalPages, $page + 1) ?>&status=<?= $statusFilter ?>&type=<?= $paymentType ?>" class="page-btn-modern">
            <i class="bi bi-chevron-right"></i>
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cash-stack"></i> Payment Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalBody">
        <!-- Content will be loaded dynamically -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterPayments(status) {
    const currentType = '<?= $paymentType ?>';
    let url = 'payment.php?';
    
    if (currentType) {
        url += 'type=' + currentType;
    }
    
    if (status) {
        url += (currentType ? '&' : '') + 'status=' + status;
    }
    
    window.location.href = url;
}

function viewPaymentDetails(payment) {
    const modalBody = document.getElementById('modalBody');
    
    // Calculate fees dynamically if not set (for pending payments or old data)
    const amount = parseFloat(payment.amount) || 0;
    const platformFee = (payment.platform_fee && payment.platform_fee > 0) 
                        ? parseFloat(payment.platform_fee) 
                        : Math.round(amount * 0.10 * 100) / 100;
    const ownerPayout = (payment.owner_payout && payment.owner_payout > 0) 
                        ? parseFloat(payment.owner_payout) 
                        : Math.round((amount - platformFee) * 100) / 100;
    
    const escrowBadge = payment.escrow_status ? `<span class="badge bg-warning">${payment.escrow_status === 'held' ? '🔒 Escrow Held' : (payment.escrow_status === 'released_to_owner' ? '🔓 Released to Owner' : payment.escrow_status)}</span>` : '';
    const payoutBadge = payment.payout_status ? `<span class="badge bg-info">${payment.payout_status === 'pending' ? '⏳ Payout Pending' : (payment.payout_status === 'completed' ? '✅ Completed' : payment.payout_status)}</span>` : '';
    
    modalBody.innerHTML = `
      <h6><i class="bi bi-cash"></i> Payment Information</h6>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Payment ID</div>
          <div class="info-value">#PAY-${String(payment.id).padStart(4, '0')}</div>
        </div>
        <div class="info-item">
          <div class="info-label">Total Amount</div>
          <div class="info-value">₱${amount.toFixed(2)}</div>
        </div>
        <div class="info-item">
          <div class="info-label">Payment Method</div>
          <div class="info-value">GCASH</div>
        </div>
        <div class="info-item">
          <div class="info-label">Payment Status</div>
          <div class="info-value"><span class="badge bg-${payment.payment_status === 'verified' ? 'success' : 'warning'}">${payment.payment_status.toUpperCase()}</span></div>
        </div>
      </div>

      <h6><i class="bi bi-calculator"></i> Fee Breakdown</h6>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Platform Fee (10%)</div>
          <div class="info-value">₱${platformFee.toFixed(2)}</div>
        </div>
        <div class="info-item">
          <div class="info-label">Owner Payout</div>
          <div class="info-value">₱${ownerPayout.toFixed(2)}</div>
        </div>
      </div>

      <h6><i class="bi bi-calendar-event"></i> Rental Details</h6>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Booking ID</div>
          <div class="info-value">#BK-${String(payment.booking_id).padStart(4, '0')}</div>
        </div>
        <div class="info-item">
          <div class="info-label">Vehicle</div>
          <div class="info-value">${payment.brand} ${payment.model} ${payment.car_year}</div>
        </div>
        <div class="info-item">
          <div class="info-label">Renter</div>
          <div class="info-value">${payment.renter_name}</div>
        </div>
        <div class="info-item">
          <div class="info-label">Owner</div>
          <div class="info-value">${payment.owner_name}</div>
        </div>
      </div>

      <h6><i class="bi bi-tags"></i> Status Badges</h6>
      <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
        <span class="badge bg-${payment.payment_status === 'verified' ? 'success' : payment.payment_status === 'pending' ? 'warning' : 'danger'}" style="font-size: 14px; padding: 8px 16px;">
          ${payment.payment_status.toUpperCase()}
        </span>
        ${escrowBadge}
        ${payoutBadge}
      </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

// Modal functions removed - using Bootstrap 5 modals now

function submitPayout(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = document.getElementById('payoutSubmitBtn');
    const errorDiv = document.getElementById('payoutError');
    const errorMsg = document.getElementById('payoutErrorMessage');
    
    // Hide previous errors
    errorDiv.style.display = 'none';
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    
    fetch('api/payment/complete_payout.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            // Log the actual response for debugging
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error("Server returned an error. Check browser console for details.");
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            closePayoutModal();
            location.reload();
        } else {
            // Show error in modal
            errorMsg.textContent = data.message;
            errorDiv.style.display = 'flex';
            
            // Re-enable button
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Payout';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        
        // Show error in modal
        errorMsg.textContent = err.message;
        errorDiv.style.display = 'flex';
        
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Payout';
    });
}

function verifyPayment(paymentId, action, paymentType = 'regular') {
    const isLateFee = paymentType === 'late_fee';
    
    const message = action === 'verify' ? 
        (isLateFee ? 'Are you sure you want to verify this late fee payment?' : 'Are you sure you want to verify this payment? Funds will be held in escrow.') : 
        (isLateFee ? 'Are you sure you want to reject this late fee payment?' : 'Are you sure you want to reject this payment? The booking will be cancelled.');
    
    if (!confirm(message)) return;

    const formData = new FormData();
    formData.append('payment_id', paymentId);
    formData.append('action', action);

    // Route to correct API based on payment type
    const apiUrl = isLateFee ? 'api/payment/verify_late_fee_payment.php' : 'api/payment/verify_payment.php';
    
    // For late fee payments, we need to send admin_id and different action names
    if (isLateFee) {
        formData.append('admin_id', <?= $_SESSION['admin_id'] ?? 1 ?>);
        formData.delete('action');
        formData.append('action', action === 'verify' ? 'approve' : 'reject');
        formData.append('notes', action === 'reject' ? 'Payment verification failed' : 'Payment verified successfully');
    }

    fetch(apiUrl, {
        method: 'POST',
        body: formData
    })
    .then(r => {
        // Log the response for debugging
        console.log('Response status:', r.status);
        console.log('Response headers:', r.headers);
        
        // Get the response text first
        return r.text().then(text => {
            console.log('Response text:', text);
            
            // Try to parse as JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response was:', text);
                throw new Error('Server returned invalid JSON. Check console for details.');
            }
        });
    })
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error: ' + err.message);
    });
}

function releaseEscrow(bookingId) {
    if (!confirm('Release escrow and schedule payout to owner?')) return;

    const formData = new FormData();
    formData.append('booking_id', bookingId);

    fetch('api/payment/release_escrow.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error: ' + err.message);
    });
}

// Close modal when clicking outside
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.getElementById('payoutModal').addEventListener('click', function(e) {
    if (e.target === this) closePayoutModal();
});
</script>
<script src="include/notifications.js"></script>
</body>
</html>