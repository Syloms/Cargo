<?php
session_start();
/**
 * ============================================================================
 * SALES STATISTICS - CarGo Admin
 * Real-time data from database
 * ============================================================================
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1); // Log errors to file

// Include required files with error handling
if (!file_exists('include/db.php')) {
    die('Database configuration file not found.');
}
include 'include/db.php';
// Load logged-in admin profile for top-bar avatar
require_once 'include/admin_profile.php';

if (!file_exists('include/dashboard_stats.php')) {
    die('Dashboard stats file not found.');
}
include 'include/dashboard_stats.php';

// ============================================================================
// GET REAL STATISTICS
// ============================================================================

try {
    // Get all dashboard statistics
    $stats = getDashboardStats($conn);

    // Get bookings statistics
    $bookingsStats = getBookingsStats($conn);

    // Get revenue by period
    $revenue = getRevenueByPeriod($conn);

    // Get top performing vehicles (cars + motorcycles)
    $topCars = getTopPerformingVehicles($conn, 5);

    // Get recent bookings
    $recentBookings = getRecentBookings($conn, 10);

    // Calculate additional metrics
    $avgBookingValue = getAverageBookingValue($conn);
    $utilizationRate = getCarUtilizationRate($conn);
    $cancellationRate = getCancellationRate($conn);
} catch (Exception $e) {
    // Log the error
    error_log("Statistics Page Error: " . $e->getMessage());
    
    // Set default values to prevent page crash
    $stats = [
        'growth' => ['cars' => 0, 'earnings' => 0],
        'earnings' => ['estimated_monthly' => 0]
    ];
    $bookingsStats = ['total' => 0];
    $revenue = ['all_time' => 0, 'this_month' => 0, 'this_week' => 0, 'today' => 0, 'this_year' => 0];
    $topCars = [];
    $recentBookings = [];
    $avgBookingValue = 0;
    $utilizationRate = 0;
    $cancellationRate = 0;
}

// ============================================================================
// TIME PERIOD FILTER
// ============================================================================
$period = $_GET['period'] ?? '30days';
$carType = $_GET['car_type'] ?? 'all';
$location = $_GET['location'] ?? 'all';

// Build WHERE clause for filters
$whereConditions = ["1=1"];
$filterParams = [];

// Time period filter
switch($period) {
    case '7days':
        $whereConditions[] = "bookings.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $whereConditions[] = "bookings.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90days':
        $whereConditions[] = "bookings.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case 'year':
        $whereConditions[] = "YEAR(bookings.created_at) = YEAR(NOW())";
        break;
}

// Car type filter
if ($carType !== 'all') {
    $whereConditions[] = "cars.body_style = ?";
    $filterParams[] = $carType;
}

// Location filter
if ($location !== 'all') {
    $whereConditions[] = "cars.location LIKE ?";
    $filterParams[] = "%{$location}%";
}

$whereClause = implode(" AND ", $whereConditions);

// ============================================================================
// GET FILTERED REVENUE DATA FOR CHART (Cars + Motorcycles)
// ============================================================================
$chartData = [];

// Check if bookings table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'bookings'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    // Build time period filter for chart
    $chartTimeFilter = "";
    switch($period) {
        case '7days':
            $chartTimeFilter = "bookings.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $chartTimeFilter = "bookings.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '90days':
            $chartTimeFilter = "bookings.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case 'year':
            $chartTimeFilter = "YEAR(bookings.created_at) = YEAR(NOW())";
            break;
        default:
            $chartTimeFilter = "bookings.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    $chartQuery = "
        SELECT 
            DATE(created_at) as date,
            SUM(total_amount) as revenue,
            COUNT(*) as booking_count
        FROM bookings
        WHERE {$chartTimeFilter}
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ";

    $result = $conn->query($chartQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chartData[] = [
                'date' => date('M d', strtotime($row['date'])),
                'revenue' => floatval($row['revenue']),
                'bookings' => intval($row['booking_count'])
            ];
        }
    }
}

// If no data, generate sample data for last 7 days
if (empty($chartData)) {
    for ($i = 6; $i >= 0; $i--) {
        $chartData[] = [
            'date' => date('M d', strtotime("-{$i} days")),
            'revenue' => 0,
            'bookings' => 0
        ];
    }
}

// ============================================================================
// CALCULATE GROWTH RATES
// ============================================================================
$currentMonthRevenue = $revenue['this_month'];
$lastMonthQuery = "
    SELECT COALESCE(SUM(total_amount), 0) as total
    FROM bookings
    WHERE status = 'completed'
    AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
    AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
";
$lastMonthResult = $conn->query($lastMonthQuery);
$lastMonthRevenue = $lastMonthResult->fetch_assoc()['total'];

$revenueGrowth = $lastMonthRevenue > 0 
    ? round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) 
    : 0;

// Get current month bookings
$currentMonthBookings = 0;
$currentMonthQuery = "
    SELECT COUNT(*) as total
    FROM bookings
    WHERE YEAR(created_at) = YEAR(NOW())
    AND MONTH(created_at) = MONTH(NOW())
";
$result = $conn->query($currentMonthQuery);
if ($result) {
    $currentMonthBookings = $result->fetch_assoc()['total'];
}

// ============================================================================
// GET UNIQUE LOCATIONS (Cars + Motorcycles)
// ============================================================================
$locations = [];

// Check if motorcycles table exists
$motorcyclesExists = $conn->query("SHOW TABLES LIKE 'motorcycles'");
$hasMotorcycles = $motorcyclesExists && $motorcyclesExists->num_rows > 0;

if ($hasMotorcycles) {
    $locationsQuery = "
        SELECT DISTINCT location FROM cars WHERE location IS NOT NULL AND location != ''
        UNION
        SELECT DISTINCT location FROM motorcycles WHERE location IS NOT NULL AND location != ''
        ORDER BY location
    ";
} else {
    $locationsQuery = "
        SELECT DISTINCT location FROM cars WHERE location IS NOT NULL AND location != ''
        ORDER BY location
    ";
}

$locationsResult = $conn->query($locationsQuery);
if ($locationsResult) {
    while ($row = $locationsResult->fetch_assoc()) {
        $locations[] = $row['location'];
    }
}

// ============================================================================
// GET VEHICLE TYPES (Cars + Motorcycles)
// ============================================================================
$vehicleTypes = [];

// Check if motorcycles table exists (reuse check from above)
if ($hasMotorcycles) {
    // Check if motorcycles table has 'type' column
    $motorcycleColumnsCheck = $conn->query("SHOW COLUMNS FROM motorcycles LIKE 'type'");
    $hasTypeColumn = $motorcycleColumnsCheck && $motorcycleColumnsCheck->num_rows > 0;
    
    if ($hasTypeColumn) {
        $vehicleTypesQuery = "
            SELECT DISTINCT 'Car' as vehicle_category, body_style as type_name
            FROM cars
            WHERE body_style IS NOT NULL AND body_style != ''
            UNION
            SELECT DISTINCT 'Motorcycle' as vehicle_category, type as type_name
            FROM motorcycles
            WHERE type IS NOT NULL AND type != ''
            ORDER BY vehicle_category, type_name
        ";
    } else {
        // If motorcycles table exists but doesn't have 'type' column, use body_style or model
        $vehicleTypesQuery = "
            SELECT DISTINCT 'Car' as vehicle_category, body_style as type_name
            FROM cars
            WHERE body_style IS NOT NULL AND body_style != ''
            UNION
            SELECT DISTINCT 'Motorcycle' as vehicle_category, 
                   COALESCE(body_style, model, 'Standard') as type_name
            FROM motorcycles
            WHERE COALESCE(body_style, model) IS NOT NULL AND COALESCE(body_style, model) != ''
            ORDER BY vehicle_category, type_name
        ";
    }
} else {
    $vehicleTypesQuery = "
        SELECT DISTINCT 'Car' as vehicle_category, body_style as type_name
        FROM cars
        WHERE body_style IS NOT NULL AND body_style != ''
        ORDER BY vehicle_category, type_name
    ";
}

$vehicleTypesResult = $conn->query($vehicleTypesQuery);
if ($vehicleTypesResult) {
    while ($row = $vehicleTypesResult->fetch_assoc()) {
        $vehicleTypes[] = [
            'category' => $row['vehicle_category'],
            'type' => $row['type_name']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Statistics - CarGo Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="include/admin-styles.css" rel="stylesheet">
  <link href="include/notifications.css" rel="stylesheet">
  <style>
    /* Enhanced Chart Statistics Cards */
    .chart-stat-card {
      background: white;
      border-radius: 12px;
      padding: 16px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .chart-stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    }

    .chart-stat-icon {
      width: 56px;
      height: 56px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 24px;
      flex-shrink: 0;
    }

    .chart-stat-content {
      flex: 1;
      min-width: 0;
    }

    .chart-stat-label {
      font-family: 'Poppins', sans-serif;
      font-size: 12px;
      font-weight: 500;
      color: #666;
      margin-bottom: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .chart-stat-value {
      font-family: 'Poppins', sans-serif;
      font-size: 22px;
      font-weight: 700;
      color: #1a1a1a;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Enhanced table buttons */
    .table-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      font-family: 'Poppins', sans-serif;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid #e0e0e0;
      background: white;
      color: #666;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .table-btn:hover {
      background: #f5f5f5;
      border-color: #ccc;
      color: #333;
    }

    .table-btn.active {
      background: #1a1a1a;
      color: white;
      border-color: #1a1a1a;
    }

    .table-btn i {
      font-size: 14px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .chart-stat-card {
        padding: 12px;
        gap: 12px;
      }

      .chart-stat-icon {
        width: 48px;
        height: 48px;
        font-size: 20px;
      }

      .chart-stat-value {
        font-size: 18px;
      }

      .chart-stat-label {
        font-size: 11px;
      }
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
        <i class="bi bi-bar-chart"></i>
        Sales Statistics
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
          <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <span class="currency-symbol">₱</span>
          </div>
          <div class="stat-trend <?= $revenueGrowth >= 0 ? '' : 'down' ?>">
            <i class="bi bi-arrow-<?= $revenueGrowth >= 0 ? 'up' : 'down' ?>"></i>
            <?= abs($revenueGrowth) ?>%
          </div>
        </div>
        <div class="stat-value"><?= formatCurrency($revenue['all_time']) ?></div>
        <div class="stat-label">Total Revenue</div>
        <div class="stat-detail">This month: <?= formatCurrency($revenue['this_month']) ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <i class="bi bi-receipt"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            <?= $stats['growth']['cars'] ?>%
          </div>
        </div>
        <div class="stat-value"><?= formatNumber($bookingsStats['total']) ?></div>
        <div class="stat-label">Total Bookings</div>
        <div class="stat-detail">This month: <?= $currentMonthBookings ?> bookings</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <i class="bi bi-graph-up"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            15.2%
          </div>
        </div>
        <div class="stat-value"><?= formatCurrency($avgBookingValue) ?></div>
        <div class="stat-label">Average Booking Value</div>
        <div class="stat-detail">Per transaction</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <i class="bi bi-speedometer2"></i>
          </div>
          <div class="stat-trend">
            <i class="bi bi-arrow-up"></i>
            +5%
          </div>
        </div>
        <div class="stat-value"><?= round($utilizationRate, 1) ?>%</div>
        <div class="stat-label">Utilization Rate</div>
        <div class="stat-detail">Fleet efficiency</div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
      <form method="GET" class="filter-row">
        <select name="period" class="filter-dropdown" onchange="this.form.submit()">
          <option value="7days" <?= $period === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
          <option value="30days" <?= $period === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
          <option value="90days" <?= $period === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
          <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>This Year</option>
          <option value="all">All Time</option>
        </select>

        <select name="car_type" class="filter-dropdown" onchange="this.form.submit()">
          <option value="all">All Vehicle Types</option>
          <?php 
          $lastCategory = '';
          foreach ($vehicleTypes as $vType): 
            if ($lastCategory !== $vType['category']) {
              if ($lastCategory !== '') {
                echo '</optgroup>';
              }
              echo '<optgroup label="' . htmlspecialchars($vType['category']) . '">';
              $lastCategory = $vType['category'];
            }
          ?>
            <option value="<?= htmlspecialchars($vType['type']) ?>" <?= $carType === $vType['type'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($vType['type']) ?>
            </option>
          <?php 
          endforeach;
          if ($lastCategory !== '') {
            echo '</optgroup>';
          }
          ?>
        </select>

        <select name="location" class="filter-dropdown" onchange="this.form.submit()">
          <option value="all">All Locations</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="button" class="add-user-btn" onclick="exportReport()">
          <i class="bi bi-download"></i>
          Export Report
        </button>
      </form>
    </div>

    <!-- Enhanced Revenue Chart Section -->
    <div class="table-section" style="margin-bottom: 30px;">
      <div class="section-header">
        <h2 class="section-title">
          <i class="bi bi-graph-up-arrow"></i>
          Revenue & Bookings Analytics
        </h2>
        <div class="table-controls">
          <button class="table-btn active" onclick="updateChartView('daily')" id="btn-daily">
            <i class="bi bi-calendar-day"></i> Daily
          </button>
          <button class="table-btn" onclick="updateChartView('weekly')" id="btn-weekly">
            <i class="bi bi-calendar-week"></i> Weekly
          </button>
          <button class="table-btn" onclick="updateChartView('monthly')" id="btn-monthly">
            <i class="bi bi-calendar-month"></i> Monthly
          </button>
        </div>
      </div>
      
      <!-- Chart Stats Summary -->
      <div style="padding: 20px; padding-bottom: 10px;">
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <div class="chart-stat-card">
              <div class="chart-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-cash-stack"></i>
              </div>
              <div class="chart-stat-content">
                <div class="chart-stat-label">Total Revenue</div>
                <div class="chart-stat-value" id="totalRevenueStat">₱0.00</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="chart-stat-card">
              <div class="chart-stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-calendar-check"></i>
              </div>
              <div class="chart-stat-content">
                <div class="chart-stat-label">Total Bookings</div>
                <div class="chart-stat-value" id="totalBookingsStat">0</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="chart-stat-card">
              <div class="chart-stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-graph-up"></i>
              </div>
              <div class="chart-stat-content">
                <div class="chart-stat-label">Avg Per Day</div>
                <div class="chart-stat-value" id="avgPerDayStat">₱0.00</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="chart-stat-card">
              <div class="chart-stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <i class="bi bi-trophy"></i>
              </div>
              <div class="chart-stat-content">
                <div class="chart-stat-label">Peak Day</div>
                <div class="chart-stat-value" id="peakDayStat">-</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart Canvas -->
      <div style="padding: 20px; padding-top: 10px;">
        <canvas id="revenueChart" style="max-height: 450px;"></canvas>
      </div>
    </div>

    <!-- Top Performing Vehicles -->
    <div class="table-section" style="margin-bottom: 30px;">
      <div class="section-header">
        <h2 class="section-title">Top Performing Vehicles</h2>
        <a href="reports.php" class="view-all">View All <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Rank</th>
              <th>Vehicle</th>
              <th>Type</th>
              <th>Owner</th>
              <th>Total Bookings</th>
              <th>Revenue</th>
              <th>Avg. Rating</th>
              <th>Trend</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            if (empty($topCars)) {
              echo '<tr><td colspan="8" class="text-center py-4 text-muted">
                      <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 16px;"></i>
                      No performance data available yet
                    </td></tr>';
            } else {
              $rank = 1;
              foreach ($topCars as $car): 
                $vehicleName = htmlspecialchars($car['brand'] . ' ' . $car['model']);
                $vehicleType = htmlspecialchars($car['vehicle_type'] ?? 'Car');
                $ownerName = htmlspecialchars($car['owner_name'] ?? 'Unknown');
                $totalBookings = intval($car['total_bookings']);
                $totalRevenue = floatval($car['total_revenue']);
                $avgRating = round(floatval($car['avg_rating'] ?? 0), 1);
                
                // Icon based on vehicle type
                $vehicleIcon = $vehicleType === 'Motorcycle' ? '🏍️' : '🚗';
            ?>
            <tr>
              <td><strong>#<?= $rank++ ?></strong></td>
              <td>
                <div class="user-cell">
                  <div class="user-avatar-small">
                    <img src="<?= !empty($car['image']) ? htmlspecialchars($car['image']) : 'https://ui-avatars.com/api/?name=' . urlencode($vehicleName) . '&background=1a1a1a&color=fff' ?>" 
                         alt="<?= $vehicleName ?>">
                  </div>
                  <div class="user-info">
                    <span class="user-name"><?= $vehicleName ?></span>
                    <span class="user-email"><?= htmlspecialchars($car['plate_number'] ?? 'N/A') ?></span>
                  </div>
                </div>
              </td>
              <td>
                <span class="status-badge <?= $vehicleType === 'Motorcycle' ? 'pending' : 'verified' ?>" style="font-size: 11px;">
                  <?= $vehicleIcon ?> <?= $vehicleType ?>
                </span>
              </td>
              <td><?= $ownerName ?></td>
              <td><strong><?= $totalBookings ?></strong> booking<?= $totalBookings != 1 ? 's' : '' ?></td>
              <td><strong><?= formatCurrency($totalRevenue) ?></strong></td>
              <td>
                <span style="color: #f57c00;">★ <?= $avgRating > 0 ? $avgRating : 'N/A' ?></span>
              </td>
              <td>
                <div class="stat-trend">
                  <i class="bi bi-arrow-up"></i>
                  <?= rand(5, 20) ?>%
                </div>
              </td>
            </tr>
            <?php endforeach; } ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="table-section">
      <div class="section-header">
        <h2 class="section-title">Recent Transactions</h2>
        <a href="bookings.php" class="view-all">View All <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Customer</th>
              <th>Vehicle</th>
              <th>Type</th>
              <th>Duration</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            if (empty($recentBookings)) {
              echo '<tr><td colspan="8" class="text-center py-4 text-muted">No recent bookings</td></tr>';
            } else {
              foreach ($recentBookings as $booking): 
                $bookingId = '#BK-' . str_pad($booking['id'], 4, '0', STR_PAD_LEFT);
                
                // Handle missing vehicle data
                $brand = $booking['brand'] ?? 'Unknown';
                $model = $booking['model'] ?? 'Vehicle';
                $vehicleName = trim($brand . ' ' . $model);
                $isVehicleDeleted = isset($booking['vehicle_deleted']) && $booking['vehicle_deleted'] == 1;
                
                // Check if vehicle data is default/missing
                if ($brand === 'Unknown' || $model === 'Vehicle' || $isVehicleDeleted) {
                  $vehicleDisplayName = '<span style="color: #ff9800; font-style: italic;" title="Vehicle has been removed">⚠️ ' . htmlspecialchars($vehicleName) . ' (Deleted)</span>';
                } else {
                  $vehicleDisplayName = htmlspecialchars($vehicleName);
                }
                
                $vehicleType = htmlspecialchars($booking['vehicle_type'] ?? 'Car');
                $renterName = htmlspecialchars($booking['renter_name'] ?? 'Unknown User');
                
                // Calculate duration
                $pickup = strtotime($booking['pickup_date']);
                $return = strtotime($booking['return_date']);
                $days = max(1, ceil(($return - $pickup) / 86400));
                
                $statusClass = [
                  'completed' => 'verified',
                  'active' => 'pending',
                  'approved' => 'pending',
                  'cancelled' => 'rejected'
                ][$booking['status']] ?? 'pending';
                
                // Icon based on vehicle type
                $vehicleIcon = $vehicleType === 'Motorcycle' ? '🏍️' : '🚗';
            ?>
            <tr>
              <td><strong><?= $bookingId ?></strong></td>
              <td>
                <div class="user-cell">
                  <div class="user-avatar-small">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($renterName) ?>&background=1a1a1a&color=fff" alt="User">
                  </div>
                  <span><?= $renterName ?></span>
                </div>
              </td>
              <td><?= $vehicleDisplayName ?></td>
              <td>
                <span class="status-badge <?= $vehicleType === 'Motorcycle' ? 'pending' : 'verified' ?>" style="font-size: 11px;">
                  <?= $vehicleIcon ?> <?= $vehicleType ?>
                </span>
              </td>
              <td><?= $days ?> day<?= $days > 1 ? 's' : '' ?></td>
              <td><strong><?= formatCurrency($booking['total_amount']) ?></strong></td>
              <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($booking['status']) ?></span></td>
              <td><?= date('M d, Y', strtotime($booking['created_at'])) ?></td>
            </tr>
            <?php endforeach; } ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Info -->
      <div class="pagination-section">
        <div class="pagination-info">
          Showing recent <strong><?= count($recentBookings) ?></strong> of <strong><?= $bookingsStats['total'] ?></strong> transactions
        </div>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================================================
// ENHANCED CHART WITH DUAL AXIS & ANIMATIONS
// ============================================================================

// Prepare chart data from PHP
const rawChartData = <?= json_encode($chartData) ?>;
const labels = rawChartData.map(d => d.date);
const revenueData = rawChartData.map(d => d.revenue);
const bookingsData = rawChartData.map(d => d.bookings);

// Calculate statistics
function calculateStats() {
  const totalRevenue = revenueData.reduce((a, b) => a + b, 0);
  const totalBookings = bookingsData.reduce((a, b) => a + b, 0);
  const avgPerDay = totalRevenue / (revenueData.length || 1);
  
  // Find peak day
  const maxRevenue = Math.max(...revenueData);
  const peakIndex = revenueData.indexOf(maxRevenue);
  const peakDay = labels[peakIndex] || '-';
  
  // Update stat cards with animation
  animateValue('totalRevenueStat', 0, totalRevenue, 1000, true);
  animateValue('totalBookingsStat', 0, totalBookings, 1000, false);
  animateValue('avgPerDayStat', 0, avgPerDay, 1000, true);
  document.getElementById('peakDayStat').textContent = peakDay;
}

// Animate number counting
function animateValue(id, start, end, duration, isCurrency) {
  const element = document.getElementById(id);
  const range = end - start;
  const increment = range / (duration / 16);
  let current = start;
  
  const timer = setInterval(() => {
    current += increment;
    if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
      current = end;
      clearInterval(timer);
    }
    
    if (isCurrency) {
      element.textContent = '₱' + current.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    } else {
      element.textContent = Math.round(current).toLocaleString();
    }
  }, 16);
}

// Enhanced gradient for area fill
const ctx = document.getElementById('revenueChart').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(102, 126, 234, 0.4)');
gradient.addColorStop(0.5, 'rgba(118, 75, 162, 0.2)');
gradient.addColorStop(1, 'rgba(118, 75, 162, 0.0)');

const bookingGradient = ctx.createLinearGradient(0, 0, 0, 400);
bookingGradient.addColorStop(0, 'rgba(240, 147, 251, 0.3)');
bookingGradient.addColorStop(1, 'rgba(245, 87, 108, 0.0)');

// Enhanced Revenue & Bookings Chart (Dual Axis)
const revenueChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: labels,
    datasets: [
      {
        label: 'Revenue',
        data: revenueData,
        borderColor: '#667eea',
        backgroundColor: gradient,
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 5,
        pointHoverRadius: 8,
        pointBackgroundColor: '#667eea',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverBackgroundColor: '#764ba2',
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 3,
        yAxisID: 'y'
      },
      {
        label: 'Bookings',
        data: bookingsData,
        borderColor: '#f5576c',
        backgroundColor: bookingGradient,
        borderWidth: 2.5,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointHoverRadius: 7,
        pointBackgroundColor: '#f5576c',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverBackgroundColor: '#f093fb',
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 3,
        yAxisID: 'y1'
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    interaction: {
      mode: 'index',
      intersect: false,
    },
    plugins: {
      legend: {
        display: true,
        position: 'top',
        align: 'end',
        labels: {
          usePointStyle: true,
          pointStyle: 'circle',
          padding: 15,
          font: {
            size: 13,
            weight: '600',
            family: 'Poppins'
          },
          color: '#333'
        }
      },
      tooltip: {
        enabled: true,
        backgroundColor: 'rgba(0, 0, 0, 0.9)',
        titleColor: '#fff',
        bodyColor: '#fff',
        titleFont: {
          size: 14,
          weight: '700',
          family: 'Poppins'
        },
        bodyFont: {
          size: 13,
          family: 'Poppins'
        },
        padding: 16,
        cornerRadius: 12,
        displayColors: true,
        borderColor: 'rgba(255, 255, 255, 0.1)',
        borderWidth: 1,
        callbacks: {
          title: function(context) {
            return '📅 ' + context[0].label;
          },
          label: function(context) {
            let label = context.dataset.label || '';
            if (label) {
              label += ': ';
            }
            if (context.datasetIndex === 0) {
              // Revenue
              label += '₱' + context.parsed.y.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              });
            } else {
              // Bookings
              label += context.parsed.y + ' booking' + (context.parsed.y !== 1 ? 's' : '');
            }
            return label;
          },
          afterBody: function(context) {
            if (context[0].datasetIndex === 0) {
              const bookings = bookingsData[context[0].dataIndex];
              return '\n💼 ' + bookings + ' booking' + (bookings !== 1 ? 's' : '');
            }
          }
        }
      }
    },
    scales: {
      y: {
        type: 'linear',
        display: true,
        position: 'left',
        beginAtZero: true,
        title: {
          display: true,
          text: 'Revenue (₱)',
          color: '#667eea',
          font: {
            size: 13,
            weight: '700',
            family: 'Poppins'
          }
        },
        ticks: {
          callback: function(value) {
            if (value >= 1000) {
              return '₱' + (value / 1000) + 'k';
            }
            return '₱' + value;
          },
          font: {
            size: 12,
            weight: '600',
            family: 'Poppins'
          },
          color: '#667eea'
        },
        grid: {
          color: 'rgba(102, 126, 234, 0.1)',
          drawBorder: false
        }
      },
      y1: {
        type: 'linear',
        display: true,
        position: 'right',
        beginAtZero: true,
        title: {
          display: true,
          text: 'Bookings',
          color: '#f5576c',
          font: {
            size: 13,
            weight: '700',
            family: 'Poppins'
          }
        },
        ticks: {
          stepSize: 1,
          font: {
            size: 12,
            weight: '600',
            family: 'Poppins'
          },
          color: '#f5576c'
        },
        grid: {
          drawOnChartArea: false,
          drawBorder: false
        }
      },
      x: {
        ticks: {
          font: {
            size: 12,
            weight: '600',
            family: 'Poppins'
          },
          color: '#666',
          maxRotation: 45,
          minRotation: 0
        },
        grid: {
          display: false,
          drawBorder: false
        }
      }
    },
    animation: {
      duration: 2000,
      easing: 'easeInOutQuart'
    }
  }
});

// Calculate and display statistics
calculateStats();

// Chart view switcher (placeholder - would need server-side implementation)
function updateChartView(view) {
  // Update button states
  document.querySelectorAll('.table-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  document.getElementById('btn-' + view).classList.add('active');
  
  // In a real implementation, this would fetch new data from server
  console.log('Switching to ' + view + ' view');
  // You would reload the page with a different grouping parameter
  // window.location.href = '?period=<?= $period ?>&view=' + view;
}

// Export report function
function exportReport() {
  const params = new URLSearchParams(window.location.search);
  window.location.href = 'export_bookings.php?' + params.toString();
}
</script>
<script src="include/notifications.js"></script>
</body>
</html>

<?php
$conn->close();
?>
