<?php
session_start();
// Configuration loaded via header.php
/**
 * ============================================================================ 
 * UNIFIED USER MANAGEMENT & VERIFICATION SYSTEM
 * CarGo - Agusan del Sur Car Rental Platform
 * ============================================================================ 
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

include 'include/db.php';
include 'include/admin_profile.php';


function fixPath($path) {
    if (empty($path)) return '';
    
    // Remove Windows absolute path and normalize slashes
    $path = str_replace('\\', '/', $path);
    
    // Remove any absolute paths (C:/xampp/..., /var/www/..., etc.)
    // Look for common patterns and extract relative path
    if (strpos($path, 'cargoAdmin') !== false) {
        // Extract everything after 'cargoAdmin/'
        $path = substr($path, strpos($path, 'cargoAdmin') + 11); // 11 = strlen('cargoAdmin/')
    } elseif (strpos($path, 'carGOAdmin') !== false) {
        // Handle old naming convention
        $path = substr($path, strpos($path, 'carGOAdmin') + 11);
    }
    
    // Remove "api/../" patterns
    $path = str_replace('api/../', '', $path);
    
    // If path starts with 'uploads/', it's already relative - make it absolute from web root
    if (strpos($path, 'uploads/') === 0) {
        // For web access, we need to prepend 'cargoAdmin/'
        $path = 'cargoAdmin/' . $path;
    }
    
    // Ensure starts with / for absolute web path
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    
    return $path;
}

// Helper function for profile images (simpler, just handles relative paths)
function fixProfileImagePath($path) {
    if (empty($path)) return '';
    
    // Normalize slashes
    $path = str_replace('\\', '/', $path);
    
    // If it's already a full URL (http/https), return as-is
    if (preg_match('/^https?:\/\//', $path)) {
        return $path;
    }
    
    // Remove any absolute file system paths
    if (strpos($path, 'cargoAdmin') !== false) {
        $path = substr($path, strpos($path, 'cargoAdmin') + 11);
    }
    
    // If already starts with uploads/, just return it (relative path)
    if (strpos($path, 'uploads/') === 0) {
        return $path;
    }
    
    // If starts with /uploads/, remove leading slash
    if (strpos($path, '/uploads/') === 0) {
        return substr($path, 1);
    }
    
    return $path;
}

// ---------------------------
// Helper: bind params (works with variable length)
function bind_params_safe($stmt, $types, $params) {
    if ($types === '' || empty($params)) return;
    // mysqli::bind_param requires references
    $refs = [];
    $refs[] = & $types;
    for ($i = 0; $i < count($params); $i++) {
        $refs[] = & $params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// ============================================================================
// SECTION 1: HANDLE VERIFICATION ACTIONS (Approve/Reject)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve']) || isset($_POST['reject']))) {
    $verification_id = $_POST['id'] ?? null;
    $user_id = $_POST['user_id'] ?? null;

    if (!$verification_id || !$user_id) {
        header("Location: users.php?error=invalid_request");
        exit;
    }

    $verification_id = intval($verification_id);
    $user_id = intval($user_id);

    // Get user info for notification
    $userStmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult ? $userResult->fetch_assoc() : null;
    $userStmt->close();

    if (!$user) {
        header("Location: users.php?error=user_not_found");
        exit;
    }

    // Handle Approval
    if (isset($_POST['approve'])) {
        $updateStmt = $conn->prepare("UPDATE user_verifications 
                                       SET status = 'approved', 
                                           verified_at = NOW(),
                                           updated_at = NOW() 
                                       WHERE id = ?");
        $updateStmt->bind_param("i", $verification_id);

        if ($updateStmt->execute()) {
            $notificationTitle = "Verification Approved ✓";
            $notificationMessage = "Congratulations! Your identity verification has been approved. You now have full access to all features.";

            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, read_status, created_at) 
                                         VALUES (?, ?, ?, 'unread', NOW())");
            $notifStmt->bind_param("iss", $user_id, $notificationTitle, $notificationMessage);
            $notifStmt->execute();
            $notifStmt->close();

            header("Location: users.php?success=approved&user=" . urlencode($user['fullname']));
        } else {
            header("Location: users.php?error=approval_failed&details=" . urlencode($updateStmt->error));
        }

        $updateStmt->close();
        exit;
    }

    // Handle Rejection
    if (isset($_POST['reject'])) {
        $reason = trim($_POST['reason'] ?? '');

        if (empty($reason)) {
            header("Location: users.php?error=rejection_reason_required");
            exit;
        }

        $updateStmt = $conn->prepare("UPDATE user_verifications 
                                       SET status = 'rejected', 
                                           review_notes = ?,
                                           updated_at = NOW() 
                                       WHERE id = ?");
        $updateStmt->bind_param("si", $reason, $verification_id);

        if ($updateStmt->execute()) {
            $notificationTitle = "Verification Rejected ✗";
            $notificationMessage = "Your identity verification was rejected.\n\nReason: " . $reason . "\n\nPlease review the requirements and resubmit your verification with the correct documents.";

            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, read_status, created_at) 
                                         VALUES (?, ?, ?, 'unread', NOW())");
            $notifStmt->bind_param("iss", $user_id, $notificationTitle, $notificationMessage);
            $notifStmt->execute();
            $notifStmt->close();

            header("Location: users.php?success=rejected&user=" . urlencode($user['fullname']));
        } else {
            header("Location: users.php?error=rejection_failed&details=" . urlencode($updateStmt->error));
        }

        $updateStmt->close();
        exit;
    }
}

// ============================================================================
// SECTION 1B: HANDLE USER SUSPENSION (Suspend/Unsuspend)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['suspend_user']) || isset($_POST['unsuspend_user']))) {
    // Basic auth guard
    if (!isset($_SESSION['admin_id'])) {
        header('Location: index.php?error=unauthorized');
        exit;
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    if ($user_id <= 0) {
        header('Location: users.php?error=invalid_request');
        exit;
    }

    // Fetch user
    $userStmt = $conn->prepare("SELECT id, fullname, is_suspended FROM users WHERE id = ? LIMIT 1");
    $userStmt->bind_param('i', $user_id);
    $userStmt->execute();
    $uRes = $userStmt->get_result();
    $user = $uRes ? $uRes->fetch_assoc() : null;
    $userStmt->close();

    if (!$user) {
        header('Location: users.php?error=user_not_found');
        exit;
    }

    $isCurrentlySuspended = !empty($user['is_suspended']) && intval($user['is_suspended']) === 1;

    if (isset($_POST['suspend_user'])) {
        if ($isCurrentlySuspended) {
            header('Location: users.php?error=already_suspended');
            exit;
        }

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            header('Location: users.php?error=suspension_reason_required');
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET is_suspended = 1, suspended_at = NOW(), suspension_reason = ?, api_token = NULL WHERE id = ?");
        $stmt->bind_param('si', $reason, $user_id);

        if ($stmt->execute()) {
            // Notify user
            $title = 'Account Suspended';
            $message = "Your account has been suspended. Reason: {$reason}. If you believe this is a mistake, please contact support.";
            $notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, read_status, created_at) VALUES (?, ?, ?, 'unread', NOW())");
            $notif->bind_param('iss', $user_id, $title, $message);
            $notif->execute();
            $notif->close();

            header('Location: users.php?success=suspended&user=' . urlencode($user['fullname']));
        } else {
            header('Location: users.php?error=suspend_failed&details=' . urlencode($stmt->error));
        }

        $stmt->close();
        exit;
    }

    if (isset($_POST['unsuspend_user'])) {
        if (!$isCurrentlySuspended) {
            header('Location: users.php?error=not_suspended');
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET is_suspended = 0, suspended_at = NULL, suspension_reason = NULL WHERE id = ?");
        $stmt->bind_param('i', $user_id);

        if ($stmt->execute()) {
            $title = 'Account Reactivated';
            $message = 'Your account suspension has been lifted. You may now log in again.';
            $notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, read_status, created_at) VALUES (?, ?, ?, 'unread', NOW())");
            $notif->bind_param('iss', $user_id, $title, $message);
            $notif->execute();
            $notif->close();

            header('Location: users.php?success=unsuspended&user=' . urlencode($user['fullname']));
        } else {
            header('Location: users.php?error=unsuspend_failed&details=' . urlencode($stmt->error));
        }

        $stmt->close();
        exit;
    }
}

// ============================================================================
// SECTION 2: DETERMINE VIEW MODE
// ============================================================================
$viewMode = $_GET['view'] ?? 'management';

// ============================================================================
// SECTION 3: BUILD FILTER CONDITIONS (APPLIES TO BOTH VIEWS)
// ============================================================================
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? 'all';
$verificationFilter = $_GET['verification'] ?? 'all';

$whereConditions = ["1=1"];
$params = [];
$types = "";

// Search filter
if (!empty($search)) {
    $whereConditions[] = "(users.fullname LIKE ? OR users.email LIKE ? OR users.phone LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

// Role filter
if ($role !== "all") {
    $whereConditions[] = "users.role = ?";
    $params[] = $role;
    $types .= "s";
}

// Verification filter
if ($verificationFilter === 'verified') {
    $whereConditions[] = "user_verifications.status = 'approved'";
} elseif ($verificationFilter === 'pending') {
    $whereConditions[] = "user_verifications.status = 'pending'";
} elseif ($verificationFilter === 'not-verified') {
    $whereConditions[] = "user_verifications.id IS NULL";
} elseif ($verificationFilter === 'rejected') {
    $whereConditions[] = "user_verifications.status = 'rejected'";
} elseif ($verificationFilter === 'suspended') {
    $whereConditions[] = "users.is_suspended = 1";
}

$whereClause = implode(" AND ", $whereConditions);

// ============================================================================
// SECTION 4: FETCH STATISTICS (with filters applied)
// ============================================================================
$statsQuery = "
    SELECT 
        COUNT(DISTINCT users.id) as total,
        SUM(CASE WHEN user_verifications.status = 'approved' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN user_verifications.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN user_verifications.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN user_verifications.id IS NULL THEN 1 ELSE 0 END) as unverified,
        SUM(CASE WHEN users.is_suspended = 1 THEN 1 ELSE 0 END) as suspended
    FROM users
    LEFT JOIN user_verifications ON users.id = user_verifications.user_id
    WHERE {$whereClause}
";

$stats = [
    'total' => 0,
    'verified' => 0,
    'pending' => 0,
    'rejected' => 0,
    'unverified' => 0,
    'suspended' => 0
];

if (!empty($params)) {
    $statsStmt = $conn->prepare($statsQuery);
    if ($statsStmt) {
        bind_params_safe($statsStmt, $types, $params);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        if ($statsResult) {
            $f = $statsResult->fetch_assoc();
            if ($f) $stats = array_merge($stats, $f);
        }
        $statsStmt->close();
    } else {
        // Fallback: run direct query (less safe) if prepare failed
        $tmpResult = $conn->query($statsQuery);
        if ($tmpResult) {
            $f = $tmpResult->fetch_assoc();
            if ($f) $stats = array_merge($stats, $f);
        }
    }
} else {
    $statsResult = $conn->query($statsQuery);
    if ($statsResult) {
        $f = $statsResult->fetch_assoc();
        if ($f) $stats = array_merge($stats, $f);
    }
}

// Normalize stats numeric values
foreach (['total','verified','pending','rejected','unverified','suspended'] as $k) {
    $stats[$k] = intval($stats[$k] ?? 0);
}

// ============================================================================
// SECTION 5: FETCH DATA BASED ON VIEW MODE
// ============================================================================
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Count total rows
$countSql = "SELECT COUNT(DISTINCT users.id) as total FROM users LEFT JOIN user_verifications ON users.id = user_verifications.user_id WHERE {$whereClause}";

$totalRows = 0;
if (!empty($params)) {
    $countStmt = $conn->prepare($countSql);
    if ($countStmt) {
        bind_params_safe($countStmt, $types, $params);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        if ($countResult) {
            $f = $countResult->fetch_assoc();
            $totalRows = intval($f['total'] ?? 0);
        }
        $countStmt->close();
    } else {
        $tmp = $conn->query($countSql);
        $f = $tmp ? $tmp->fetch_assoc() : null;
        $totalRows = intval($f['total'] ?? 0);
    }
} else {
    $countResult = $conn->query($countSql);
    if ($countResult) {
        $f = $countResult->fetch_assoc();
        $totalRows = intval($f['total'] ?? 0);
    }
}

$totalPages = $limit > 0 ? ceil($totalRows / $limit) : 1;

// Build SQL depending on view
if ($viewMode === 'overview') {
    $sql = "SELECT users.*, user_verifications.id as verification_id, user_verifications.status as verification_status,
            user_verifications.created_at as verification_date, user_verifications.verified_at, user_verifications.review_notes
            FROM users LEFT JOIN user_verifications ON users.id = user_verifications.user_id
            WHERE {$whereClause} ORDER BY users.created_at DESC";
    // prepare
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            bind_params_safe($stmt, $types, $params);
        } else {
            // fallback to direct query
            $stmt = null;
            $directQuery = $sql;
        }
    } else {
        $stmt = $conn->prepare($sql);
    }
} else {
    $sql = "SELECT users.*, user_verifications.id as verification_id, user_verifications.status as verification_status,
        user_verifications.id_type, user_verifications.first_name as ver_first_name, user_verifications.last_name as ver_last_name,
        user_verifications.mobile_number, user_verifications.gender, user_verifications.date_of_birth,
        user_verifications.region, user_verifications.province, user_verifications.municipality, user_verifications.barangay,
        user_verifications.id_front_photo, user_verifications.id_back_photo, user_verifications.selfie_photo,
        user_verifications.review_notes, user_verifications.created_at as verification_date, user_verifications.verified_at
        FROM users LEFT JOIN user_verifications ON users.id = user_verifications.user_id
        WHERE {$whereClause} ORDER BY users.created_at DESC LIMIT ? OFFSET ?";

    // Merge params + limit + offset
    $queryParams = $params;
    $queryTypes  = $types;
    $queryParams[] = $limit;
    $queryParams[] = $offset;
    $queryTypes .= "ii";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        bind_params_safe($stmt, $queryTypes, $queryParams);
    } else {
        // fallback to direct query building (escape ints)
        $directQuery = str_replace("WHERE {$whereClause}", "WHERE {$whereClause} LIMIT {$limit} OFFSET {$offset}", $sql);
    }
}

// Execute statement or direct query
$query = false;
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->execute();
    $query = $stmt->get_result();
} else {
    // direct query fallback (less safe, uses current params substituted)
    if (isset($directQuery)) {
        $query = $conn->query($directQuery);
    } else {
        // if no stmt and no directQuery, try running prepared path without bind (edge case)
        $fallback = $conn->query($sql);
        if ($fallback) $query = $fallback;
    }
}

// If $query is false/null convert to empty result like object
if (!$query) {
    // create an empty array to avoid fatal errors in view
    $fake = new stdClass();
    $fake->num_rows = 0;
    $query = $fake;
}

// Helper function to build URL with filters
function buildFilterUrl($baseParams = []) {
    global $search, $role, $verificationFilter;

    $params = $baseParams;

    if (!empty($search)) {
        $params['search'] = $search;
    }
    if ($role !== 'all') {
        $params['role'] = $role;
    }
    if ($verificationFilter !== 'all') {
        $params['verification'] = $verificationFilter;
    }

    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $viewMode === 'overview' ? 'User Status Overview' : 'User Management' ?> - CarGo Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="include/admin-styles.css?v=<?= time() ?>" rel="stylesheet">
  <link href="include/notifications.css" rel="stylesheet">
  <link href="include/modal-theme-standardized.css" rel="stylesheet">
  
  <style>
    /* Additional inline enhancements for smooth animations */
    .fade-in {
      animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .stat-card {
      animation: slideUp 0.6s ease-out backwards;
    }
    
    .stat-card:nth-child(1) { animation-delay: 0.1s; }
    .stat-card:nth-child(2) { animation-delay: 0.2s; }
    .stat-card:nth-child(3) { animation-delay: 0.3s; }
    .stat-card:nth-child(4) { animation-delay: 0.4s; }
    .stat-card:nth-child(5) { animation-delay: 0.5s; }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .active-filter {
      background-color: #667eea !important;
      color: white !important;
      border-color: #667eea !important;
    }

    /* doc modal basic styles (if your CSS expects these) */
    .doc-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 9999; }
    .doc-modal.active { display: flex; }
    /* doc-modal styles now handled by modal-theme-standardized.css (contact-modal design) */

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
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      position: relative !important;
      background-image: none !important;
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
  </style>
</head>
<body>

<div class="dashboard-wrapper">
  <?php include('include/sidebar.php'); ?>

  <main class="main-content">
    
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php if ($_GET['success'] === 'approved'): ?>
          <strong>Verification Approved!</strong> User <strong><?= htmlspecialchars($_GET['user'] ?? '') ?></strong> has been successfully verified and notified.
        <?php elseif ($_GET['success'] === 'rejected'): ?>
          <strong>Verification Rejected!</strong> User <strong><?= htmlspecialchars($_GET['user'] ?? '') ?></strong> has been notified of the rejection.
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Error!</strong>
        <?php
        $errorMessages = [
            'invalid_request' => 'Invalid request. Please try again.',
            'approval_failed' => 'Failed to approve verification. Please try again.',
            'rejection_failed' => 'Failed to reject verification. Please try again.',
            'rejection_reason_required' => 'Rejection reason is required.',
            'user_not_found' => 'User not found.'
        ];
        echo $errorMessages[$_GET['error']] ?? 'An error occurred. Please try again.';
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Top Bar -->
    <div class="top-bar fade-in">
      <h1 class="page-title">
        <i class="bi bi-people-fill"></i>
        <?= $viewMode === 'overview' ? 'User Status Overview' : 'Users Management' ?>
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

    <!-- Search Section (Now appears in BOTH views) -->
    <div class="search-section fade-in" style="animation-delay: 0.15s;">
      <form method="GET" class="search-form">
        <input type="hidden" name="view" value="<?= $viewMode ?>">
        <input 
          type="text" 
          name="search" 
          class="search-input" 
          placeholder="🔍 Search by name, email, or phone number..." 
          value="<?= htmlspecialchars($search) ?>">
        
        <select name="role" class="filter-select <?= $role !== 'all' ? 'active-filter' : '' ?>">
          <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>👥 All Roles</option>
          <option value="Owner" <?= $role === 'Owner' ? 'selected' : '' ?>>🚗 Car Owners</option>
          <option value="Renter" <?= $role === 'Renter' ? 'selected' : '' ?>>👤 Renters</option>
        </select>

        <select name="verification" class="filter-select <?= $verificationFilter !== 'all' ? 'active-filter' : '' ?>">
          <option value="all" <?= $verificationFilter === 'all' ? 'selected' : '' ?>>🔐 All Status</option>
          <option value="verified" <?= $verificationFilter === 'verified' ? 'selected' : '' ?>>✅ Verified</option>
          <option value="pending" <?= $verificationFilter === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
          <option value="rejected" <?= $verificationFilter === 'rejected' ? 'selected' : '' ?>>❌ Rejected</option>
          <option value="not-verified" <?= $verificationFilter === 'not-verified' ? 'selected' : '' ?>>⚠️ Not Verified</option>
          <option value="suspended" <?= $verificationFilter === 'suspended' ? 'selected' : '' ?>>🚫 Suspended</option>
        </select>

        <button type="submit" class="search-btn">
          <i class="bi bi-search"></i> Search
        </button>
        
        <a href="users.php?view=<?= $viewMode ?>" class="reset-btn">
          <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
      </form>
      
      <?php if (!empty($search) || $role !== 'all' || $verificationFilter !== 'all'): ?>
        <div class="alert alert-info mt-3 mb-0">
          <i class="bi bi-funnel-fill me-2"></i>
          <strong>Active Filters:</strong>
          <?php if (!empty($search)): ?>
            <span class="badge bg-primary me-1">Search: "<?= htmlspecialchars($search) ?>"</span>
          <?php endif; ?>
          <?php if ($role !== 'all'): ?>
            <span class="badge bg-primary me-1">Role: <?= htmlspecialchars($role) ?></span>
          <?php endif; ?>
          <?php if ($verificationFilter !== 'all'): ?>
            <span class="badge bg-primary me-1">Verification: <?= ucfirst(str_replace('-', ' ', $verificationFilter)) ?></span>
          <?php endif; ?>
          <span class="ms-2">| Showing <?= $totalRows ?> result<?= $totalRows != 1 ? 's' : '' ?></span>
        </div>
      <?php endif; ?>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon stat-total">
            <i class="bi bi-people"></i>
          </div>
        </div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">Total Users</div>
        <div class="stat-detail">
          <i class="bi bi-graph-up"></i> <?= empty($search) && $role === 'all' && $verificationFilter === 'all' ? 'All registered users' : 'Filtered results' ?>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon stat-approved">
            <i class="bi bi-shield-check"></i>
          </div>
        </div>
        <div class="stat-value"><?= $stats['verified'] ?></div>
        <div class="stat-label">Verified Users</div>
        <div class="stat-detail">
          <i class="bi bi-check-circle"></i> Identity confirmed
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon stat-pending">
            <i class="bi bi-clock-history"></i>
          </div>
        </div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-label">Pending Review</div>
        <div class="stat-detail">
          <i class="bi bi-hourglass-split"></i> Awaiting verification
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon stat-rejected">
            <i class="bi bi-<?= $viewMode === 'overview' ? 'x-circle' : 'person-x' ?>"></i>
          </div>
        </div>
        <div class="stat-value"><?= $viewMode === 'overview' ? $stats['rejected'] : $stats['unverified'] ?></div>
        <div class="stat-label"><?= $viewMode === 'overview' ? 'Rejected' : 'Not Verified' ?></div>
        <div class="stat-detail">
          <i class="bi bi-info-circle"></i> <?= $viewMode === 'overview' ? 'Declined submissions' : 'No submission yet' ?>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon stat-suspended">
            <i class="bi bi-ban"></i>
          </div>
        </div>
        <div class="stat-value"><?= $stats['suspended'] ?></div>
        <div class="stat-label">Suspended Users</div>
        <div class="stat-detail">
          <i class="bi bi-slash-circle"></i> Account suspended
        </div>
      </div>
    </div>

    <?php if ($viewMode === 'overview'): ?>
      
      <!-- Quick Action Buttons -->
      <div class="card mb-4 fade-in" style="animation-delay: 0.2s;">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0">
            <i class="bi bi-lightning-charge-fill me-2"></i>Quick Actions
          </h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <a href="<?= buildFilterUrl(['view' => 'management', 'verification' => 'pending']) ?>" class="btn btn-warning w-100">
                <i class="bi bi-clock-history me-2"></i>Review Pending
                <span class="badge bg-light text-warning ms-2"><?= $stats['pending'] ?></span>
              </a>
            </div>
            <div class="col-md-3">
              <a href="<?= buildFilterUrl(['view' => 'management', 'verification' => 'verified']) ?>" class="btn btn-success w-100">
                <i class="bi bi-shield-check me-2"></i>View Verified
                <span class="badge bg-light text-success ms-2"><?= $stats['verified'] ?></span>
              </a>
            </div>
            <div class="col-md-3">
              <a href="<?= buildFilterUrl(['view' => 'management', 'verification' => 'suspended']) ?>" class="btn btn-danger w-100">
                <i class="bi bi-ban me-2"></i>View Suspended
                <span class="badge bg-light text-danger ms-2"><?= $stats['suspended'] ?></span>
              </a>
            </div>
            <div class="col-md-3">
              <a href="<?= buildFilterUrl(['view' => 'management']) ?>" class="btn btn-primary w-100">
                <i class="bi bi-gear-fill me-2"></i>Manage All Users
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- User Overview Cards -->
      <div class="card fade-in" style="animation-delay: 0.3s;">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">
            <i class="bi bi-list-ul me-2"></i>All Users Overview
            <span class="badge bg-light text-primary ms-2"><?= $stats['total'] ?></span>
          </h5>
        </div>
        <div class="card-body p-3">
          <?php 
          if ($query->num_rows === 0) {
            echo '<div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h4>No users found</h4>
                    <p>No users match your current filters. Try adjusting your search criteria.</p>
                  </div>';
          } else {
            $counter = 1;
            while ($user = $query->fetch_assoc()) {
              $statusBadge = '';
              $statusClass = '';
              $statusIcon = '';
              
              // Check if user is suspended first
              $isSuspended = !empty($user['is_suspended']) && intval($user['is_suspended']) === 1;
              
              if ($isSuspended) {
                $statusBadge = '<span class="badge bg-danger"><i class="bi bi-ban me-1"></i>Account Suspended</span>';
                $statusClass = 'text-danger';
                $statusIcon = '<i class="bi bi-ban"></i>';
                if (!empty($user['suspension_reason'])) {
                  $statusBadge .= '<br><small class="text-danger mt-1 d-block"><i class="bi bi-info-circle me-1"></i>' . htmlspecialchars(substr($user['suspension_reason'], 0, 50)) . '...</small>';
                }
              } elseif (empty($user['verification_id'])) {
                $statusBadge = '<span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Not Submitted</span>';
                $statusClass = 'text-secondary';
                $statusIcon = '<i class="bi bi-person-x"></i>';
              } elseif ($user['verification_status'] === 'approved') {
                $statusBadge = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Verified</span>';
                $statusClass = 'text-success';
                $statusIcon = '<i class="bi bi-shield-check"></i>';
                $verifiedDate = !empty($user['verified_at']) ? date('M d, Y', strtotime($user['verified_at'])) : '';
                if ($verifiedDate) $statusBadge .= '<br><small class="text-muted mt-1 d-block"><i class="bi bi-calendar-check me-1"></i>Verified on ' . $verifiedDate . '</small>';
              } elseif ($user['verification_status'] === 'pending') {
                $statusBadge = '<span class="badge bg-warning"><i class="bi bi-hourglass-split me-1"></i>Pending Review</span>';
                $statusClass = 'text-warning';
                $statusIcon = '<i class="bi bi-clock-history"></i>';
                $submitDate = !empty($user['verification_date']) ? date('M d, Y', strtotime($user['verification_date'])) : '';
                if ($submitDate) $statusBadge .= '<br><small class="text-muted mt-1 d-block"><i class="bi bi-upload me-1"></i>Submitted ' . $submitDate . '</small>';
              } elseif ($user['verification_status'] === 'rejected') {
                $statusBadge = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>';
                $statusClass = 'text-danger';
                $statusIcon = '<i class="bi bi-x-circle"></i>';
                if (!empty($user['review_notes'])) {
                  $statusBadge .= '<br><small class="text-danger mt-1 d-block"><i class="bi bi-info-circle me-1"></i>' . htmlspecialchars(substr($user['review_notes'], 0, 50)) . '...</small>';
                }
              }
              
              $avatar = !empty($user['profile_image']) 
                ? htmlspecialchars(fixProfileImagePath($user['profile_image'])) 
                : 'https://ui-avatars.com/api/?name=' . urlencode($user['fullname']) . '&background=random';
              ?>
              <div class="card mb-2 shadow-sm">
                <div class="card-body">
                  <div class="row align-items-center">
                    <div class="col-auto">
                      <div class="bg-light rounded-circle d-flex align-items-center justify-content-center <?= $statusClass ?>" 
                           style="width: 55px; height: 55px; font-size: 1.6rem;">
                        <?= $statusIcon ?>
                      </div>
                    </div>
                    <div class="col-md-1">
                      <strong class="text-primary">#<?= $counter++ ?></strong>
                      <div class="text-muted small">ID: <?= $user['id'] ?></div>
                    </div>
                    <div class="col-md-3">
                      <div class="d-flex align-items-center">
                        <img src="<?= $avatar ?>" class="rounded-circle me-2" 
                             style="width: 45px; height: 45px; object-fit: cover;"
                             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['fullname']) ?>&background=random';">
                        <div>
                          <div class="fw-bold"><?= htmlspecialchars($user['fullname']) ?></div>
                          <div class="text-muted small">
                            <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email']) ?>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-2">
                      <span class="badge bg-<?= $user['role'] === 'Owner' ? 'primary' : 'info' ?>">
                        <i class="bi bi-<?= $user['role'] === 'Owner' ? 'car-front' : 'person' ?> me-1"></i>
                        <?= htmlspecialchars($user['role']) ?>
                      </span>
                      <?php if (!empty($user['municipality'])): ?>
                        <div class="text-muted small mt-1">
                          <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($user['municipality']) ?>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                      <div class="<?= $statusClass ?> fw-bold">
                        <?= $statusBadge ?>
                      </div>
                    </div>
                    <div class="col-md-2 text-end">
                      <?php if (!empty($user['verification_status']) && $user['verification_status'] === 'pending'): ?>
                        <a href="<?= buildFilterUrl(['view' => 'management', 'highlight' => $user['verification_id']]) ?>" 
                           class="btn btn-sm btn-warning">
                          <i class="bi bi-eye me-1"></i>Review Now
                        </a>
                      <?php endif; ?>
                      <small class="text-muted d-block mt-2">
                        <i class="bi bi-calendar-plus me-1"></i><?= date('M d, Y', strtotime($user['created_at'])) ?>
                      </small>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
          }
          ?>
        </div>
      </div>

      <!-- System Info -->
      <div class="card mt-4 fade-in" style="animation-delay: 0.4s;">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0">
            <i class="bi bi-info-circle-fill me-2"></i>System Information
          </h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <h6 class="fw-bold text-primary"><i class="bi bi-database me-2"></i>Database Status</h6>
              <ul class="list-unstyled">
                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Users table: <span class="text-success fw-bold">Connected</span></li>
                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Verifications table: <span class="text-success fw-bold">Connected</span></li>
                <li><i class="bi bi-file-earmark-text me-2"></i>Total records: <strong><?= $stats['total'] ?></strong> users</li>
              </ul>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold text-primary"><i class="bi bi-graph-up me-2"></i>Verification Statistics</h6>
              <ul class="list-unstyled">
                <li><i class="bi bi-upload me-2"></i>Submission Rate: 
                  <strong class="text-info">
                    <?= $stats['total'] > 0 ? 
                        round((($stats['verified'] + $stats['pending'] + $stats['rejected']) / $stats['total']) * 100, 1) : 0 ?>%
                  </strong>
                </li>
                <li><i class="bi bi-check-circle me-2"></i>Approval Rate: 
                  <strong class="text-success">
                    <?= ($stats['verified'] + $stats['pending'] + $stats['rejected']) > 0 ? 
                        round(($stats['verified'] / ($stats['verified'] + $stats['pending'] + $stats['rejected'])) * 100, 1) : 0 ?>%
                  </strong>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

    <?php else: ?>

      <!-- Table Section -->
      <div class="table-section fade-in" style="animation-delay: 0.3s;">
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th><i class="bi bi-hash me-1"></i>#</th>
                <th><i class="bi bi-person me-1"></i>User</th>
                <th><i class="bi bi-telephone me-1"></i>Phone</th>
                <th><i class="bi bi-tag me-1"></i>Role</th>
                <th><i class="bi bi-geo-alt me-1"></i>Municipality</th>
                <th><i class="bi bi-calendar me-1"></i>Joined</th>
                <th><i class="bi bi-shield-check me-1"></i>Verification</th>
                <th><i class="bi bi-gear me-1"></i>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              if ($query->num_rows === 0) {
                echo '<tr><td colspan="8" class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h4>No users found</h4>
                        <p>Try adjusting your search or filter criteria</p>
                      </td></tr>';
              }

              $num = $offset + 1;
              while($row = $query->fetch_assoc()) { 
                $avatar = !empty($row['profile_image']) 
                  ? htmlspecialchars(fixProfileImagePath($row['profile_image'])) 
                  : 'https://ui-avatars.com/api/?name=' . urlencode($row['fullname']) . '&background=667eea&color=fff';
                
                $verificationStatus = $row['verification_status'] ?? 'not-verified';
                $verificationLabel = $verificationStatus === 'approved' ? 'Verified' : 
                                    ($verificationStatus === 'pending' ? 'Pending' : 
                                    ($verificationStatus === 'rejected' ? 'Rejected' : 'Not Verified'));
              ?>
              <tr <?= isset($_GET['highlight']) && $_GET['highlight'] == $row['verification_id'] ? 'style="background-color: #fff3cd;"' : '' ?>>
                <td><strong class="text-primary">#<?= $num++ ?></strong></td>
                <td>
                  <div class="user-cell">
                    <div class="user-avatar-small">
                      <img src="<?= $avatar ?>" alt="<?= htmlspecialchars($row['fullname']) ?>"
                           onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['fullname']) ?>&background=667eea&color=fff';">
                    </div>
                    <div class="user-info">
                      <span class="user-name"><?= htmlspecialchars($row['fullname']) ?></span>
                      <span class="user-email"><?= htmlspecialchars($row['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td>
                  <i class="bi bi-phone me-1"></i>
                  <?= htmlspecialchars($row['phone'] ?? 'N/A') ?>
                </td>
                <td>
                  <span class="role-badge <?= strtolower($row['role']) ?>">
                    <i class="bi bi-<?= $row['role'] === 'Owner' ? 'car-front' : 'person' ?> me-1"></i>
                    <?= htmlspecialchars($row['role']) ?>
                  </span>
                </td>
                <td>
                  <i class="bi bi-geo-alt-fill me-1 text-danger"></i>
                  <?= htmlspecialchars($row['municipality'] ?: 'N/A') ?>
                </td>
                <td>
                  <i class="bi bi-calendar-check me-1"></i>
                  <?= date('M d, Y', strtotime($row['created_at'])) ?>
                </td>
                <td>
                  <?php 
                  $isSuspendedInTable = !empty($row['is_suspended']) && intval($row['is_suspended']) === 1;
                  if ($isSuspendedInTable): 
                  ?>
                    <span class="badge bg-danger">
                      <i class="bi bi-ban me-1"></i>Suspended
                    </span>
                  <?php else: ?>
                    <span class="verification-badge <?= $verificationStatus ?>">
                      <?= $verificationLabel ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="action-buttons">
                    <?php if (!empty($row['verification_id'])) { ?>
                      <button class="action-btn view" title="View Verification Details" 
                              data-bs-toggle="modal" data-bs-target="#verificationModal<?= $row['verification_id'] ?>">
                        <i class="bi bi-eye-fill"></i>
                      </button>
                    <?php } else { ?>
                      <button class="action-btn view" title="No Verification Submitted" disabled>
                        <i class="bi bi-eye-slash"></i>
                      </button>
                    <?php } ?>

                    <?php 
                      $isSuspended = !empty($row['is_suspended']) && intval($row['is_suspended']) === 1;
                    ?>

                    <?php if ($isSuspended): ?>
                      <form method="POST" action="users.php" class="d-inline" onsubmit="return confirm('Unsuspend this user?');">
                        <input type="hidden" name="user_id" value="<?= intval($row['id']) ?>">
                        <button type="submit" name="unsuspend_user" class="action-btn" title="Unsuspend User" style="background:#198754; color:#fff;">
                          <i class="bi bi-unlock-fill"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="POST" action="users.php" class="d-inline" onsubmit="return window.__cargoSuspendUser(this);">
                        <input type="hidden" name="user_id" value="<?= intval($row['id']) ?>">
                        <input type="hidden" name="reason" value="">
                        <button type="submit" name="suspend_user" class="action-btn" title="Suspend User" style="background:#dc3545; color:#fff;">
                          <i class="bi bi-slash-circle-fill"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>

              <!-- Verification Details Modal -->
              <?php if (!empty($row['verification_id'])) { 
                $statusClass = match($verificationStatus) {
                  "approved" => "success",
                  "rejected" => "danger",
                  default => "warning"
                };
              ?>
              <div class="modal fade" id="verificationModal<?= $row['verification_id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                  <div class="modal-content modal-verification">
                    <div class="modal-header">
                      <h5 class="modal-title">
                        <i class="bi bi-shield-check-fill me-2"></i>Verification Details - <?= htmlspecialchars($row['fullname']) ?>
                      </h5>
                      <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                      <!-- User Information Grid -->
                      <div class="row mb-3">
                        <div class="col-md-4">
                          <strong><i class="bi bi-person-fill me-2 text-primary"></i>Full Name:</strong><br>
                          <span class="text-muted"><?= htmlspecialchars($row['ver_first_name'] . " " . $row['ver_last_name']) ?></span>
                        </div>
                        <div class="col-md-4">
                          <strong><i class="bi bi-envelope-fill me-2 text-primary"></i>Email:</strong><br>
                          <span class="text-muted"><?= htmlspecialchars($row['email']) ?></span>
                        </div>
                        <div class="col-md-4">
                          <strong><i class="bi bi-phone-fill me-2 text-primary"></i>Mobile:</strong><br>
                          <span class="text-muted"><?= htmlspecialchars($row['mobile_number']) ?></span>
                        </div>
                      </div>

                      <!-- Address Information -->
<div class="row mb-3">
  <div class="col-md-12">
    <strong><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Address:</strong><br>
    <span class="text-muted">
      <?php 
      $addressParts = [];
      if (!empty($row['barangay'])) $addressParts[] = 'Brgy. ' . htmlspecialchars($row['barangay']);
      if (!empty($row['municipality'])) $addressParts[] = htmlspecialchars($row['municipality']);
      if (!empty($row['province'])) $addressParts[] = htmlspecialchars($row['province']);
      if (!empty($row['region'])) $addressParts[] = htmlspecialchars($row['region']);

      echo !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';
      ?>
    </span>
  </div>
</div>

                      <div class="row mb-3">
                        <div class="col-md-4">
                          <strong><i class="bi bi-gender-ambiguous me-2 text-primary"></i>Gender:</strong><br>
                          <span class="text-muted"><?= htmlspecialchars($row['gender'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-4">
                          <strong><i class="bi bi-calendar-event me-2 text-primary"></i>Date of Birth:</strong><br>
                          <span class="text-muted"><?= $row['date_of_birth'] ? date('M d, Y', strtotime($row['date_of_birth'])) : 'N/A' ?></span>
                        </div>
                        <div class="col-md-4">
                          <strong><i class="bi bi-card-text me-2 text-primary"></i>ID Type:</strong><br>
                          <span class="badge bg-info"><?= htmlspecialchars($row['id_type']) ?></span>
                        </div>
                      </div>

                      <div class="row mb-3">
                        <div class="col-md-6">
                          <strong><i class="bi bi-info-circle me-2 text-primary"></i>Status:</strong><br>
                          <span class="badge bg-<?= $statusClass ?> fs-6 mt-1">
                            <i class="bi bi-<?= $statusClass === 'success' ? 'check-circle' : ($statusClass === 'danger' ? 'x-circle' : 'clock') ?> me-1"></i>
                            <?= ucfirst($verificationStatus) ?>
                          </span>
                        </div>
                        <div class="col-md-6">
                          <strong><i class="bi bi-clock-history me-2 text-primary"></i>Submitted:</strong><br>
                          <span class="text-muted"><?= !empty($row['verification_date']) ? date('M d, Y h:i A', strtotime($row['verification_date'])) : 'N/A' ?></span>
                        </div>
                      </div>

                      <?php if($verificationStatus == "rejected" && !empty($row["review_notes"])) { ?>
                        <div class="alert alert-danger">
                          <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Rejection Reason:</strong><br>
                          <span class="ms-4"><?= nl2br(htmlspecialchars($row["review_notes"])) ?></span>
                        </div>
                      <?php } ?>

                      <hr>

                      <h6 class="fw-bold mb-3">
                        <i class="bi bi-file-earmark-image-fill me-2"></i>Submitted Documents
                      </h6>
                      <div class="row text-center g-3">
                        <div class="col-md-4">
                          <p class="fw-semibold text-primary">
                            <i class="bi bi-front me-2"></i>ID (Front)
                          </p>
                          <?php $idFrontPath = fixPath($row['id_front_photo']); ?>
                          <img src="<?= htmlspecialchars($idFrontPath) ?>" 
                               class="img-fluid rounded border doc-thumbnail" 
                               style="cursor: pointer; max-height: 200px; object-fit: cover; width: 100%;"
                               onclick="viewDocument('<?= htmlspecialchars($idFrontPath) ?>', 'ID Front')"
                               data-original-path="<?= htmlspecialchars($row['id_front_photo']) ?>"
                               onerror="handleImageError(this)">
                        </div>

                        <div class="col-md-4">
                          <p class="fw-semibold text-primary">
                            <i class="bi bi-back me-2"></i>ID (Back)
                          </p>
                          <?php $idBackPath = fixPath($row['id_back_photo']); ?>
                          <img src="<?= htmlspecialchars($idBackPath) ?>" 
                               class="img-fluid rounded border doc-thumbnail"
                               style="cursor: pointer; max-height: 200px; object-fit: cover; width: 100%;"
                               onclick="viewDocument('<?= htmlspecialchars($idBackPath) ?>', 'ID Back')"
                               data-original-path="<?= htmlspecialchars($row['id_back_photo']) ?>"
                               onerror="handleImageError(this)">
                        </div>

                        <div class="col-md-4">
                          <p class="fw-semibold text-primary">
                            <i class="bi bi-camera-fill me-2"></i>Selfie
                          </p>
                          <?php $selfiePath = fixPath($row['selfie_photo']); ?>
                          <img src="<?= htmlspecialchars($selfiePath) ?>" 
                               class="img-fluid rounded border doc-thumbnail"
                               style="cursor: pointer; max-height: 200px; object-fit: cover; width: 100%;"
                               onclick="viewDocument('<?= htmlspecialchars($selfiePath) ?>', 'Selfie Photo')"
                               data-original-path="<?= htmlspecialchars($row['selfie_photo']) ?>"
                               onerror="handleImageError(this)">
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer">
                      <?php if ($verificationStatus == "pending") { ?>
                        <form method="POST" action="users.php" class="d-inline">
                          <input type="hidden" name="id" value="<?= $row['verification_id'] ?>">
                          <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                          <button name="approve" class="btn btn-success" onclick="return confirm('✅ Approve this verification?\n\nThe user will be notified immediately and granted full access.');">
                            <i class="bi bi-check-circle-fill me-2"></i>Approve Verification
                          </button>
                        </form>

                        <button class="btn btn-danger" 
                                data-bs-toggle="modal" 
                                data-bs-target="#rejectModal<?= $row['verification_id'] ?>"
                                data-bs-dismiss="modal">
                          <i class="bi bi-x-circle-fill me-2"></i>Reject Verification
                        </button>
                      <?php } ?>

                      <button class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-2"></i>Close
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Reject Reason Modal -->
              <div class="modal fade" id="rejectModal<?= $row['verification_id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content modal-danger">
                    <div class="modal-header">
                      <h5 class="modal-title">
                        <i class="bi bi-x-circle-fill me-2"></i>Reject Verification
                      </h5>
                      <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <form method="POST" action="users.php">
                      <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $row['verification_id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">

                        <div class="alert alert-warning">
                          <i class="bi bi-exclamation-triangle-fill me-2"></i>
                          <strong>Important:</strong> The user will be notified of the rejection and can resubmit their verification.
                        </div>

                        <label class="form-label fw-bold">
                          <i class="bi bi-pencil-square me-2"></i>Reason for Rejection *
                        </label>
                        <textarea name="reason" required rows="4" class="form-control"
                          placeholder="Please provide a clear and specific reason for rejection. Include what needs to be corrected or improved."></textarea>
                      </div>

                      <div class="modal-footer">
                        <button type="submit" name="reject" class="btn btn-danger">
                          <i class="bi bi-send-fill me-2"></i>Submit Rejection
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                          <i class="bi bi-x-lg me-2"></i>Cancel
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <?php } ?>

              <?php } ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
          <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= buildFilterUrl(['view' => 'management', 'page' => max(1, $page - 1)]) ?>">
                <i class="bi bi-chevron-left"></i>
              </a>
            </li>

            <?php 
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            
            if ($start > 1) {
              echo '<li class="page-item"><a class="page-link" href="' . buildFilterUrl(['view' => 'management', 'page' => 1]) . '">1</a></li>';
              if ($start > 2) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
              }
            }
            
            for ($i = $start; $i <= $end; $i++): ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= buildFilterUrl(['view' => 'management', 'page' => $i]) ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; 
            
            if ($end < $totalPages) {
              if ($end < $totalPages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
              }
              echo '<li class="page-item"><a class="page-link" href="' . buildFilterUrl(['view' => 'management', 'page' => $totalPages]) . '">' . $totalPages . '</a></li>';
            }
            ?>

            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= buildFilterUrl(['view' => 'management', 'page' => min($totalPages, $page + 1)]) ?>">
                <i class="bi bi-chevron-right"></i>
              </a>
            </li>
          </ul>
        </div>
        
        <div class="text-center text-muted mt-3">
          <small>
            <i class="bi bi-info-circle me-1"></i>
            Showing <strong><?= $offset + 1 ?></strong> to <strong><?= min($offset + $limit, $totalRows) ?></strong> of <strong><?= $totalRows ?></strong> results
          </small>
        </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<!-- Document Viewer Modal -->
<div class="doc-modal" id="docModal">
  <div class="doc-modal-content">
    <div class="doc-modal-header">
      <h3 class="doc-modal-title" id="docModalTitle">
        <i class="bi bi-file-earmark-image me-2"></i>Document Viewer
      </h3>
      <div class="doc-modal-actions">
        <a id="docDownloadBtn" href="#" download class="doc-modal-btn download" title="Download Document">
          <i class="bi bi-download"></i>
        </a>
        <button class="doc-modal-btn close" onclick="closeDocModal()" title="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
    <div class="doc-modal-body" id="docModalBody">
      <div class="text-center">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Loading document...</p>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Handle thumbnail image loading errors with fallback paths
function handleImageError(imgElement) {
  const originalPath = imgElement.getAttribute('data-original-path');
  const currentSrc = imgElement.src;
  
  // Generate fallback URLs
  const fallbackUrls = [];
  
  if (originalPath) {
    // Try with /cargoAdmin/ prefix
    if (originalPath.startsWith('uploads/')) {
      fallbackUrls.push('/cargoAdmin/' + originalPath);
      fallbackUrls.push('cargoAdmin/' + originalPath);
    }
    
    // Try original path as-is
    if (!originalPath.startsWith('/')) {
      fallbackUrls.push('/' + originalPath);
    }
    fallbackUrls.push(originalPath);
  }
  
  // Try next URL that hasn't been tried yet
  for (let url of fallbackUrls) {
    const fullUrl = url.startsWith('http') ? url : (window.location.origin + (url.startsWith('/') ? url : '/' + url));
    if (currentSrc !== fullUrl) {
      imgElement.src = url;
      return; // Try this URL
    }
  }
  
  // All fallbacks failed, show placeholder
  imgElement.src = 'https://via.placeholder.com/300x200?text=Image+Not+Found';
  imgElement.style.opacity = '0.5';
  imgElement.parentElement.style.position = 'relative';
  
  // Add error overlay
  if (!imgElement.nextElementSibling || !imgElement.nextElementSibling.classList.contains('error-overlay')) {
    const overlay = document.createElement('div');
    overlay.className = 'error-overlay';
    overlay.innerHTML = '<small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Failed to load</small>';
    overlay.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 8px; border-radius: 4px; font-weight: bold;';
    imgElement.parentElement.appendChild(overlay);
  }
}

// Auto-dismiss alerts after 5 seconds with fade effect
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(function(alert) {
    setTimeout(function() {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    }, 5000);
  });

  // Add number counter animation to stat values
  const statValues = document.querySelectorAll('.stat-value');
  statValues.forEach(stat => {
    const finalValue = parseInt(stat.textContent);
    let currentValue = 0;
    const increment = finalValue / 50;
    const timer = setInterval(() => {
      currentValue += increment;
      if (currentValue >= finalValue) {
        stat.textContent = finalValue;
        clearInterval(timer);
      } else {
        stat.textContent = Math.floor(currentValue);
      }
    }, 20);
  });
});

// Enhanced Document Viewer with loading states
function viewDocument(docUrl, docType) {
  const modal = document.getElementById('docModal');
  const modalBody = document.getElementById('docModalBody');
  const modalTitle = document.getElementById('docModalTitle');
  const downloadBtn = document.getElementById('docDownloadBtn');
  
  modalTitle.innerHTML = `<i class="bi bi-file-earmark-image me-2"></i>${docType}`;
  downloadBtn.href = docUrl;
  downloadBtn.download = docType.replace(/\s+/g, '_') + '_' + docUrl.split('/').pop();
  
  modalBody.innerHTML = `
    <div class="text-center">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-3 text-muted">Loading ${docType.toLowerCase()}...</p>
    </div>
  `;
  
  const extension = docUrl.split('.').pop().toLowerCase();
  
  // Generate fallback URLs in case the primary URL fails
  function generateFallbackUrls(url) {
    const urls = [url];
    
    // If URL doesn't start with /, try adding it
    if (!url.startsWith('/')) {
      urls.push('/' + url);
    }
    
    // Try without leading slash
    if (url.startsWith('/')) {
      urls.push(url.substring(1));
    }
    
    // Try variations with cargoAdmin prefix
    const filename = url.split('/').pop();
    if (url.includes('uploads/verifications/')) {
      const pathPart = url.substring(url.indexOf('uploads/verifications/'));
      urls.push('/cargoAdmin/' + pathPart);
      urls.push('cargoAdmin/' + pathPart);
    }
    
    return [...new Set(urls)]; // Remove duplicates
  }
  
  function tryLoadImage(urls, index = 0) {
    if (index >= urls.length) {
      // All URLs failed
      modalBody.innerHTML = `
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <strong>Failed to load image.</strong> The file may not exist or the path is incorrect.
          <details class="mt-2">
            <summary>Tried paths:</summary>
            <ul class="text-start mt-2 small">
              ${urls.map(u => `<li><code>${u}</code></li>`).join('')}
            </ul>
          </details>
        </div>
      `;
      return;
    }
    
    const img = document.createElement('img');
    img.src = urls[index];
    img.alt = docType;
    img.style.maxWidth = '100%';
    
    img.onload = function() {
      modalBody.innerHTML = '';
      modalBody.appendChild(img);
      // Update download button with working URL
      downloadBtn.href = urls[index];
    };
    
    img.onerror = function() {
      // Try next URL
      tryLoadImage(urls, index + 1);
    };
  }
  
  if (extension === 'pdf') {
    const iframe = document.createElement('iframe');
    iframe.src = docUrl;
    iframe.style.width = '100%';
    iframe.style.height = '70vh';
    iframe.onload = function() {
      modalBody.innerHTML = '';
      modalBody.appendChild(iframe);
    };
    iframe.onerror = function() {
      modalBody.innerHTML = `
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Failed to load PDF. The file may not exist or the path is incorrect.
        </div>
      `;
    };
  } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
    const fallbackUrls = generateFallbackUrls(docUrl);
    tryLoadImage(fallbackUrls);
  } else {
    const iframe = document.createElement('iframe');
    iframe.src = docUrl;
    iframe.style.width = '100%';
    iframe.style.height = '70vh';
    modalBody.innerHTML = '';
    modalBody.appendChild(iframe);
  }
  
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeDocModal() {
  const modal = document.getElementById('docModal');
  modal.classList.remove('active');
  document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.getElementById('docModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeDocModal();
  }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeDocModal();
  }
});

// Suspension prompt helper
window.__cargoSuspendUser = function(form) {
  const reason = prompt('Enter suspension reason (required):');
  if (!reason || reason.trim() === '') {
    alert('Suspension reason is required.');
    return false;
  }
  form.querySelector('input[name="reason"]').value = reason.trim();
  return confirm('Suspend this user?');
};

// Add smooth scroll to highlighted row
window.addEventListener('load', function() {
  const highlightedRow = document.querySelector('tr[style*="background-color"]');
  if (highlightedRow) {
    setTimeout(() => {
      highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 500);
  }
});
</script>
<script src="include/notifications.js"></script>
</body>
</html>

<?php
// Close statement and connection if applicable
if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
$conn->close();
?>
