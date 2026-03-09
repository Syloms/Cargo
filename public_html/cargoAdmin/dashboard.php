<?php
session_start();
include 'include/db.php';
include 'include/admin_profile.php';
include 'include/dashboard_stats.php';

// ============================================================================
// GET ALL DASHBOARD STATISTICS USING HELPER FUNCTIONS
// ============================================================================

// Get all dashboard statistics
$stats = getDashboardStats($conn);

// Extract statistics for easier access
$earnings = $stats['earnings'];
$bookings = $stats['cars']; // Since you're using cars as bookings
$users = $stats['users'];
$issues = $stats['notifications'];
$verifications = $stats['verifications'];
$growth = $stats['growth'];

// Get recent cars for the table
$query = getRecentCars($conn, 5);

// Set values for display
$totalEarnings = $earnings['estimated_monthly'];
$earningsGrowth = $growth['earnings'];

$totalBookingsCount = $bookings['active'];
$bookingsGrowth = $growth['cars'];

$totalUsersCount = $users['total'];
$usersGrowth = $growth['users'];

$pendingIssues = $issues['unread'];
$issuesChange = $growth['notifications'];

$topCars = getTopPerformingCars($conn, 5);

// Get revenue breakdown
$revenue = getRevenueByPeriod($conn);
//echo formatCurrency($revenue['this_month']); // This month's revenue

// Get recent bookings
$recentBookings = getRecentBookings($conn, 10);

// Get metrics
$avgBookingValue = getAverageBookingValue($conn);
$utilizationRate = getCarUtilizationRate($conn);
$cancellationRate = getCancellationRate($conn);

// ============================================================================
// WELCOME CARD CTA (dynamic)
// ============================================================================
$ctaHref = 'bookings.php';
$ctaLabel = 'Go to Bookings';

// Prioritize what admins usually need to act on first
if (!empty($verifications['pending']) && (int)$verifications['pending'] > 0) {
  $ctaHref = 'users.php?verification=pending';
  $ctaLabel = 'Review Verifications';
} elseif (!empty($bookings['pending']) && (int)$bookings['pending'] > 0) {
  // In this dashboard stats, $bookings refers to cars listing stats (pending car listings)
  $ctaHref = 'get_cars_admin.php?status=pending';
  $ctaLabel = 'Review Pending Listings';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CarGo Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="include/admin-styles.css" rel="stylesheet">
  <link href="include/notifications.css" rel="stylesheet">
</head>
<body>

<div class="dashboard-wrapper">
  <?php include('include/sidebar.php'); ?>

  <main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
      <h1 class="page-title">Dashboard Overview</h1>
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

    <!-- Welcome Card -->
    <div class="welcome-card">
      <div class="welcome-content">
        <h2>Welcome back, <?= htmlspecialchars($currentAdminName) ?>.</h2>
        <p>
          Here’s a quick snapshot: <strong><?= formatNumber($users['total']) ?></strong> users,
          <strong><?= formatNumber($bookings['active']) ?></strong> active listings,
          and <strong><?= formatNumber($verifications['pending']) ?></strong> pending verifications.
          Keep things moving by reviewing items that need your attention.
        </p>
        <div class="welcome-actions">
          <a class="welcome-btn" href="<?= htmlspecialchars($ctaHref) ?>">
            <?= htmlspecialchars($ctaLabel) ?>
          </a>
          <a class="welcome-btn secondary" href="notifications.php">
            View Notifications
          </a>
        </div>
      </div>
      <svg class="welcome-illustration" viewBox="0 0 200 150" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="100" cy="100" r="40" fill="#1a1a1a" opacity="0.1"/>
        <path d="M60 100 Q100 60 140 100" stroke="#1a1a1a" stroke-width="4" fill="none"/>
        <circle cx="70" cy="100" r="8" fill="#1a1a1a"/>
        <circle cx="130" cy="100" r="8" fill="#1a1a1a"/>
        <rect x="80" y="80" width="40" height="30" rx="5" fill="#1a1a1a"/>
      </svg>
    </div>

    <!-- Stats Grid with REAL DATA from Helper Functions -->
    <div class="stats-grid">
      <!-- Total Earnings -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">
            <span class="currency-symbol">₱</span>
          </div>
          <div class="stat-trend <?= $earningsGrowth >= 0 ? '' : 'down' ?>">
            <i class="bi bi-arrow-<?= $earningsGrowth >= 0 ? 'up' : 'down' ?>"></i>
            <?= abs($earningsGrowth) ?>%
          </div>
        </div>
        <div class="stat-value"><?= formatCurrency($totalEarnings) ?></div>
        <div class="stat-label">Estimated Monthly Earnings</div>
        <div class="stat-detail">Weekly: <?= formatCurrency($earnings['estimated_weekly']) ?></div>
      </div>

      <!-- Total Cars/Bookings -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-car-front-fill"></i>
          </div>
          <div class="stat-trend <?= $bookingsGrowth >= 0 ? '' : 'down' ?>">
            <i class="bi bi-arrow-<?= $bookingsGrowth >= 0 ? 'up' : 'down' ?>"></i>
            <?= abs($bookingsGrowth) ?>%
          </div>
        </div>
        <div class="stat-value"><?= formatNumber($bookings['total']) ?></div>
        <div class="stat-label">Total Cars Listed</div>
        <div class="stat-detail">Active: <?= formatNumber($bookings['active']) ?> | Pending: <?= formatNumber($bookings['pending']) ?></div>
      </div>

      <!-- Total Users -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-people"></i>
          </div>
          <div class="stat-trend <?= $usersGrowth >= 0 ? '' : 'down' ?>">
            <i class="bi bi-arrow-<?= $usersGrowth >= 0 ? 'up' : 'down' ?>"></i>
            <?= abs($usersGrowth) ?>%
          </div>
        </div>
        <div class="stat-value"><?= formatNumber($totalUsersCount) ?></div>
        <div class="stat-label">Total Users</div>
        <div class="stat-detail">Owners: <?= formatNumber($users['owners']) ?> | Renters: <?= formatNumber($users['renters']) ?></div>
      </div>

      <!-- Notifications/Issues -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-bell-fill"></i>
          </div>
          <div class="stat-trend <?= $issuesChange >= 0 ? 'down' : '' ?>">
            <i class="bi bi-arrow-<?= $issuesChange >= 0 ? 'up' : 'down' ?>"></i>
            <?= abs($issuesChange) ?>%
          </div>
        </div>
        <div class="stat-value"><?= formatNumber($pendingIssues) ?></div>
        <div class="stat-label">Unread Notifications</div>
        <div class="stat-detail">Total: <?= formatNumber($issues['total']) ?></div>
      </div>

      <!-- Pending Verifications -->
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-shield-check"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-hourglass-split"></i>
            Review
          </div>
        </div>
        <div class="stat-value"><?= formatNumber($verifications['pending']) ?></div>
        <div class="stat-label">Pending Verification</div>
        <div class="stat-detail">Approved: <?= formatNumber($verifications['approved']) ?> | Rejected: <?= formatNumber($verifications['rejected']) ?></div>
      </div>
    </div>

    <!-- Additional Summary Cards -->
    <div class="row mb-4">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Platform Statistics</h5>
          </div>
          <div class="card-body">
            <div class="row text-center">
              <div class="col-6 border-end">
                <h3 class="text-success"><?= formatNumber($verifications['approved']) ?></h3>
                <p class="text-muted mb-0">Verified Users</p>
              </div>
              <div class="col-6">
                <h3 class="text-info"><?= formatNumber($bookings['active']) ?></h3>
                <p class="text-muted mb-0">Active Listings</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Agusan del Sur Coverage</h5>
          </div>
          <div class="card-body">
            <div class="row text-center">
              <div class="col-6 border-end">
                <h3 class="text-primary">14</h3>
                <p class="text-muted mb-0">Municipalities</p>
              </div>
              <div class="col-6">
                <h3 class="text-warning"><?= formatNumber($users['owners']) ?></h3>
                <p class="text-muted mb-0">Car Owners</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Car Listings Table -->
    <div class="table-section">
      <div class="section-header">
        <h2 class="section-title">Recent Car Listings</h2>
        <a href="get_cars_admin.php" class="view-all">
          View All
          <i class="bi bi-arrow-right"></i>
        </a>
      </div>

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Owner</th>
            <th>Car Details</th>
            <th>Plate Number</th>
            <th>Status</th>
            <th>Image</th>
            <th>Documents</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if ($query->num_rows === 0) {
            echo '<tr><td colspan="8" class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h4>No cars found</h4>
                    <p>No car listings available yet</p>
                  </td></tr>';
          }

          $num = 1;
          while($row = $query->fetch_assoc()) { ?>
          <tr>
            <td><?= $num++ ?></td>
            <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
            <td>
              <strong><?= htmlspecialchars($row['brand']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($row['model']) ?></small>
            </td>
            <td><strong><?= htmlspecialchars($row['plate_number']) ?></strong></td>
            <td>
              <span class="status-badge <?= $row['status'] ?>">
                <?= ucfirst($row['status']) ?>
              </span>
            </td>
            <td>
              <?php if(!empty($row['image'])) { ?>
                <img src="<?= htmlspecialchars($row['image']) ?>" 
                     class="car-thumb" 
                     onclick="viewCarImage('<?= htmlspecialchars($row['image']) ?>', '<?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?>')" 
                     alt="Car">
              <?php } else { ?>
                <span class="text-muted">No Image</span>
              <?php } ?>
            </td>
            <td>
              <a href="javascript:void(0)" 
                 onclick="viewDocument('<?= htmlspecialchars($row['official_receipt']) ?>', 'Official Receipt')"
                 class="doc-btn or" title="Official Receipt">OR</a>
              <a href="javascript:void(0)" 
                 onclick="viewDocument('<?= htmlspecialchars($row['certificate_of_registration']) ?>', 'Certificate of Registration')"
                 class="doc-btn cr" title="Certificate of Registration">CR</a>
            </td>
            <td>
              <div class="action-buttons">
                <form method="POST" action="update_car_status.php" style="display: contents;">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  
                  <?php if($row['status'] !== 'approved') { ?>
                    <button name="status" value="approved" class="action-btn approve ps-5 pe-5">
                      <i class="bi bi-check-lg"></i> Approve
                    </button>
                  <?php } ?>

                  <?php if($row['status'] !== 'rejected') { ?>
                    <button type="button" 
                            class="action-btn reject rejectBtn ps-5 pe-5" 
                            data-id="<?= $row['id'] ?>">
                      <i class="bi bi-x-lg "></i> Reject
                    </button>
                  <?php } ?>
                </form>
              </div>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Image Modal -->
<div class="image-modal" id="imageModal">
  <div class="image-modal-content">
    <div class="image-modal-header">
      <button class="modal-action-btn download" onclick="downloadImage()" title="Download Image">
        <i class="bi bi-download"></i>
      </button>
      <button class="modal-action-btn close" onclick="closeImageModal()" title="Close">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <img id="modalImage" src="" alt="Car Image">
    <div class="image-modal-footer" id="modalCaption"></div>
  </div>
</div>

<!-- Document Modal -->
<div class="doc-modal" id="docModal">
  <div class="doc-modal-content">
    <div class="doc-modal-header">
      <h3 class="doc-modal-title" id="docModalTitle">Document Viewer</h3>
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
      <!-- Document content will be loaded here -->
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentImageUrl = '';

function viewCarImage(imageUrl, carName) {
  const modal = document.getElementById('imageModal');
  const modalImg = document.getElementById('modalImage');
  const caption = document.getElementById('modalCaption');
  
  currentImageUrl = imageUrl;
  modal.classList.add('active');
  modalImg.src = imageUrl;
  caption.textContent = carName || 'Car Image';
  document.body.style.overflow = 'hidden';
}

function closeImageModal() {
  const modal = document.getElementById('imageModal');
  modal.classList.remove('active');
  document.body.style.overflow = 'auto';
}

function downloadImage() {
  if (!currentImageUrl) return;
  const link = document.createElement('a');
  link.href = currentImageUrl;
  const filename = currentImageUrl.split('/').pop() || 'car-image.jpg';
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function viewDocument(docUrl, docType) {
  const modal = document.getElementById('docModal');
  const modalBody = document.getElementById('docModalBody');
  const modalTitle = document.getElementById('docModalTitle');
  const downloadBtn = document.getElementById('docDownloadBtn');
  
  modalTitle.textContent = docType;
  downloadBtn.href = docUrl;
  downloadBtn.download = docType.replace(/\s+/g, '_') + '_' + docUrl.split('/').pop();
  
  modalBody.innerHTML = '';
  
  const extension = docUrl.split('.').pop().toLowerCase();
  
  if (extension === 'pdf') {
    const iframe = document.createElement('iframe');
    iframe.src = docUrl;
    modalBody.appendChild(iframe);
  } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
    const img = document.createElement('img');
    img.src = docUrl;
    img.alt = docType;
    modalBody.appendChild(img);
  } else {
    const iframe = document.createElement('iframe');
    iframe.src = docUrl;
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

document.getElementById('imageModal').addEventListener('click', function(e) {
  if (e.target === this) closeImageModal();
});

document.getElementById('docModal').addEventListener('click', function(e) {
  if (e.target === this) closeDocModal();
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeImageModal();
    closeDocModal();
  }
});
</script>
<script src="include/notifications.js"></script>
</body>
</html>