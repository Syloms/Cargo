<?php
/**
 * ============================================================================
 * ESCROW MANAGEMENT - CarGo Admin (FIXED)
 * Manage funds held in escrow between payment and payout
 * ============================================================================
 * 
 * ESCROW LIFECYCLE OVERVIEW:
 * --------------------------
 * 1. PAYMENT VERIFIED → Funds enter escrow (escrow_status = 'held')
 * 2. RENTAL PERIOD → Funds remain secured in escrow
 * 3. RENTAL COMPLETED → Ready for release (status = 'completed')
 * 4. RELEASE TO OWNER → Escrow released (escrow_status = 'released_to_owner')
 * 5. PAYOUT PROCESSING → Owner receives payment (payout_status = 'completed')
 * 
 * ESCROW STATUS VALUES:
 * ---------------------
 * - 'pending'            : Payment not yet verified (initial state)
 * - 'held'               : Funds secured in escrow (normal active state)
 * - 'released_to_owner'  : Released and ready for payout
 * - 'released'           : Alternative released state (legacy)
 * - 'refunded'           : Refunded back to renter
 * - 'on_hold' (virtual)  : When escrow_hold_reason is set (not a DB enum value)
 * 
 * PAYOUT STATUS VALUES:
 * ---------------------
 * - 'pending'     : Awaiting payout processing
 * - 'processing'  : Payout in progress
 * - 'completed'   : Payout successfully completed
 * - 'failed'      : Payout failed
 * 
 * REQUIREMENTS FOR ESCROW RELEASE:
 * --------------------------------
 * Before an escrow can be released to the owner, ALL of the following must be true:
 * 
 * ✅ 1. BOOKING STATUS = 'completed'
 *       - The rental period must be finished
 *       - Trip must be properly ended (trip_started = 1, actual_return_date set)
 *       - Cannot release during 'ongoing', 'approved', or 'pending' states
 * 
 * ✅ 2. ESCROW STATUS = 'held'
 *       - Funds must be currently held in escrow
 *       - Cannot release if already 'released_to_owner' or 'refunded'
 * 
 * ✅ 3. NO ACTIVE HOLDS
 *       - escrow_hold_reason must be NULL or empty
 *       - escrow_hold_details must be NULL or empty
 *       - Any disputes/investigations must be resolved first
 * 
 * ✅ 4. OWNER GCASH CONFIGURED
 *       - owner_gcash (GCash number) must be set in users table
 *       - Cannot process payout without payment method
 *       - Display warning badge if missing
 * 
 * ✅ 5. NO OUTSTANDING ISSUES (Recommended checks)
 *       - No active insurance claims on the booking
 *       - No unresolved damage reports
 *       - Late fees settled (if applicable)
 *       - Mileage verification completed (if enabled)
 *       - No active disputes or complaints
 * 
 * ✅ 6. PAYMENT VERIFIED
 *       - payment_verified_at should be set
 *       - Payment status should be 'verified' or 'completed'
 *       - Original payment must have cleared
 * 
 * ✅ 7. SUFFICIENT HOLDING PERIOD (Auto-release)
 *       - For auto-release: 3 days after return_date
 *       - Manual release can be done immediately after completion
 *       - Provides buffer for any issues to surface
 * 
 * ESCROW ACTIONS AVAILABLE:
 * -------------------------
 * 
 * 📤 RELEASE TO OWNER
 *    - API: api/escrow/release_escrow.php
 *    - Sets escrow_status = 'released_to_owner'
 *    - Sets escrow_released_at = NOW()
 *    - Creates payout record or schedules payout
 *    - Sends notification to owner
 *    - Requires all release conditions above
 * 
 * ⏸️ PUT ON HOLD
 *    - API: api/escrow/hold_escrow.php
 *    - Sets escrow_hold_reason (keeps escrow_status = 'held')
 *    - Sets escrow_hold_details
 *    - Prevents release until resolved
 *    - Used for: disputes, investigations, complaints, damage claims
 *    - Notifies both renter and owner
 * 
 * ▶️ RESUME ESCROW
 *    - API: api/escrow/resume_escrow.php
 *    - Clears escrow_hold_reason and escrow_hold_details
 *    - Returns escrow to normal 'held' state
 *    - Can then be released normally
 * 
 * ↩️ REFUND TO RENTER
 *    - API: api/escrow/refund_escrow.php
 *    - Sets escrow_status = 'refunded'
 *    - Sets booking status = 'cancelled'
 *    - Creates refund record in refunds table
 *    - Updates payment status to 'refunded'
 *    - Cannot be undone - permanent action
 * 
 * AUTOMATED PROCESSES:
 * --------------------
 * 
 * 🤖 AUTO-RELEASE CRON JOB
 *    - File: cron/auto_release_escrow.php
 *    - Runs: Daily (recommended: 0 0 * * *)
 *    - Releases escrow for completed bookings after 3 days
 *    - Only if all release requirements are met
 *    - Logs all actions to escrow_logs table
 * 
 * DATABASE TABLES INVOLVED:
 * -------------------------
 * - bookings: Main escrow status and amounts
 * - payments: Payment verification and references
 * - payouts: Payout tracking and completion
 * - refunds: Refund processing records
 * - escrow_logs: Audit trail of all escrow actions (if exists)
 * - notifications: User notifications for escrow events
 * - users: Owner GCash details for payouts
 * 
 * IMPORTANT FIELDS IN BOOKINGS TABLE:
 * ------------------------------------
 * - escrow_status: Current escrow state (ENUM)
 * - escrow_held_at: When funds entered escrow
 * - escrow_released_at: When released to owner
 * - escrow_refunded_at: When refunded to renter
 * - escrow_hold_reason: Why on hold (NULL = not on hold)
 * - escrow_hold_details: Detailed explanation of hold
 * - owner_payout: Amount to release to owner (after platform fee)
 * - platform_fee: 10% fee retained by platform
 * - total_amount: Original payment from renter
 * - payout_status: Current payout state (ENUM)
 * - payment_verified_at: When payment was verified
 * - status: Booking status (affects release eligibility)
 * 
 * ESCROW AMOUNT CALCULATION:
 * --------------------------
 * Total Amount (Renter Pays) = Rental Price + Insurance + Extras
 * Platform Fee (10%) = Total Amount × 0.10
 * Owner Payout (Escrow) = Total Amount - Platform Fee
 * 
 * Example:
 * - Total Amount: ₱5,000
 * - Platform Fee: ₱500 (10%)
 * - Owner Payout: ₱4,500 (held in escrow)
 * 
 * SECURITY & COMPLIANCE:
 * ----------------------
 * - All escrow actions require admin authentication
 * - All actions are logged to escrow_logs (audit trail)
 * - Transaction logging via TransactionLogger
 * - Database transactions ensure data integrity
 * - Rollback on any errors to prevent partial updates
 * - Notifications sent to affected parties
 * 
 * TROUBLESHOOTING:
 * ----------------
 * Q: Why can't I release an escrow?
 * A: Check all 7 requirements above. Most common issues:
 *    - Booking not 'completed' yet
 *    - Owner GCash not configured
 *    - Escrow on hold (check escrow_hold_reason)
 * 
 * Q: What if owner doesn't have GCash?
 * A: Release button will show warning. Owner must add GCash in profile.
 * 
 * Q: Can I undo a release?
 * A: No - once released, payout process begins. Cannot reverse.
 * 
 * Q: Can I undo a refund?
 * A: No - refunds are permanent and cannot be reversed.
 * 
 * ============================================================================
 */

session_start();
require_once 'include/db.php';
require_once 'include/admin_profile.php';

// Auth check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   FIRST: CHECK IF escrow_logs TABLE EXISTS
   ========================================================= */
$checkLogsTable = mysqli_query($conn, "SHOW TABLES LIKE 'escrow_logs'");
$logsTableExists = mysqli_num_rows($checkLogsTable) > 0;

/* =========================================================
   ESCROW STATISTICS WITH ERROR HANDLING
   ========================================================= */

// Total funds currently held in escrow
$fundsQuery = "SELECT COALESCE(SUM(owner_payout), 0) AS total 
               FROM bookings 
               WHERE escrow_status = 'held' AND owner_payout > 0";
$fundsResult = mysqli_query($conn, $fundsQuery);
if (!$fundsResult) {
    error_log("Funds query error: " . mysqli_error($conn));
    $fundsInEscrow = 0;
} else {
    $fundsInEscrow = mysqli_fetch_assoc($fundsResult)['total'];
}

// Pending releases (completed bookings ready for release)
$pendingQuery = "SELECT COUNT(*) AS c FROM bookings 
                 WHERE status = 'completed' 
                 AND escrow_status = 'held'";
$pendingResult = mysqli_query($conn, $pendingQuery);
if (!$pendingResult) {
    error_log("Pending releases query error: " . mysqli_error($conn));
    $pendingReleases = 0;
} else {
    $pendingReleases = mysqli_fetch_assoc($pendingResult)['c'];
}

// Released this month
$releasedQuery = "SELECT COALESCE(SUM(owner_payout), 0) AS total 
                  FROM bookings 
                  WHERE escrow_status IN ('released_to_owner', 'released')
                  AND escrow_released_at IS NOT NULL
                  AND MONTH(escrow_released_at) = MONTH(NOW())
                  AND YEAR(escrow_released_at) = YEAR(NOW())";
$releasedResult = mysqli_query($conn, $releasedQuery);
if (!$releasedResult) {
    error_log("Released query error: " . mysqli_error($conn));
    $releasedThisMonth = 0;
} else {
    $releasedThisMonth = mysqli_fetch_assoc($releasedResult)['total'];
}

// Disputed/On Hold - Since 'on_hold' doesn't exist in enum, check escrow_hold_reason instead
$disputedQuery = "SELECT COUNT(*) AS c FROM bookings 
                  WHERE escrow_hold_reason IS NOT NULL 
                  AND escrow_hold_reason != ''
                  AND escrow_status = 'held'";
$disputedResult = mysqli_query($conn, $disputedQuery);
if (!$disputedResult) {
    error_log("Disputed query error: " . mysqli_error($conn));
    $disputedCount = 0;
} else {
    $disputedCount = mysqli_fetch_assoc($disputedResult)['c'];
}

// Average escrow duration (days between payment verification and release)
$avgQuery = "SELECT AVG(DATEDIFF(COALESCE(escrow_released_at, NOW()), 
                                  COALESCE(payment_verified_at, created_at))) AS avg_days
             FROM bookings
             WHERE escrow_status IN ('released_to_owner', 'released')
             AND escrow_released_at IS NOT NULL";
$avgResult = mysqli_query($conn, $avgQuery);
if (!$avgResult) {
    error_log("Average duration query error: " . mysqli_error($conn));
    $avgDuration = 0;
} else {
    $avgRow = mysqli_fetch_assoc($avgResult);
    $avgDuration = $avgRow['avg_days'] ?? 0;
}

/* =========================================================
   FILTERS & PAGINATION
   ========================================================= */
$limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 10;
$page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$offset = ($page - 1) * $limit;

$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'held';
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'priority';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$minAmount = isset($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$maxAmount = isset($_GET['max_amount']) ? floatval($_GET['max_amount']) : 0;

/* =========================================================
   BUILD WHERE CLAUSE - FIXED FOR ACTUAL ENUM VALUES
   ========================================================= */
$where = " WHERE 1 ";

// Status filter - using actual enum values from database
switch($statusFilter) {
    case 'held':
        $where .= " AND b.escrow_status = 'held' AND (b.escrow_hold_reason IS NULL OR b.escrow_hold_reason = '') ";
        break;
    case 'released':
        $where .= " AND b.escrow_status IN ('released_to_owner', 'released') ";
        break;
    case 'on_hold':
        // Since 'on_hold' is not in enum, we check for hold reason
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

// Search filter
if (!empty($search)) {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (
        b.id LIKE '%$searchEsc%' OR
        u_renter.fullname LIKE '%$searchEsc%' OR
        u_owner.fullname LIKE '%$searchEsc%' OR
        COALESCE(c.brand, m.brand) LIKE '%$searchEsc%'
    )";
}

// Date range filter
if (!empty($dateFrom)) {
    $dateFromEsc = mysqli_real_escape_string($conn, $dateFrom);
    $where .= " AND DATE(b.created_at) >= '$dateFromEsc' ";
}

if (!empty($dateTo)) {
    $dateToEsc = mysqli_real_escape_string($conn, $dateTo);
    $where .= " AND DATE(b.created_at) <= '$dateToEsc' ";
}

// Amount range filter
if ($minAmount > 0) {
    $where .= " AND b.owner_payout >= $minAmount ";
}

if ($maxAmount > 0) {
    $where .= " AND b.owner_payout <= $maxAmount ";
}

/* =========================================================
   MAIN QUERY - ESCROW TRANSACTIONS
   ========================================================= */
$sql = "
    SELECT 
        b.id AS booking_id,
        b.user_id AS renter_id,
        b.owner_id,
        b.car_id,
        b.total_amount,
        b.platform_fee,
        b.owner_payout,
        b.status AS booking_status,
        b.escrow_status,
        b.pickup_date,
        b.return_date,
        b.created_at,
        b.escrow_released_at,
        b.escrow_held_at,
        b.payment_verified_at,
        b.vehicle_type,
        b.escrow_hold_reason,
        b.escrow_hold_details,
        
        -- Calculate days in escrow
        CASE 
            WHEN b.escrow_status = 'held' THEN 
                DATEDIFF(NOW(), COALESCE(b.escrow_held_at, b.payment_verified_at, b.created_at))
            WHEN b.escrow_status IN ('released_to_owner', 'released') THEN 
                DATEDIFF(b.escrow_released_at, COALESCE(b.escrow_held_at, b.payment_verified_at, b.created_at))
            ELSE 0
        END AS days_in_escrow,
        
        -- Renter info
        u_renter.fullname AS renter_name,
        u_renter.email AS renter_email,
        u_renter.phone AS renter_phone,
        
        -- Owner info
        u_owner.fullname AS owner_name,
        u_owner.email AS owner_email,
        u_owner.gcash_number AS owner_gcash,
        u_owner.gcash_name AS owner_gcash_name,
        
        -- Vehicle info (supports both cars and motorcycles)
        COALESCE(c.brand, m.brand) AS brand,
        COALESCE(c.model, m.model) AS model,
        COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
        COALESCE(c.plate_number, m.plate_number) AS plate_number,
        COALESCE(c.image, m.image) AS vehicle_image,
        
        -- Payment info
        p.payment_status,
        p.payment_method,
        p.payment_reference,
        p.created_at AS payment_date,
        
        -- Payout status
        b.payout_status
        
    FROM bookings b
    JOIN users u_renter ON b.user_id = u_renter.id
    JOIN users u_owner ON b.owner_id = u_owner.id
    LEFT JOIN payments p ON b.id = p.booking_id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    $where
    ORDER BY 
        CASE 
            WHEN '$sortBy' = 'priority' THEN
                CASE 
                    -- Priority 1: Items on hold (have hold reason)
                    WHEN b.escrow_hold_reason IS NOT NULL AND b.escrow_hold_reason != '' THEN 1
                    -- Priority 2: Completed bookings ready to release
                    WHEN b.escrow_status = 'held' AND b.status = 'completed' THEN 2
                    -- Priority 3: Other held escrow
                    WHEN b.escrow_status = 'held' THEN 3
                    -- Everything else
                    ELSE 4
                END
            ELSE 0
        END,
        CASE '$sortBy'
            WHEN 'date_asc' THEN b.created_at
            WHEN 'amount_asc' THEN b.owner_payout
            ELSE NULL
        END ASC,
        CASE '$sortBy'
            WHEN 'date_desc' THEN b.created_at
            WHEN 'amount_desc' THEN b.owner_payout
            WHEN 'days_desc' THEN DATEDIFF(NOW(), COALESCE(b.escrow_held_at, b.payment_verified_at, b.created_at))
            WHEN 'days_asc' THEN DATEDIFF(NOW(), COALESCE(b.escrow_held_at, b.payment_verified_at, b.created_at))
            ELSE b.created_at
        END DESC,
        CASE '$sortBy'
            WHEN 'renter_asc' THEN u_renter.fullname
            WHEN 'owner_asc' THEN u_owner.fullname
            ELSE NULL
        END ASC,
        CASE '$sortBy'
            WHEN 'renter_desc' THEN u_renter.fullname
            WHEN 'owner_desc' THEN u_owner.fullname
            ELSE NULL
        END DESC
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Main query SQL ERROR: " . mysqli_error($conn) . "<br><br>Query: " . $sql);
}

/* =========================================================
   COUNT FOR PAGINATION
   ========================================================= */
$countSql = "SELECT COUNT(*) AS total 
FROM bookings b 
JOIN users u_renter ON b.user_id = u_renter.id
LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
$where";

$countRes = mysqli_query($conn, $countSql);
if (!$countRes) {
    die("Count query error: " . mysqli_error($conn));
}
$totalRows = mysqli_fetch_assoc($countRes)['total'];
$totalPages = max(1, ceil($totalRows / $limit));

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Escrow Management - CarGo Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="include/admin-styles.css" rel="stylesheet">
  <link href="include/modal-theme-standardized.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="include/notifications.css" rel="stylesheet">
  <style>
    /* Additional escrow-specific styles */
    .escrow-timeline {
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
      margin-top: 20px;
    }
    
    .timeline-item {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #e0e0e0;
    }
    
    .timeline-item:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }
    
    .timeline-icon {
      width: 40px;
      height: 40px;
      background: #1a1a1a;
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    
    .timeline-content {
      flex: 1;
    }
    
    .timeline-title {
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 4px;
    }
    
    .timeline-date {
      font-size: 12px;
      color: #999;
    }
    
    .escrow-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .escrow-badge.held {
      background: #fff3cd;
      color: #856404;
    }
    
    .escrow-badge.released {
      background: #d4edda;
      color: #155724;
    }
    
    .escrow-badge.on-hold {
      background: #f8d7da;
      color: #721c24;
    }
    
    .escrow-badge.refunded {
      background: #d1ecf1;
      color: #0c5460;
    }
    
    .urgency-high {
      color: #dc3545;
      font-weight: 700;
    }
    
    .urgency-medium {
      color: #ffc107;
      font-weight: 600;
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
        <i class="bi bi-shield-lock"></i>
        Escrow Management
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
          <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <i class="bi bi-shield-check"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-lock"></i>
            Secured
          </div>
        </div>
        <div class="stat-value">₱<?= number_format($fundsInEscrow, 2) ?></div>
        <div class="stat-label">Funds in Escrow</div>
        <div class="stat-detail">Currently held</div>
      </div>

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
        <div class="stat-value"><?= $pendingReleases ?></div>
        <div class="stat-label">Pending Releases</div>
        <div class="stat-detail">
          <strong>Ready to release:</strong><br>
          <small style="font-size: 11px;">
            ✅ Booking completed<br>
            ✅ Escrow held<br>
            ✅ No holds<br>
            ✅ GCash configured
          </small>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <i class="bi bi-check-circle"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            +12%
          </div>
        </div>
        <div class="stat-value">₱<?= number_format($releasedThisMonth, 2) ?></div>
        <div class="stat-label">Released This Month</div>
        <div class="stat-detail">To owners</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <i class="bi bi-hourglass-split"></i>
          </div>
          <div class="stat-trend">
            <?php if ($disputedCount > 0): ?>
            <i class="bi bi-exclamation-triangle"></i>
            <?= $disputedCount ?>
            <?php else: ?>
            <i class="bi bi-check"></i>
            Clear
            <?php endif; ?>
          </div>
        </div>
        <div class="stat-value"><?= round($avgDuration, 1) ?> days</div>
        <div class="stat-label">Avg. Escrow Duration</div>
        <div class="stat-detail">Release time</div>
      </div>
    </div>
    
    <!-- ESCROW RELEASE REQUIREMENTS INFO BOX -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <div style="display: flex; align-items: flex-start; gap: 20px;">
        <div style="flex-shrink: 0;">
          <i class="bi bi-info-circle" style="font-size: 48px; opacity: 0.9;"></i>
        </div>
        <div style="flex: 1;">
          <h5 style="margin: 0 0 15px 0; font-weight: 700;">📋 Requirements for Escrow Release</h5>
          <p style="margin: 0 0 15px 0; opacity: 0.95; font-size: 14px;">
            Before releasing escrow funds to the owner, ensure ALL of the following conditions are met:
          </p>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px;">
            <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
              <strong style="display: block; margin-bottom: 6px;">✅ 1. Booking Completed</strong>
              <small style="opacity: 0.9;">Rental period finished, trip ended properly</small>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
              <strong style="display: block; margin-bottom: 6px;">✅ 2. Escrow Status = Held</strong>
              <small style="opacity: 0.9;">Funds currently secured in escrow</small>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
              <strong style="display: block; margin-bottom: 6px;">✅ 3. No Active Holds</strong>
              <small style="opacity: 0.9;">No disputes or investigations pending</small>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
              <strong style="display: block; margin-bottom: 6px;">✅ 4. GCash Configured</strong>
              <small style="opacity: 0.9;">Owner has valid GCash number in profile</small>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
              <strong style="display: block; margin-bottom: 6px;">✅ 5. Payment Verified</strong>
              <small style="opacity: 0.9;">Original payment has cleared successfully</small>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
              <strong style="display: block; margin-bottom: 6px;">✅ 6. No Outstanding Issues</strong>
              <small style="opacity: 0.9;">Claims, damages, late fees resolved</small>
            </div>
          </div>
          <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 6px; border-left: 4px solid #ffc107;">
            <strong>⚡ Auto-Release:</strong> 
            <small style="opacity: 0.95;">Escrows are automatically released 3 days after rental completion if all requirements are met. Manual release can be done immediately.</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
      <form method="GET" id="filterForm">
        <div class="filter-row">
          <div class="search-box">
            <input type="text" name="search" id="searchInput" 
                   placeholder="Search by booking, renter, owner, or vehicle..." 
                   value="<?= htmlspecialchars($search) ?>">
            <i class="bi bi-search"></i>
          </div>

          <select name="status" class="filter-dropdown" onchange="this.form.submit()">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Escrow</option>
            <option value="held" <?= $statusFilter === 'held' ? 'selected' : '' ?>>Currently Held</option>
            <option value="pending_release" <?= $statusFilter === 'pending_release' ? 'selected' : '' ?>>Ready to Release</option>
            <option value="on_hold" <?= $statusFilter === 'on_hold' ? 'selected' : '' ?>>On Hold / Disputed</option>
            <option value="released" <?= $statusFilter === 'released' ? 'selected' : '' ?>>Released</option>
            <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
          </select>

          <select name="sort" class="filter-dropdown" onchange="this.form.submit()">
            <option value="priority" <?= $sortBy === 'priority' ? 'selected' : '' ?>>🎯 By Priority</option>
            <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>📅 Newest First</option>
            <option value="date_asc" <?= $sortBy === 'date_asc' ? 'selected' : '' ?>>📅 Oldest First</option>
            <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>💰 Highest Amount</option>
            <option value="amount_asc" <?= $sortBy === 'amount_asc' ? 'selected' : '' ?>>💰 Lowest Amount</option>
            <option value="days_desc" <?= $sortBy === 'days_desc' ? 'selected' : '' ?>>⏱️ Longest in Escrow</option>
            <option value="days_asc" <?= $sortBy === 'days_asc' ? 'selected' : '' ?>>⏱️ Shortest in Escrow</option>
            <option value="renter_asc" <?= $sortBy === 'renter_asc' ? 'selected' : '' ?>>👤 Renter (A-Z)</option>
            <option value="owner_asc" <?= $sortBy === 'owner_asc' ? 'selected' : '' ?>>🏢 Owner (A-Z)</option>
          </select>

          <button type="button" class="add-user-btn" onclick="toggleAdvancedFilters()" id="advancedFilterBtn">
            <i class="bi bi-funnel"></i>
            Advanced
          </button>

          <button type="button" class="add-user-btn" onclick="exportEscrow()">
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

    <!-- Escrow Table -->
    <div class="table-section">
      <div class="section-header">
        <h2 class="section-title">Escrow Transactions</h2>
        <div class="table-controls">
          <a href="?status=held" class="table-btn <?= $statusFilter === 'held' ? 'active' : '' ?>">
            Currently Held
          </a>
          <a href="?status=pending_release" class="table-btn <?= $statusFilter === 'pending_release' ? 'active' : '' ?>">
            Pending Release (<?= $pendingReleases ?>)
          </a>
          <a href="?status=on_hold" class="table-btn <?= $statusFilter === 'on_hold' ? 'active' : '' ?>">
            On Hold <?= $disputedCount > 0 ? "($disputedCount)" : '' ?>
          </a>
        </div>
      </div>

      <div class="table-responsive">
        <?php if (mysqli_num_rows($result) == 0): ?>
        <div style="padding: 60px 20px; text-center;">
          <i class="bi bi-inbox" style="font-size: 64px; color: #ddd;"></i>
          <p style="margin-top: 20px; color: #999; font-size: 16px;">No escrow transactions found</p>
        </div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Renter</th>
              <th>Owner</th>
              <th>Vehicle</th>
              <th>Escrow Amount</th>
              <th>Booking Status</th>
              <th>Escrow Status</th>
              <th>Days in Escrow</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): 
              $bookingId = '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT);
              $vehicleName = htmlspecialchars($row['brand'] . ' ' . $row['model'] . ' ' . $row['vehicle_year']);
              $daysInEscrow = intval($row['days_in_escrow']);
              
              // Determine if this is "on hold" (has hold reason)
              $isOnHold = !empty($row['escrow_hold_reason']);
              $actualEscrowStatus = $isOnHold ? 'on_hold' : $row['escrow_status'];
              
              // Determine urgency class
              $urgencyClass = '';
              if ($actualEscrowStatus === 'held' && $row['booking_status'] === 'completed') {
                if ($daysInEscrow > 7) {
                  $urgencyClass = 'urgency-high';
                } elseif ($daysInEscrow > 3) {
                  $urgencyClass = 'urgency-medium';
                }
              }
              
              // Status badge mapping - FIXED
              $escrowStatusMap = [
                'held' => 'held',
                'released_to_owner' => 'released',
                'released' => 'released',
                'on_hold' => 'on-hold',
                'refunded' => 'refunded',
                'pending' => 'held'
              ];
              $escrowBadgeClass = $escrowStatusMap[$actualEscrowStatus] ?? 'held';
              
              $bookingStatusClass = [
                'completed' => 'verified',
                'ongoing' => 'pending',
                'approved' => 'pending',
                'cancelled' => 'rejected'
              ][$row['booking_status']] ?? 'pending';
            ?>
            <tr>
              <td>
                <strong><?= $bookingId ?></strong><br>
                <small style="color:#999;"><?= date('M d, Y', strtotime($row['created_at'])) ?></small>
              </td>

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

              <td>
                <div class="user-cell">
                  <div class="user-avatar-small">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['owner_name']) ?>&background=1a1a1a&color=fff">
                  </div>
                  <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($row['owner_name']) ?></span>
                    <span class="user-email"><?= htmlspecialchars($row['owner_email']) ?></span>
                  </div>
                </div>
              </td>

              <td>
                <strong><?= $vehicleName ?></strong><br>
                <small style="color:#999;"><?= htmlspecialchars($row['plate_number']) ?></small>
              </td>

              <td>
                <div style="font-size: 14px;">
                  <strong style="color: #000; font-size: 16px;">₱<?= number_format($row['owner_payout'], 2) ?></strong><br>
                  <small style="color: #999;">
                    Total: ₱<?= number_format($row['total_amount'], 2) ?><br>
                    Fee: ₱<?= number_format($row['platform_fee'], 2) ?>
                  </small>
                </div>
              </td>

              <td>
                <span class="status-badge <?= $bookingStatusClass ?>">
                  <?= ucfirst($row['booking_status']) ?>
                </span>
                <?php if ($row['booking_status'] === 'completed' && $actualEscrowStatus === 'held' && !$isOnHold): ?>
                <br><small style="color: #28a745; font-weight: 600;">
                  ✓ Ready for release
                  <?php if (empty($row['owner_gcash'])): ?>
                  <br><span style="color: #dc3545;">⚠️ GCash needed</span>
                  <?php endif; ?>
                </small>
                <?php endif; ?>
              </td>

              <td>
                <span class="escrow-badge <?= $escrowBadgeClass ?>">
                  <?php
                  $statusIcons = [
                    'held' => '🔒',
                    'released_to_owner' => '✅',
                    'released' => '✅',
                    'on_hold' => '⚠️',
                    'refunded' => '↩️',
                    'pending' => '⏳'
                  ];
                  echo $statusIcons[$actualEscrowStatus] ?? '🔒';
                  ?>
                  <?= ucfirst(str_replace('_', ' ', $actualEscrowStatus)) ?>
                </span>
                <?php if ($isOnHold): ?>
                <br><small style="color: #dc3545;">Reason: <?= htmlspecialchars($row['escrow_hold_reason']) ?></small>
                <?php endif; ?>
              </td>

              <td>
                <span class="<?= $urgencyClass ?>">
                  <?= $daysInEscrow ?> day<?= $daysInEscrow != 1 ? 's' : '' ?>
                </span>
              </td>

              <td>
                <div class="action-buttons">
                  <button class="action-btn view" onclick='viewEscrowDetails(<?= json_encode($row) ?>)' title="View Details">
                    <i class="bi bi-eye"></i>
                  </button>

                  <?php 
                  // ============================================================================
                  // RELEASE TO OWNER BUTTON - VALIDATION LOGIC
                  // ============================================================================
                  // Show "Release to Owner" button ONLY when ALL conditions are met:
                  //
                  // ✅ REQUIREMENT 1: Booking status is 'completed'
                  //    - Rental period must be finished
                  //    - Trip properly ended with actual_return_date set
                  //
                  // ✅ REQUIREMENT 2: Escrow status is 'held'
                  //    - Funds currently secured in escrow
                  //    - Not already released or refunded
                  //
                  // ✅ REQUIREMENT 3: Not currently on hold
                  //    - No active disputes or investigations
                  //    - escrow_hold_reason must be NULL/empty
                  //
                  // ✅ REQUIREMENT 4: Owner has GCash configured
                  //    - owner_gcash field must be set in users table
                  //    - Required for payout processing
                  //
                  // Additional recommended checks (handled by backend API):
                  // - Payment verified (payment_verified_at set)
                  // - No active insurance claims
                  // - Late fees settled (if applicable)
                  // - Mileage verification completed (if enabled)
                  // ============================================================================
                  
                  $canRelease = (
                    $row['booking_status'] === 'completed' && 
                    $actualEscrowStatus === 'held' && 
                    !$isOnHold &&
                    !empty($row['owner_gcash'])
                  );
                  
                  if ($canRelease): 
                  ?>
                  <button class="action-btn approve" onclick="releaseEscrow(<?= $row['booking_id'] ?>)" title="✅ All requirements met - Release to Owner">
                    <i class="bi bi-unlock"></i>
                  </button>
                  <?php elseif ($actualEscrowStatus === 'held' && $row['booking_status'] === 'completed' && !$isOnHold): ?>
                    <!-- Booking completed but owner GCash missing -->
                    <span class="badge bg-danger" style="font-size: 11px;" title="Cannot release: Owner must configure GCash number in profile settings">
                      ⚠️ Owner GCash not set
                    </span>
                  <?php elseif ($actualEscrowStatus === 'held' && $row['booking_status'] !== 'completed'): ?>
                    <!-- Booking not completed yet -->
                    <span class="badge bg-warning" style="font-size: 11px;" title="Cannot release: Booking must be completed first (current: <?= $row['booking_status'] ?>)">
                      ⏳ Waiting for completion
                    </span>
                  <?php endif; ?>

                  <?php if ($actualEscrowStatus === 'held' && !$isOnHold): ?>
                  <button class="action-btn edit" onclick="holdEscrow(<?= $row['booking_id'] ?>)" title="Put On Hold">
                    <i class="bi bi-pause-circle"></i>
                  </button>
                  <button class="action-btn reject" onclick="refundEscrow(<?= $row['booking_id'] ?>)" title="Refund to Renter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                  <?php endif; ?>

                  <?php if ($isOnHold): ?>
                  <button class="action-btn approve" onclick="resumeEscrow(<?= $row['booking_id'] ?>)" title="Resume Escrow">
                    <i class="bi bi-play-circle"></i>
                  </button>
                  <button class="action-btn reject" onclick="refundEscrow(<?= $row['booking_id'] ?>)" title="Refund to Renter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                  <?php endif; ?>
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
          'search' => $search,
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
          of <strong><?= $totalRows ?></strong> transaction<?= $totalRows != 1 ? 's' : '' ?>
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

          <?php 
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);
          for ($i = $startPage; $i <= $endPage; $i++): 
            $pageParams = $queryParams;
            $pageParams['page'] = $i;
          ?>
          <a href="?<?= http_build_query($pageParams) ?>" 
             class="page-btn <?= $i === $page ? 'active' : '' ?>">
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

<!-- Escrow Details Modal -->
<div class="modal fade" id="escrowModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" id="escrowModalContent">
      <!-- Loaded dynamically -->
    </div>
  </div>
</div>

<!-- Hold Escrow Modal -->
<div class="modal fade" id="holdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-warning">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-pause-circle"></i> Put Escrow On Hold</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="hold_booking_id">
        
        <div class="mb-3">
          <label class="form-label">Reason for Hold *</label>
          <select class="form-control" id="hold_reason" required>
            <option value="">Select reason...</option>
            <option value="dispute">Dispute between parties</option>
            <option value="investigation">Under investigation</option>
            <option value="complaint">Customer complaint</option>
            <option value="damage">Vehicle damage claim</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Details *</label>
          <textarea class="form-control" id="hold_details" rows="3" required 
                    placeholder="Provide detailed reason for putting escrow on hold..."></textarea>
        </div>

        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i>
          This will freeze the escrow funds until the issue is resolved. Both renter and owner will be notified.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" onclick="submitHold()">
          <i class="bi bi-pause-circle"></i> Put On Hold
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Refund Escrow Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-danger">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-undo-alt"></i> Refund Escrow to Renter</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="refund_booking_id">
        
        <div class="mb-3">
          <label class="form-label">Refund Reason *</label>
          <select class="form-control" id="refund_reason" required>
            <option value="">Select reason...</option>
            <option value="cancelled_by_owner">Cancelled by owner</option>
            <option value="car_unavailable">Car unavailable</option>
            <option value="booking_error">Booking error</option>
            <option value="customer_request">Customer request</option>
            <option value="dispute_resolution">Dispute resolution</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Refund Details *</label>
          <textarea class="form-control" id="refund_details" rows="3" required 
                    placeholder="Explain why the escrow is being refunded..."></textarea>
        </div>

        <div class="alert alert-danger">
          <i class="bi bi-exclamation-circle"></i>
          <strong>Warning:</strong> This will return funds to the renter and cancel the booking. This action cannot be undone.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="submitRefund()">
          <i class="bi bi-arrow-counterclockwise"></i> Process Refund
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// VIEW ESCROW DETAILS
function viewEscrowDetails(escrow) {
  const vehicleName = `${escrow.brand} ${escrow.model} ${escrow.vehicle_year}`;
  const bookingId = '#BK-' + String(escrow.booking_id).padStart(4, '0');
  
  // Determine actual escrow status
  const isOnHold = escrow.escrow_hold_reason && escrow.escrow_hold_reason.trim() !== '';
  const actualEscrowStatus = isOnHold ? 'on_hold' : escrow.escrow_status;
  
  const escrowStatusMap = {
    'held': { icon: '🔒', label: 'Held in Escrow', class: 'warning' },
    'released_to_owner': { icon: '✅', label: 'Released to Owner', class: 'success' },
    'released': { icon: '✅', label: 'Released to Owner', class: 'success' },
    'on_hold': { icon: '⚠️', label: 'On Hold', class: 'danger' },
    'refunded': { icon: '↩️', label: 'Refunded to Renter', class: 'info' }
  };
  
  const status = escrowStatusMap[actualEscrowStatus] || { icon: '❓', label: 'Unknown', class: 'secondary' };
  
  document.getElementById('escrowModalContent').innerHTML = `
    <div class="modal-header">
      <h5 class="modal-title">
        <i class="bi bi-shield-lock"></i>
        Escrow Details - ${bookingId}
      </h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">
      <!-- Status Alert -->
      <div class="alert alert-${status.class}">
        <h6 style="margin: 0;">${status.icon} ${status.label}</h6>
        <small>Days in escrow: <strong>${escrow.days_in_escrow}</strong></small>
        ${isOnHold ? `<br><small><strong>Hold Reason:</strong> ${escrow.escrow_hold_reason}</small>` : ''}
      </div>

      <!-- Escrow Amount Breakdown -->
      <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h6 style="margin-bottom: 15px;">Escrow Amount Breakdown</h6>
        <table class="table table-sm mb-0">
          <tr>
            <td>Total Booking Amount</td>
            <td class="text-end"><strong>₱${parseFloat(escrow.total_amount).toLocaleString()}</strong></td>
          </tr>
          <tr>
            <td>Platform Fee (10%)</td>
            <td class="text-end text-danger">-₱${parseFloat(escrow.platform_fee).toLocaleString()}</td>
          </tr>
          <tr class="table-success">
            <td><strong>Owner Payout (In Escrow)</strong></td>
            <td class="text-end"><strong>₱${parseFloat(escrow.owner_payout).toLocaleString()}</strong></td>
          </tr>
        </table>
      </div>

      <!-- Parties Information -->
      <div class="row mb-3">
        <div class="col-md-6">
          <h6>Renter</h6>
          <p>
            <strong>${escrow.renter_name}</strong><br>
            ${escrow.renter_email}<br>
            ${escrow.renter_phone || 'N/A'}
          </p>
        </div>
        <div class="col-md-6">
          <h6>Owner</h6>
          <p>
            <strong>${escrow.owner_name}</strong><br>
            ${escrow.owner_email}<br>
            ${escrow.owner_gcash ? `GCash: ${escrow.owner_gcash}` : 'GCash not set'}
          </p>
        </div>
      </div>

      <!-- Booking Information -->
      <h6>Booking Details</h6>
      <p>
        <strong>Vehicle:</strong> ${vehicleName}<br>
        <strong>Plate:</strong> ${escrow.plate_number}<br>
        <strong>Rental Period:</strong> ${escrow.pickup_date} to ${escrow.return_date}<br>
        <strong>Booking Status:</strong> <span class="badge bg-primary">${escrow.booking_status}</span><br>
        <strong>Payment Method:</strong> ${escrow.payment_method ? escrow.payment_method.toUpperCase() : 'N/A'}<br>
        ${escrow.payment_reference ? `<strong>Payment Ref:</strong> ${escrow.payment_reference}<br>` : ''}
      </p>

      <!-- Timeline -->
      <div class="escrow-timeline">
        <h6 style="margin-bottom: 15px;">Escrow Timeline</h6>
        
        <div class="timeline-item">
          <div class="timeline-icon">
            <i class="bi bi-check-circle"></i>
          </div>
          <div class="timeline-content">
            <div class="timeline-title">Payment Verified</div>
            <div class="timeline-date">${escrow.payment_date ? new Date(escrow.payment_date).toLocaleString() : 'N/A'}</div>
          </div>
        </div>

        <div class="timeline-item">
          <div class="timeline-icon">
            <i class="bi bi-lock"></i>
          </div>
          <div class="timeline-content">
            <div class="timeline-title">Funds Held in Escrow</div>
            <div class="timeline-date">${escrow.escrow_held_at ? new Date(escrow.escrow_held_at).toLocaleString() : new Date(escrow.created_at).toLocaleString()}</div>
          </div>
        </div>

        ${actualEscrowStatus === 'released_to_owner' || actualEscrowStatus === 'released' ? `
        <div class="timeline-item">
          <div class="timeline-icon">
            <i class="bi bi-unlock"></i>
          </div>
          <div class="timeline-content">
            <div class="timeline-title">Released to Owner</div>
            <div class="timeline-date">${escrow.escrow_released_at ? new Date(escrow.escrow_released_at).toLocaleString() : 'N/A'}</div>
          </div>
        </div>
        ` : ''}

        ${isOnHold ? `
        <div class="timeline-item">
          <div class="timeline-icon" style="background: #dc3545;">
            <i class="bi bi-pause-circle"></i>
          </div>
          <div class="timeline-content">
            <div class="timeline-title">Put On Hold</div>
            <div class="timeline-date">Currently under review</div>
            ${escrow.escrow_hold_details ? `<div style="margin-top: 8px; font-size: 13px;">${escrow.escrow_hold_details}</div>` : ''}
          </div>
        </div>
        ` : ''}
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
  `;
  
  new bootstrap.Modal(document.getElementById('escrowModal')).show();
}

// RELEASE ESCROW
function releaseEscrow(bookingId) {
  // Comprehensive confirmation message with requirements checklist
  const confirmMessage = `
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   RELEASE ESCROW TO OWNER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Booking ID: #BK-${String(bookingId).padStart(4, '0')}

Before proceeding, confirm that:

✅ Booking is completed
✅ No disputes or issues pending
✅ Owner has valid GCash number
✅ Payment has been verified
✅ No outstanding claims or damages

⚠️ This action cannot be undone!

Funds will be released to owner and payout will be scheduled.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Proceed with escrow release?
  `.trim();

  if (!confirm(confirmMessage)) return;

  const formData = new FormData();
  formData.append('booking_id', bookingId);

  fetch('api/escrow/release_escrow.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('✅ SUCCESS!\n\n' + data.message + '\n\nBooking ID: #BK-' + String(bookingId).padStart(4, '0'));
      location.reload();
    } else {
      alert('❌ RELEASE FAILED\n\n' + data.message + '\n\nPlease check:\n• Booking status\n• Escrow status\n• Owner GCash configuration\n• No active holds');
    }
  })
  .catch(err => {
    console.error(err);
    alert('❌ NETWORK ERROR\n\nCould not connect to server. Please check your connection and try again.');
  });
}

// HOLD ESCROW
function holdEscrow(bookingId) {
  document.getElementById('hold_booking_id').value = bookingId;
  document.getElementById('hold_reason').value = '';
  document.getElementById('hold_details').value = '';
  new bootstrap.Modal(document.getElementById('holdModal')).show();
}

function submitHold() {
  const bookingId = document.getElementById('hold_booking_id').value;
  const reason = document.getElementById('hold_reason').value;
  const details = document.getElementById('hold_details').value.trim();

  if (!reason || !details) {
    alert('Please provide both reason and details');
    return;
  }

  const formData = new FormData();
  formData.append('booking_id', bookingId);
  formData.append('reason', reason);
  formData.append('details', details);

  fetch('api/escrow/hold_escrow.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('⚠️ ' + data.message);
      bootstrap.Modal.getInstance(document.getElementById('holdModal')).hide();
      location.reload();
    } else {
      alert('❌ ' + data.message);
    }
  })
  .catch(err => {
    console.error(err);
    alert('Network error occurred');
  });
}

// REFUND ESCROW
function refundEscrow(bookingId) {
  document.getElementById('refund_booking_id').value = bookingId;
  document.getElementById('refund_reason').value = '';
  document.getElementById('refund_details').value = '';
  new bootstrap.Modal(document.getElementById('refundModal')).show();
}

function submitRefund() {
  const bookingId = document.getElementById('refund_booking_id').value;
  const reason = document.getElementById('refund_reason').value;
  const details = document.getElementById('refund_details').value.trim();

  if (!reason || !details) {
    alert('Please provide both reason and details');
    return;
  }

  if (!confirm('Are you sure you want to refund this escrow to the renter? This cannot be undone.')) {
    return;
  }

  const formData = new FormData();
  formData.append('booking_id', bookingId);
  formData.append('reason', reason);
  formData.append('details', details);

  fetch('api/escrow/refund_escrow.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('✅ ' + data.message);
      bootstrap.Modal.getInstance(document.getElementById('refundModal')).hide();
      location.reload();
    } else {
      alert('❌ ' + data.message);
    }
  })
  .catch(err => {
    console.error(err);
    alert('Network error occurred');
  });
}

// RESUME ESCROW (from on_hold back to held)
function resumeEscrow(bookingId) {
  if (!confirm('Resume this escrow? It will return to normal held status.')) return;

  const formData = new FormData();
  formData.append('booking_id', bookingId);

  fetch('api/escrow/resume_escrow.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('✅ ' + data.message);
      location.reload();
    } else {
      alert('❌ ' + data.message);
    }
  })
  .catch(err => {
    console.error(err);
    alert('Network error occurred');
  });
}

// TOGGLE ADVANCED FILTERS
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

// CLEAR FILTERS
function clearFilters() {
  window.location.href = 'escrow.php?status=held';
}

// EXPORT
function exportEscrow() {
  const params = new URLSearchParams(window.location.search);
  window.location.href = 'api/escrow/export_escrow.php?' + params.toString();
}

// LIVE SEARCH
let searchTimeout;
document.getElementById('searchInput').addEventListener('keyup', function() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    this.form.submit();
  }, 500);
});

// Show advanced filters if any advanced filter is active
window.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const hasAdvancedFilters = urlParams.get('date_from') || urlParams.get('date_to') || 
                              urlParams.get('min_amount') || urlParams.get('max_amount') ||
                              (urlParams.get('limit') && urlParams.get('limit') !== '10');
  
  if (hasAdvancedFilters) {
    document.getElementById('advancedFilters').style.display = 'block';
    document.getElementById('advancedFilterBtn').classList.add('active');
    document.getElementById('advancedFilterBtn').innerHTML = '<i class="bi bi-funnel-fill"></i> Hide Filters';
  }
});
</script>
<script src="include/notifications.js"></script>
</body>
</html>

<?php
$conn->close();
?>