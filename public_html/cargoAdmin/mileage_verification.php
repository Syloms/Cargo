<?php
session_start();
include "include/db.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$vehicle_filter = $_GET['vehicle_type'] ?? 'all';

// Build WHERE clause
$where_conditions = ["b.status IN ('active', 'completed')"];

if ($status_filter === 'pending') {
    $where_conditions[] = "b.odometer_end IS NOT NULL AND b.mileage_verified_by IS NULL";
} elseif ($status_filter === 'verified') {
    $where_conditions[] = "b.mileage_verified_by IS NOT NULL";
} elseif ($status_filter === 'has_excess') {
    $where_conditions[] = "b.excess_mileage > 0";
} elseif ($status_filter === 'needs_review') {
    $where_conditions[] = "ABS(b.actual_mileage - b.gps_distance) / b.actual_mileage * 100 > 20";
}

if ($vehicle_filter !== 'all') {
    $where_conditions[] = "b.vehicle_type = '$vehicle_filter'";
}

$where_clause = implode(" AND ", $where_conditions);

// Get bookings with mileage data
$query = "
    SELECT 
        b.id,
        b.vehicle_type,
        b.car_id,
        b.pickup_date,
        b.return_date,
        b.odometer_start,
        b.odometer_end,
        b.odometer_start_photo,
        b.odometer_end_photo,
        b.actual_mileage,
        b.allowed_mileage,
        b.excess_mileage,
        b.excess_mileage_fee,
        b.gps_distance,
        b.mileage_verified_by,
        b.mileage_verified_at,
        b.excess_mileage_paid,
        u.fullname AS renter_name,
        o.fullname AS owner_name,
        CASE 
            WHEN b.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model)
            ELSE CONCAT(m.brand, ' ', m.model)
        END AS vehicle_name,
        DATEDIFF(b.return_date, b.pickup_date) + 1 AS rental_days,
        CASE 
            WHEN b.actual_mileage IS NOT NULL AND b.gps_distance IS NOT NULL AND b.actual_mileage > 0
            THEN ABS(b.actual_mileage - b.gps_distance) / b.actual_mileage * 100
            ELSE NULL 
        END AS discrepancy_percentage
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN users o ON b.owner_id = o.id
    LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
    LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
    WHERE $where_clause
    ORDER BY 
        CASE WHEN b.mileage_verified_by IS NULL THEN 0 ELSE 1 END,
        b.odometer_end_timestamp DESC
";

$result = $conn->query($query);
$bookings = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) AS total_bookings,
        SUM(CASE WHEN mileage_verified_by IS NULL AND odometer_end IS NOT NULL THEN 1 ELSE 0 END) AS pending_verification,
        SUM(CASE WHEN excess_mileage > 0 THEN 1 ELSE 0 END) AS with_excess,
        SUM(CASE WHEN excess_mileage_paid = 1 THEN 1 ELSE 0 END) AS excess_paid,
        SUM(excess_mileage_fee) AS total_excess_revenue
    FROM bookings
    WHERE status IN ('active', 'completed')
    AND odometer_start IS NOT NULL
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mileage Verification - CARGO Admin</title>
    <link rel="stylesheet" href="include/admin-styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .mileage-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: 600;
            color: #333;
        }
        .stat-card.pending { border-left: 4px solid #f59e0b; }
        .stat-card.excess { border-left: 4px solid #ef4444; }
        .stat-card.paid { border-left: 4px solid #10b981; }
        .stat-card.revenue { border-left: 4px solid #3b82f6; }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
        }

        .bookings-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .bookings-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .bookings-table th {
            background: #f3f4f6;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #374151;
        }
        .bookings-table td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }
        .bookings-table tr:hover {
            background: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge.verified { background: #d1fae5; color: #065f46; }
        .badge.pending { background: #fed7aa; color: #92400e; }
        .badge.excess { background: #fecaca; color: #991b1b; }
        .badge.warning { background: #fef3c7; color: #92400e; }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 {
            margin: 0;
        }
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        .close:hover { color: #333; }

        .odometer-photos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .photo-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .photo-container img {
            width: 100%;
            height: 250px;
            object-fit: contain;
            background: #f9fafb;
        }
        .photo-label {
            padding: 10px;
            background: #f3f4f6;
            font-weight: 600;
            font-size: 13px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-weight: 600;
            color: #6b7280;
        }
        .info-value {
            color: #111827;
        }
    </style>
</head>
<body>
    <?php include "include/sidebar.php"; ?>

    <div class="main-content">
        <?php include "include/header.php"; ?>

        <div class="content-wrapper">
            <h1 style="margin-bottom: 30px;">🔍 Mileage Verification</h1>

            <!-- Statistics -->
            <div class="mileage-stats">
                <div class="stat-card pending">
                    <h3>Pending Verification</h3>
                    <div class="value"><?= $stats['pending_verification'] ?></div>
                </div>
                <div class="stat-card excess">
                    <h3>With Excess Mileage</h3>
                    <div class="value"><?= $stats['with_excess'] ?></div>
                </div>
                <div class="stat-card paid">
                    <h3>Excess Paid</h3>
                    <div class="value"><?= $stats['excess_paid'] ?></div>
                </div>
                <div class="stat-card revenue">
                    <h3>Total Excess Revenue</h3>
                    <div class="value">₱<?= number_format($stats['total_excess_revenue'], 2) ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <label style="font-weight: 600;">Filter by:</label>
                <select id="statusFilter" onchange="applyFilters()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                    <option value="verified" <?= $status_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="has_excess" <?= $status_filter === 'has_excess' ? 'selected' : '' ?>>Has Excess</option>
                    <option value="needs_review" <?= $status_filter === 'needs_review' ? 'selected' : '' ?>>Needs Review (>20% discrepancy)</option>
                </select>
                <select id="vehicleFilter" onchange="applyFilters()">
                    <option value="all" <?= $vehicle_filter === 'all' ? 'selected' : '' ?>>All Vehicles</option>
                    <option value="car" <?= $vehicle_filter === 'car' ? 'selected' : '' ?>>Cars Only</option>
                    <option value="motorcycle" <?= $vehicle_filter === 'motorcycle' ? 'selected' : '' ?>>Motorcycles Only</option>
                </select>
            </div>

            <!-- Bookings Table -->
            <div class="bookings-table">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Vehicle</th>
                            <th>Renter</th>
                            <th>Mileage</th>
                            <th>Excess</th>
                            <th>GPS vs Odometer</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    No mileage data found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><strong>#<?= $booking['id'] ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($booking['vehicle_name']) ?>
                                        <br>
                                        <small style="color: #6b7280;"><?= ucfirst($booking['vehicle_type']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($booking['renter_name']) ?></td>
                                    <td>
                                        <?php if ($booking['actual_mileage']): ?>
                                            <strong><?= $booking['actual_mileage'] ?> km</strong>
                                            <br>
                                            <small style="color: #6b7280;">Allowed: <?= $booking['allowed_mileage'] ?? 'Unlimited' ?> km</small>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">Not recorded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['excess_mileage'] > 0): ?>
                                            <span class="badge excess">
                                                <?= $booking['excess_mileage'] ?> km
                                            </span>
                                            <br>
                                            <small style="color: #ef4444; font-weight: 600;">₱<?= number_format($booking['excess_mileage_fee'], 2) ?></small>
                                        <?php else: ?>
                                            <span style="color: #10b981;">✓ Within limit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['gps_distance'] && $booking['actual_mileage']): ?>
                                            <?php 
                                            $discrepancy = $booking['discrepancy_percentage'];
                                            $badgeClass = $discrepancy > 20 ? 'warning' : 'verified';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= number_format($discrepancy, 1) ?>% diff
                                            </span>
                                            <br>
                                            <small style="color: #6b7280;">GPS: <?= number_format($booking['gps_distance'], 1) ?> km</small>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['mileage_verified_by']): ?>
                                            <span class="badge verified">✓ Verified</span>
                                        <?php else: ?>
                                            <span class="badge pending">Pending</span>
                                        <?php endif; ?>
                                        <?php if ($booking['excess_mileage'] > 0 && !$booking['excess_mileage_paid']): ?>
                                            <br><span class="badge" style="background: #fecaca; color: #991b1b; margin-top: 4px;">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="viewDetails(<?= $booking['id'] ?>)">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for viewing details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Mileage Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <script>
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const vehicle = document.getElementById('vehicleFilter').value;
            window.location.href = `mileage_verification.php?status=${status}&vehicle_type=${vehicle}`;
        }

        function viewDetails(bookingId) {
            document.getElementById('detailsModal').style.display = 'block';
            document.getElementById('modalBody').innerHTML = '<p style="text-align: center; padding: 40px;">Loading...</p>';
            
            fetch(`api/mileage/get_mileage_details.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        displayMileageDetails(data.data);
                    } else {
                        document.getElementById('modalBody').innerHTML = '<p style="color: red;">Error loading details</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('modalBody').innerHTML = '<p style="color: red;">Error: ' + error + '</p>';
                });
        }

        function displayMileageDetails(data) {
            // Dynamic base URL based on current host
            const baseUrl = window.location.origin + '/carGOAdmin/';
            let html = `
                <div class="info-row">
                    <span class="info-label">Booking ID:</span>
                    <span class="info-value">#${data.booking_id}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vehicle:</span>
                    <span class="info-value">${data.vehicle.brand} ${data.vehicle.model}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Renter:</span>
                    <span class="info-value">${data.rental.renter_name}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Owner:</span>
                    <span class="info-value">${data.rental.owner_name}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Rental Period:</span>
                    <span class="info-value">${data.rental.rental_days} days (${data.rental.pickup_date} to ${data.rental.return_date})</span>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 15px;">📸 Odometer Photos</h3>
                <div class="odometer-photos">
                    <div class="photo-container">
                        <div class="photo-label">Starting Odometer</div>
                        ${data.odometer.start_photo ? 
                            `<img src="${baseUrl}${data.odometer.start_photo}" alt="Start Odometer">` :
                            '<div style="padding: 40px; text-align: center; color: #9ca3af;">No photo</div>'
                        }
                        <div style="padding: 10px; text-align: center; font-weight: 600;">
                            ${data.odometer.start || 'Not recorded'} km
                        </div>
                    </div>
                    <div class="photo-container">
                        <div class="photo-label">Ending Odometer</div>
                        ${data.odometer.end_photo ? 
                            `<img src="${baseUrl}${data.odometer.end_photo}" alt="End Odometer">` :
                            '<div style="padding: 40px; text-align: center; color: #9ca3af;">No photo</div>'
                        }
                        <div style="padding: 10px; text-align: center; font-weight: 600;">
                            ${data.odometer.end || 'Not recorded'} km
                        </div>
                    </div>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 15px;">📊 Mileage Calculation</h3>
                <div class="info-row">
                    <span class="info-label">Distance Driven (Odometer):</span>
                    <span class="info-value"><strong>${data.mileage.actual || 'N/A'} km</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Distance Tracked (GPS):</span>
                    <span class="info-value">${data.gps.distance ? data.gps.distance + ' km' : 'N/A'}</span>
                </div>
                ${data.gps.discrepancy_percentage ? `
                    <div class="info-row">
                        <span class="info-label">Discrepancy:</span>
                        <span class="info-value" style="color: ${data.gps.discrepancy_percentage > 20 ? '#ef4444' : '#10b981'}">
                            ${data.gps.discrepancy_percentage}%
                        </span>
                    </div>
                ` : ''}
                <div class="info-row">
                    <span class="info-label">Allowed Mileage:</span>
                    <span class="info-value">${data.mileage.allowed || 'Unlimited'} km</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Excess Mileage:</span>
                    <span class="info-value" style="color: ${data.mileage.excess > 0 ? '#ef4444' : '#10b981'}; font-weight: 600;">
                        ${data.mileage.excess || 0} km
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Excess Fee:</span>
                    <span class="info-value" style="color: #ef4444; font-weight: 600; font-size: 16px;">
                        ₱${parseFloat(data.mileage.excess_fee || 0).toFixed(2)}
                    </span>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 10px;">
                    ${!data.verification.verified ? `
                        <button class="btn btn-success" onclick="verifyMileage(${data.booking_id})">
                            ✓ Verify Mileage
                        </button>
                        <button class="btn btn-danger" onclick="flagForReview(${data.booking_id})">
                            ⚠ Flag for Review
                        </button>
                    ` : `
                        <span class="badge verified" style="padding: 10px 20px; font-size: 14px;">
                            ✓ Verified by Admin
                        </span>
                    `}
                </div>
            `;
            
            document.getElementById('modalBody').innerHTML = html;
        }

        function verifyMileage(bookingId) {
            if (!confirm('Verify this mileage record?')) return;
            
            fetch('api/mileage/verify_mileage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `booking_id=${bookingId}&admin_id=<?= $admin_id ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Mileage verified successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function flagForReview(bookingId) {
            const notes = prompt('Enter notes for review:');
            if (!notes) return;
            
            fetch('api/mileage/flag_for_review.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `booking_id=${bookingId}&admin_id=<?= $admin_id ?>&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Flagged for review');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('detailsModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
