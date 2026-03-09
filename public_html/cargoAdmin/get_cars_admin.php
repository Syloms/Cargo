<?php
session_start();
include "include/db.php";

// Get filter values
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';

// Escape for safety
$search = $conn->real_escape_string($search);
$status = $conn->real_escape_string($status);

// Pagination settings
$limit = 5; // rows per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Base query - include extra_images
$sql = "
    SELECT cars.*, users.fullname 
    FROM cars 
    JOIN users ON users.id = cars.owner_id 
    WHERE 1
";

// Search filter
if (!empty($search)) {
    $sql .= " AND (
        cars.brand LIKE '%$search%' OR
        cars.model LIKE '%$search%' OR
        cars.plate_number LIKE '%$search%' OR
        users.fullname LIKE '%$search%'
    )";
}

// Status filter
if ($status !== "all") {
    $sql .= " AND cars.status = '$status'";
}

// Count total rows for pagination
$countQuery = $conn->query($sql);
$totalRows = $countQuery->num_rows;
$totalPages = ceil($totalRows / $limit);

// Final query with sorting + limit
$sql .= " ORDER BY cars.created_at DESC LIMIT $limit OFFSET $offset";

$query = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Car Approval Management | CarGo Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="include/admin-styles.css" rel="stylesheet">
  <link href="include/notifications.css" rel="stylesheet">
  <link href="include/modal-theme-standardized.css" rel="stylesheet">

</head>
<body>

<div class="dashboard-wrapper">
  <?php include('include/sidebar.php'); ?>
  <?php include('include/admin_profile.php'); ?>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
      <h1 class="page-title">
        <i class="bi bi-car-front-fill"></i>
        Car Approval Management
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

    <!-- Stats Cards -->
    <div class="stats-grid">
      <?php
      $statsQuery = $conn->query("
        SELECT 
          COUNT(*) as total,
          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
          SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
          SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM cars
      ");
      $stats = $statsQuery->fetch_assoc();
      ?>
      
      <div class="stat-card">
        <div class="stat-label">Total Cars</div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-icon stat-total">
          <i class="bi bi-car-front"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-icon stat-pending">
          <i class="bi bi-clock-history"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Approved</div>
        <div class="stat-value"><?= $stats['approved'] ?></div>
        <div class="stat-icon stat-approved">
          <i class="bi bi-check-circle"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Rejected</div>
        <div class="stat-value"><?= $stats['rejected'] ?></div>
        <div class="stat-icon stat-rejected">
          <i class="bi bi-x-circle"></i>
        </div>
      </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
      <form method="GET" class="search-form">
        <input 
          type="text" 
          name="search" 
          class="search-input" 
          placeholder="Search by owner, brand, model, or plate number..." 
          value="<?= htmlspecialchars($search) ?>">
        
        <select name="status" class="filter-select">
          <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
          <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
          <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>

        <button type="submit" class="search-btn">
          <i class="bi bi-search"></i> Search
        </button>
        
        <a href="?" class="reset-btn">
          <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
      </form>
    </div>

    <!-- Cars Table -->
    <div class="table-section">
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
                    <p>Try adjusting your search or filter criteria</p>
                  </td></tr>';
          }

          $num = $offset + 1;
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
              <?php 
              $extraImages = !empty($row['extra_images']) ? json_decode($row['extra_images'], true) : [];
              $allImages = array_filter(array_merge(
                [$row['image'] ?? ''], 
                is_array($extraImages) ? $extraImages : []
              ));
              
              if(!empty($allImages)) { 
                $mainImage = $allImages[0];
              ?>
                <div style="position: relative; display: inline-block;">
                  <img src="<?= htmlspecialchars($mainImage) ?>" 
                    class="car-thumb"
                    onclick="viewCarGallery(<?= htmlspecialchars(json_encode($allImages)) ?>, '<?= htmlspecialchars($row['brand'].' '.$row['model']) ?>', 0)"
                    alt="Car"
                    style="cursor: pointer;">
                  <?php if(count($allImages) > 1) { ?>
                    <span style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.7); color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                      +<?= count($allImages) - 1 ?>
                    </span>
                  <?php } ?>
                </div>
              <?php } else { ?>
                <span class="text-muted">No Image</span>
              <?php } ?>
            </td>
            <td>
  <a href="javascript:void(0)"
   class="doc-btn or"
   onclick="viewDocument('<?= htmlspecialchars($row['official_receipt']) ?>','Official Receipt')">
   OR
</a>

<a href="javascript:void(0)"
   class="doc-btn cr"
   onclick="viewDocument('<?= htmlspecialchars($row['certificate_of_registration']) ?>','Certificate of Registration')">
   CR
</a>
</td>

            <td>
              <div class="action-buttons">
                <form method="POST" action="update_car_status.php" style="display: contents;">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  
                  <?php if($row['status'] !== 'approved') { ?>
                    <button name="status" value="approved" class="action-btn approve pe-5 ps-5">
                      <i class="bi bi-check-lg"></i> Approve
                    </button>
                  <?php } ?>

                  <?php if($row['status'] !== 'rejected') { ?>
                    <button type="button" 
                            class="action-btn reject rejectBtn pe-5 ps-5" 
                            data-id="<?= $row['id'] ?>">
                      <i class="bi bi-x-lg"></i> Reject
                    </button>
                  <?php } ?>
                </form>
              </div>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination-wrapper">
        <ul class="pagination">
          <!-- Previous Button -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>">
              <i class="bi bi-chevron-left"></i>
            </a>
          </li>

          <!-- Page Numbers -->
          <?php 
          $start = max(1, $page - 2);
          $end = min($totalPages, $page + 2);
          
          for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>">
                <?= $i ?>
              </a>
            </li>
          <?php endfor; ?>

          <!-- Next Button -->
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>">
              <i class="bi bi-chevron-right"></i>
            </a>
          </li>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" action="update_car_status.php">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-x-circle text-danger"></i> Reject Car Listing
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="rejectCarId">
        <p class="text-muted mb-3">Provide a clear reason so the owner understands why their car was rejected.</p>
        <textarea 
          class="form-control" 
          name="remarks" 
          placeholder="Enter rejection reason..." 
          style="height:120px;" 
          required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="status" value="rejected" class="btn btn-danger ">
          <i class="bi bi-x-lg"></i> Confirm Rejection
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Enhanced Image Gallery Modal -->
<div class="image-modal" id="imageModal">
  <div class="image-modal-content">
    <div class="image-modal-header">
      <span id="imageCounter" style="color: white; font-weight: 600; font-size: 14px;"></span>
      <div>
        <button class="modal-action-btn download" onclick="downloadImage()" title="Download Image">
          <i class="bi bi-download"></i>
        </button>
        <button class="modal-action-btn close" onclick="closeImageModal()" title="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
    
    <!-- Navigation Arrows -->
    <button class="gallery-nav prev" onclick="navigateGallery(-1)" id="prevBtn" style="display: none;">
      <i class="bi bi-chevron-left"></i>
    </button>
    <button class="gallery-nav next" onclick="navigateGallery(1)" id="nextBtn" style="display: none;">
      <i class="bi bi-chevron-right"></i>
    </button>
    
    <img id="modalImage" src="" alt="Car Image">
    <div class="image-modal-footer" id="modalCaption"></div>
    
    <!-- Thumbnail Strip -->
    <div class="thumbnail-strip" id="thumbnailStrip" style="display: none;"></div>
  </div>
</div>

<style>
.gallery-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(0, 0, 0, 0.6);
  border: none;
  color: white;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  font-size: 24px;
  cursor: pointer;
  transition: all 0.3s;
  z-index: 1001;
}

.gallery-nav:hover {
  background: rgba(0, 0, 0, 0.8);
  transform: translateY(-50%) scale(1.1);
}

.gallery-nav.prev {
  left: 20px;
}

.gallery-nav.next {
  right: 20px;
}

.thumbnail-strip {
  display: flex;
  gap: 10px;
  padding: 15px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 10px;
  margin-top: 15px;
  overflow-x: auto;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
}

.thumbnail-strip img {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 8px;
  cursor: pointer;
  opacity: 0.6;
  transition: all 0.3s;
  border: 2px solid transparent;
}

.thumbnail-strip img:hover {
  opacity: 1;
  transform: scale(1.1);
}

.thumbnail-strip img.active {
  opacity: 1;
  border-color: #fff;
}
</style>





<!-- Document Modal -->
<div class="doc-modal" id="docModal">
  <div class="doc-modal-content">
    <div class="doc-modal-header">
      <h3 class="doc-modal-title" id="docModalTitle">Document Viewer</h3>
      <div class="doc-modal-actions">
        <a id="docDownloadBtn" class="doc-modal-btn download" download>
          <i class="bi bi-download"></i>
        </a>
        <button class="doc-modal-btn close" onclick="closeDocModal()">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
    <div class="doc-modal-body" id="docModalBody"></div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

  let currentImageUrl = '';
  let currentDocUrl = '';

  /* ===============================
     REJECT MODAL
  =============================== */
  document.querySelectorAll(".rejectBtn").forEach(btn => {
    btn.addEventListener("click", function () {
      const input = document.getElementById("rejectCarId");
      const modalEl = document.getElementById("rejectModal");

      if (!input || !modalEl) return;

      input.value = btn.dataset.id;
      new bootstrap.Modal(modalEl).show();
    });
  });

  /* ===============================
     IMAGE GALLERY MODAL
  =============================== */
  let galleryImages = [];
  let currentGalleryIndex = 0;
  
  window.viewCarGallery = function (images, carName, startIndex = 0) {
    const modal = document.getElementById("imageModal");
    const img = document.getElementById("modalImage");
    const caption = document.getElementById("modalCaption");
    const counter = document.getElementById("imageCounter");
    const thumbnailStrip = document.getElementById("thumbnailStrip");
    const prevBtn = document.getElementById("prevBtn");
    const nextBtn = document.getElementById("nextBtn");

    if (!modal || !img) return;

    galleryImages = Array.isArray(images) ? images : [images];
    currentGalleryIndex = startIndex;
    
    // Show navigation only if multiple images
    if (galleryImages.length > 1) {
      prevBtn.style.display = "block";
      nextBtn.style.display = "block";
      thumbnailStrip.style.display = "flex";
      
      // Build thumbnails
      thumbnailStrip.innerHTML = galleryImages.map((imgUrl, idx) => 
        `<img src="${imgUrl}" onclick="jumpToImage(${idx})" class="${idx === currentGalleryIndex ? 'active' : ''}" />`
      ).join('');
    } else {
      prevBtn.style.display = "none";
      nextBtn.style.display = "none";
      thumbnailStrip.style.display = "none";
    }
    
    updateGalleryImage();
    caption.textContent = carName || "Car Image";
    modal.classList.add("active");
    document.body.style.overflow = "hidden";
  };
  
  window.navigateGallery = function(direction) {
    currentGalleryIndex += direction;
    
    if (currentGalleryIndex < 0) {
      currentGalleryIndex = galleryImages.length - 1;
    } else if (currentGalleryIndex >= galleryImages.length) {
      currentGalleryIndex = 0;
    }
    
    updateGalleryImage();
  };
  
  window.jumpToImage = function(index) {
    currentGalleryIndex = index;
    updateGalleryImage();
  };
  
  function updateGalleryImage() {
    const img = document.getElementById("modalImage");
    const counter = document.getElementById("imageCounter");
    const thumbnailStrip = document.getElementById("thumbnailStrip");
    
    currentImageUrl = galleryImages[currentGalleryIndex];
    img.src = currentImageUrl;
    
    if (galleryImages.length > 1) {
      counter.textContent = `${currentGalleryIndex + 1} / ${galleryImages.length}`;
      
      // Update active thumbnail
      thumbnailStrip.querySelectorAll('img').forEach((thumb, idx) => {
        thumb.classList.toggle('active', idx === currentGalleryIndex);
      });
    } else {
      counter.textContent = '';
    }
  }
  
  // Legacy support for single image
  window.viewCarImage = function (imageUrl, carName) {
    viewCarGallery([imageUrl], carName, 0);
  };

  window.closeImageModal = function () {
    const modal = document.getElementById("imageModal");
    if (!modal) return;

    modal.classList.remove("active");
    document.body.style.overflow = "auto";
    galleryImages = [];
    currentGalleryIndex = 0;
  };

  window.downloadImage = function () {
    if (!currentImageUrl) return;

    const a = document.createElement("a");
    a.href = currentImageUrl;
    a.download = currentImageUrl.split("/").pop() || "image.jpg";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  const imageModal = document.getElementById("imageModal");
  if (imageModal) {
    imageModal.addEventListener("click", e => {
      if (e.target === imageModal) closeImageModal();
    });
  }

  /* ===============================
     DOCUMENT MODAL (OR / CR)
  =============================== */
  window.viewDocument = function (docUrl, docType) {
    const modal = document.getElementById("docModal");
    const body = document.getElementById("docModalBody");
    const title = document.getElementById("docModalTitle");
    const downloadBtn = document.getElementById("docDownloadBtn");

    if (!modal || !body || !downloadBtn) return;

    currentDocUrl = docUrl;
    title.textContent = docType;
    downloadBtn.href = docUrl;
    downloadBtn.download = docUrl.split("/").pop();

    body.innerHTML = "";

    const ext = docUrl.split(".").pop().toLowerCase();

    if (ext === "pdf") {
      const iframe = document.createElement("iframe");
      iframe.src = docUrl;
      iframe.style.width = "100%";
      iframe.style.height = "100%";
      iframe.style.border = "none";
      body.appendChild(iframe);
    } else {
      const img = document.createElement("img");
      img.src = docUrl;
      img.style.maxWidth = "100%";
      img.style.borderRadius = "8px";
      body.appendChild(img);
    }

    modal.classList.add("active");
    document.body.style.overflow = "hidden";
  };

  window.closeDocModal = function () {
    const modal = document.getElementById("docModal");
    if (!modal) return;

    modal.classList.remove("active");
    document.body.style.overflow = "auto";
  };

  const docModal = document.getElementById("docModal");
  if (docModal) {
    docModal.addEventListener("click", e => {
      if (e.target === docModal) closeDocModal();
    });
  }

  /* ===============================
     KEYBOARD NAVIGATION
  =============================== */
  document.addEventListener("keydown", e => {
    const imageModal = document.getElementById("imageModal");
    if (imageModal && imageModal.classList.contains("active")) {
      if (e.key === "ArrowLeft") {
        navigateGallery(-1);
      } else if (e.key === "ArrowRight") {
        navigateGallery(1);
      } else if (e.key === "Escape") {
        closeImageModal();
      }
    } else if (e.key === "Escape") {
      closeDocModal();
    }
  });

});
</script>



<script src="include/notifications.js"></script>
</body>
</html>