<!-- Sidebar -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <style>
  /* Sidebar Styles */
    .sidebar {
      width: 260px;
      background: white;
      padding: 30px 20px;
      box-shadow: 2px 0 10px rgba(0,0,0,0.05);
      position: fixed;
      height: 100vh;
      overflow-y: auto;
      transition: transform 0.3s ease;
      z-index: 1000;
    }

    /* Mobile Menu Toggle Button */
    .mobile-menu-toggle {
      display: none;
      position: fixed;
      top: 20px;
      left: 20px;
      z-index: 1001;
      background: #1a1a1a;
      color: white;
      border: none;
      width: 45px;
      height: 45px;
      border-radius: 12px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      transition: all 0.3s ease;
    }

    .mobile-menu-toggle:hover {
      background: #333;
      transform: scale(1.05);
    }

    .mobile-menu-toggle i {
      font-size: 20px;
    }

    /* Sidebar Overlay for Mobile */
    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
      opacity: 1;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 992px) {
      .mobile-menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.active {
        transform: translateX(0);
      }

      .sidebar-overlay.active {
        display: block;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 280px;
        padding: 20px 15px;
      }

      .logo-section {
        margin-bottom: 30px;
      }

      .menu-section {
        margin-bottom: 25px;
      }
    }

    .logo-section {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 40px;
      padding: 0 10px;
    }

    .logo-icon {
      width: 40px;
      height: 40px;
      background: #1a1a1a;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
      font-weight: 800;
    }

    .logo-text {
      font-size: 20px;
      font-weight: 800;
      color: #1a1a1a;
      letter-spacing: 2px;
    }

    .menu-section {
      margin-bottom: 35px;
    }

    .menu-label {
      font-size: 11px;
      font-weight: 600;
      color: #999;
      text-transform: uppercase;
      letter-spacing: 1px;
      padding: 0 10px;
      margin-bottom: 12px;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 16px;
      border-radius: 12px;
      color: #666;
      text-decoration: none;
      transition: all 0.3s ease;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 6px;
      position: relative;
    }

    .menu-item:hover {
      background: #f5f5f5;
      color: #1a1a1a;
    }

    .menu-item.active {
      background: #1a1a1a;
      color: white;
    }

    .menu-item i {
      font-size: 18px;
      width: 20px;
      text-align: center;
    }

    .menu-badge {
      position: absolute;
      right: 12px;
      background: #dc3545;
      color: white;
      font-size: 10px;
      font-weight: 600;
      padding: 2px 6px;
      border-radius: 10px;
      min-width: 18px;
      text-align: center;
    }

    .menu-item.active .menu-badge {
      background: #fff;
      color: #1a1a1a;
    }

    .logout-item {
      color: #dc3545;
      margin-top: 20px;
    }

    .logout-item:hover {
      background: #fff5f5;
      color: #dc3545;
    }
</style>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
  <i class="bi bi-list"></i>
</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="logo-section">
    <div class="logo-icon">C</div>
    <div class="logo-text">CARGO</div>
  </div>
  
  <!-- MAIN NAVIGATION -->
  <div class="menu-section">
    <div class="menu-label">Main Menu</div>
    <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
      <i class="bi bi-grid"></i>
      <span>Dashboard</span>
    </a>
    <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
      <i class="bi bi-person"></i>
      <span>User Management</span>
      <?php
      // Get pending user verifications count
      if (isset($conn)) {
        $user_verif_query = "SELECT COUNT(*) as count FROM user_verifications WHERE status = 'pending'";
        $user_verif_result = mysqli_query($conn, $user_verif_query);
        if ($user_verif_result) {
          $user_verif_data = mysqli_fetch_assoc($user_verif_result);
          if ($user_verif_data['count'] > 0) {
            echo '<span class="menu-badge">' . $user_verif_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
  </div>

  <!-- VEHICLES MANAGEMENT -->
  <div class="menu-section">
    <div class="menu-label">Vehicles</div>
    <a href="get_cars_admin.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'get_cars_admin.php' ? 'active' : ''; ?>">
      <i class="bi bi-car-front"></i>
      <span>Car Listings</span>
      <?php
      // Get pending car listings count
      if (isset($conn)) {
        $car_pending_query = "SELECT COUNT(*) as count FROM cars WHERE status = 'pending'";
        $car_pending_result = mysqli_query($conn, $car_pending_query);
        if ($car_pending_result) {
          $car_pending_data = mysqli_fetch_assoc($car_pending_result);
          if ($car_pending_data['count'] > 0) {
            echo '<span class="menu-badge">' . $car_pending_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
    <a href="get_motorcycle_admin.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'get_motorcycle_admin.php' ? 'active' : ''; ?>">
      <i class="bi bi-bicycle"></i>
      <span>Motorcycle Listings</span>
      <?php
      // Get pending motorcycle listings count
      if (isset($conn)) {
        $moto_pending_query = "SELECT COUNT(*) as count FROM motorcycles WHERE status = 'pending'";
        $moto_pending_result = mysqli_query($conn, $moto_pending_query);
        if ($moto_pending_result) {
          $moto_pending_data = mysqli_fetch_assoc($moto_pending_result);
          if ($moto_pending_data['count'] > 0) {
            echo '<span class="menu-badge">' . $moto_pending_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
  </div>

  <!-- TRANSACTIONS -->
  <div class="menu-section">
    <div class="menu-label">Transactions</div>
    <a href="bookings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''; ?>">
      <i class="bi bi-book"></i>
      <span>Bookings</span>
      <?php
      // Get pending bookings count
      if (isset($conn)) {
        $booking_pending_query = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
        $booking_pending_result = mysqli_query($conn, $booking_pending_query);
        if ($booking_pending_result) {
          $booking_pending_data = mysqli_fetch_assoc($booking_pending_result);
          if ($booking_pending_data['count'] > 0) {
            echo '<span class="menu-badge">' . $booking_pending_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
    <a href="payment.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'payment.php' ? 'active' : ''; ?>">
      <i class="bi bi-credit-card"></i>
      <span>Payments</span>
      <?php
      // Get pending payment verification count (regular + late fees)
      if (isset($conn)) {
        // Regular payments pending verification
        $regular_pending = mysqli_fetch_assoc(mysqli_query($conn,
          "SELECT COUNT(*) AS c FROM payments p
           WHERE p.payment_status='pending'
           AND NOT EXISTS (
               SELECT 1 FROM payment_transactions pt 
               WHERE pt.booking_id = p.booking_id 
               AND pt.reference_id = p.payment_reference
               AND pt.transaction_type = 'late_fee_payment'
           )"
        ))['c'];
        
        // Late fee payments pending verification
        $latefee_pending = mysqli_fetch_assoc(mysqli_query($conn,
          "SELECT COUNT(*) AS c FROM late_fee_payments 
           WHERE payment_status='pending'"
        ))['c'];
        
        $total_pending = $regular_pending + $latefee_pending;
        
        if ($total_pending > 0) {
          echo '<span class="menu-badge">' . $total_pending . '</span>';
        }
      }
      ?>
    </a>
  </div>

  <!-- FINANCIAL MANAGEMENT -->
  <div class="menu-section">
    <div class="menu-label">Financial</div>
    
    <a href="overdue_management.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'overdue_management.php' ? 'active' : ''; ?>">
      <i class="bi bi-exclamation-triangle"></i>
      <span>Overdue Rentals</span>
      <?php
      // Get overdue count
      if (isset($conn)) {
        $overdue_query = "SELECT COUNT(*) as count FROM bookings 
                         WHERE status = 'approved' 
                         AND CONCAT(return_date, ' ', return_time) < NOW()";
        $overdue_result = mysqli_query($conn, $overdue_query);
        if ($overdue_result) {
          $overdue_data = mysqli_fetch_assoc($overdue_result);
          if ($overdue_data['count'] > 0) {
            echo '<span class="menu-badge" style="background: #dc3545;">' . $overdue_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
    
    <a href="refunds.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'refunds.php' ? 'active' : ''; ?>">
      <i class="bi bi-arrow-counterclockwise"></i>
      <span>Refunds</span>
      <?php
      // Get pending refunds count
      if (isset($conn)) {
        $refund_query = "SELECT COUNT(*) as count FROM refunds WHERE status = 'pending'";
        $refund_result = mysqli_query($conn, $refund_query);
        if ($refund_result) {
          $refund_data = mysqli_fetch_assoc($refund_result);
          if ($refund_data['count'] > 0) {
            echo '<span class="menu-badge">' . $refund_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
    <a href="payouts.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'payouts.php' ? 'active' : ''; ?>">
      <i class="bi bi-cash-stack"></i>
      <span>Payouts</span>
      <?php
      // Get pending payouts count
      if (isset($conn)) {
        $payout_query = "SELECT COUNT(*) as count FROM payouts WHERE status IN ('pending', 'processing')";
        $payout_result = mysqli_query($conn, $payout_query);
        if ($payout_result) {
          $payout_data = mysqli_fetch_assoc($payout_result);
          if ($payout_data['count'] > 0) {
            echo '<span class="menu-badge">' . $payout_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
    <a href="escrow.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'escrow.php' ? 'active' : ''; ?>">
      <i class="bi bi-shield-lock"></i>
      <span>Escrow Management</span>
      <?php
      // Get held escrow count
      if (isset($conn)) {
        $escrow_query = "SELECT COUNT(*) as count FROM escrow WHERE status = 'held'";
        $escrow_result = mysqli_query($conn, $escrow_query);
        if ($escrow_result) {
          $escrow_data = mysqli_fetch_assoc($escrow_result);
          if ($escrow_data['count'] > 0) {
            echo '<span class="menu-badge">' . $escrow_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>

    <a href="security_deposits.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'security_deposits.php' ? 'active' : ''; ?>">
      <i class="bi bi-shield-check"></i>
      <span>Security Deposits</span>
      <?php
      // Get held security deposits count
      if (isset($conn)) {
        $deposits_query = "SELECT COUNT(*) as count FROM bookings WHERE security_deposit_status = 'held' AND status = 'completed'";
        $deposits_result = mysqli_query($conn, $deposits_query);
        if ($deposits_result) {
          $deposits_data = mysqli_fetch_assoc($deposits_result);
          if ($deposits_data['count'] > 0) {
            echo '<span class="menu-badge">' . $deposits_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>

    <a href="insurance.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'insurance.php' ? 'active' : ''; ?>">
      <i class="bi bi-shield-check"></i>
      <span>Insurance Management</span>
      <?php
      // Get pending claims count
      if (isset($conn)) {
        $insurance_query = "SELECT COUNT(*) as count FROM insurance_claims WHERE status IN ('submitted', 'under_review')";
        $insurance_result = mysqli_query($conn, $insurance_query);
        if ($insurance_result) {
          $insurance_data = mysqli_fetch_assoc($insurance_result);
          if ($insurance_data['count'] > 0) {
            echo '<span class="menu-badge">' . $insurance_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
  </div>

  <!-- REPORTS -->
  <div class="menu-section">
    <div class="menu-label">Report</div>
    <a href="calendar.php" 
       class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
      <i class="bi bi-calendar3"></i>
      <span>Event Calendar</span>
    </a>
    <a href="statistics.php" 
       class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : ''; ?>">
      <i class="bi bi-bar-chart"></i>
      <span>Sales Statistics</span>
    </a>
    <a href="reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
      <i class="bi bi-file-text"></i>
      <span>User Reports</span>
      <?php
      // Get unresolved reports count (pending + under_review)
      if (isset($conn)) {
        $reports_query = "SELECT COUNT(*) as count FROM reports WHERE status IN ('pending', 'under_review')";
        $reports_result = mysqli_query($conn, $reports_query);
        if ($reports_result) {
          $reports_data = mysqli_fetch_assoc($reports_result);
          if ($reports_data['count'] > 0) {
            echo '<span class="menu-badge">' . $reports_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
  </div>

  <!-- SYSTEM -->
  <div class="menu-section">
    <div class="menu-label">System</div>
    <a href="notifications.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
      <i class="bi bi-bell"></i>
      <span>Notifications</span>
      <?php
      // Get unread admin notifications count
      if (isset($conn)) {
        $notif_query = "SELECT COUNT(*) as count FROM admin_notifications WHERE read_status = 'unread'";
        $notif_result = mysqli_query($conn, $notif_query);
        if ($notif_result) {
          $notif_data = mysqli_fetch_assoc($notif_result);
          if ($notif_data['count'] > 0) {
            echo '<span class="menu-badge">' . $notif_data['count'] . '</span>';
          }
        }
      }
      ?>
    </a>
    <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
      <i class="bi bi-gear"></i>
      <span>Settings</span>
    </a>
    <a href="logout.php" class="menu-item logout-item">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </a>
  </div>
</aside>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Mobile Sidebar Toggle Functionality
(function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Toggle sidebar on button click
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            
            // Change icon based on state
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('bi-list');
                icon.classList.add('bi-x');
            } else {
                icon.classList.remove('bi-x');
                icon.classList.add('bi-list');
            }
        });
    }

    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            
            // Reset icon
            const icon = mobileMenuToggle.querySelector('i');
            icon.classList.remove('bi-x');
            icon.classList.add('bi-list');
        });
    }

    // Close sidebar when clicking a menu item on mobile
    const menuItems = sidebar.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                
                // Reset icon
                const icon = mobileMenuToggle.querySelector('i');
                icon.classList.remove('bi-x');
                icon.classList.add('bi-list');
            }
        });
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                
                // Reset icon
                const icon = mobileMenuToggle.querySelector('i');
                icon.classList.remove('bi-x');
                icon.classList.add('bi-list');
            }
        }, 250);
    });
})();

// Real-time badge update system
(function() {
    const BADGE_UPDATE_INTERVAL = 30000; // Update every 30 seconds
    
    // Map of badge IDs to their menu items
    const badgeMap = {
        'users': 'users.php',
        'cars': 'get_cars_admin.php',
        'motorcycles': 'get_motorcycle_admin.php',
        'bookings': 'bookings.php',
        'payments': 'payment.php',
        'overdue': 'overdue_management.php',
        'refunds': 'refunds.php',
        'payouts': 'payouts.php',
        'escrow': 'escrow.php',
        'escrow_releases': 'escrow_release_manager.php',
        'insurance': 'insurance.php',
        'reports': 'reports.php',
        'notifications': 'notifications.php'
    };
    
    function updateBadges() {
        fetch('api/get_sidebar_badge_counts.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.badges) {
                    Object.keys(data.badges).forEach(key => {
                        const count = data.badges[key];
                        const menuHref = badgeMap[key];
                        
                        if (menuHref) {
                            // Find the menu item by href
                            const menuItem = document.querySelector(`a.menu-item[href="${menuHref}"]`);
                            
                            if (menuItem) {
                                // Find existing badge or create new one
                                let badge = menuItem.querySelector('.menu-badge');
                                
                                if (count > 0) {
                                    if (!badge) {
                                        // Create new badge
                                        badge = document.createElement('span');
                                        badge.className = 'menu-badge';
                                        
                                        // Special styling for overdue
                                        if (key === 'overdue') {
                                            badge.style.background = '#dc3545';
                                        }
                                        
                                        menuItem.appendChild(badge);
                                    }
                                    
                                    // Update badge count with animation
                                    if (badge.textContent !== count.toString()) {
                                        badge.style.transform = 'scale(1.2)';
                                        setTimeout(() => {
                                            badge.style.transform = 'scale(1)';
                                        }, 200);
                                    }
                                    
                                    badge.textContent = count;
                                } else {
                                    // Remove badge if count is 0
                                    if (badge) {
                                        badge.remove();
                                    }
                                }
                            }
                        }
                    });
                    
                    console.log('Sidebar badges updated:', data.timestamp);
                }
            })
            .catch(error => {
                console.error('Failed to update badges:', error);
            });
    }
    
    // Update badges immediately on load
    updateBadges();
    
    // Set up periodic updates
    setInterval(updateBadges, BADGE_UPDATE_INTERVAL);
    
    // Update when page gains focus (user returns to tab)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateBadges();
        }
    });
})();
</script>

</body>
</html>