<?php
session_start();
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/header.php';

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Escrow Release Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Cargo Admin</title>
    <link rel="stylesheet" href="include/admin-styles.css">
    <style>
        .release-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #4CAF50;
        }
        
        .stat-card.warning { border-left-color: #FF9800; }
        .stat-card.info { border-left-color: #2196F3; }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .stat-money {
            color: #4CAF50;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
        
        .btn-secondary {
            background: #2196F3;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #0b7dda;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .table-header h2 {
            margin: 0;
            font-size: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-success {
            background: #D4EDDA;
            color: #155724;
        }
        
        .badge-warning {
            background: #FFF3CD;
            color: #856404;
        }
        
        .badge-danger {
            background: #F8D7DA;
            color: #721C24;
        }
        
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        
        .money {
            color: #4CAF50;
            font-weight: 600;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #D4EDDA;
            color: #155724;
            border-left: 4px solid #28A745;
        }
        
        .alert-warning {
            background: #FFF3CD;
            color: #856404;
            border-left: 4px solid #FFC107;
        }
        
        .alert-danger {
            background: #F8D7DA;
            color: #721C24;
            border-left: 4px solid #DC3545;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/include/sidebar.php'; ?>
    
    <div class="release-container">
        <h1><?php echo $pageTitle; ?></h1>
        
        <div id="alertContainer"></div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Ready for Release</h3>
                <p class="stat-value" id="releasableCount">-</p>
            </div>
            
            <div class="stat-card warning">
                <h3>Total Releasable Amount</h3>
                <p class="stat-value stat-money" id="releasableAmount">₱0.00</p>
            </div>
            
            <div class="stat-card info">
                <h3>Pending Payouts</h3>
                <p class="stat-value" id="pendingCount">-</p>
            </div>
            
            <div class="stat-card info">
                <h3>Pending Amount</h3>
                <p class="stat-value stat-money" id="pendingAmount">₱0.00</p>
            </div>
        </div>
        
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="releaseSelected()">
                🚀 Release Selected Escrows
            </button>
            <button class="btn btn-secondary" onclick="releaseAll()">
                ⚡ Release All Eligible
            </button>
            <button class="btn btn-secondary" onclick="loadData()">
                🔄 Refresh
            </button>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h2>Bookings Ready for Escrow Release</h2>
            </div>
            
            <div id="tableContent">
                <div class="loading">Loading...</div>
            </div>
        </div>
    </div>
    
    <script>
        let releasableData = [];
        
        // Load data on page load
        document.addEventListener('DOMContentLoaded', loadData);
        
        async function loadData() {
            try {
                const response = await fetch('api/escrow/check_releasable_escrows.php');
                const data = await response.json();
                
                if (data.success) {
                    releasableData = data.releasable_bookings || [];
                    
                    // Update stats
                    document.getElementById('releasableCount').textContent = data.releasable_count;
                    document.getElementById('releasableAmount').textContent = '₱' + formatMoney(data.total_releasable_amount);
                    document.getElementById('pendingCount').textContent = data.pending_payouts_count;
                    document.getElementById('pendingAmount').textContent = '₱' + formatMoney(data.pending_payouts_amount);
                    
                    // Show warnings if any
                    if (data.warnings) {
                        showAlert('warning', data.warnings.message);
                    }
                    
                    // Render table
                    renderTable(releasableData);
                } else {
                    showAlert('danger', data.message || 'Failed to load data');
                }
            } catch (error) {
                showAlert('danger', 'Error loading data: ' + error.message);
            }
        }
        
        function renderTable(bookings) {
            const tableContent = document.getElementById('tableContent');
            
            if (bookings.length === 0) {
                tableContent.innerHTML = `
                    <div class="empty-state">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                        </svg>
                        <h3>No Escrows Ready for Release</h3>
                        <p>All escrows are up to date!</p>
                    </div>
                `;
                return;
            }
            
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th>Booking ID</th>
                            <th>Owner</th>
                            <th>Renter</th>
                            <th>Vehicle</th>
                            <th>Amount</th>
                            <th>Return Date</th>
                            <th>Days Pending</th>
                            <th>GCash Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            bookings.forEach(booking => {
                const gcashBadge = booking.gcash_configured 
                    ? `<span class="badge badge-success">✓ Configured</span>`
                    : `<span class="badge badge-danger">✗ Not Set</span>`;
                
                html += `
                    <tr>
                        <td class="checkbox-cell">
                            <input type="checkbox" class="booking-checkbox" value="${booking.booking_id}">
                        </td>
                        <td>BK-${booking.booking_id}</td>
                        <td>${booking.owner_name}</td>
                        <td>${booking.renter_name}</td>
                        <td>${booking.vehicle}</td>
                        <td class="money">₱${formatMoney(booking.owner_payout)}</td>
                        <td>${formatDate(booking.return_date)}</td>
                        <td><strong>${booking.days_since_return}</strong> days</td>
                        <td>${gcashBadge}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            tableContent.innerHTML = html;
        }
        
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.booking-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
        
        async function releaseSelected() {
            const checkboxes = document.querySelectorAll('.booking-checkbox:checked');
            const bookingIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            if (bookingIds.length === 0) {
                showAlert('warning', 'Please select at least one booking to release');
                return;
            }
            
            if (!confirm(`Release escrow for ${bookingIds.length} booking(s)?`)) {
                return;
            }
            
            await releaseEscrows(bookingIds);
        }
        
        async function releaseAll() {
            if (releasableData.length === 0) {
                showAlert('warning', 'No bookings available for release');
                return;
            }
            
            if (!confirm(`Release escrow for all ${releasableData.length} eligible booking(s)?`)) {
                return;
            }
            
            const bookingIds = releasableData.map(b => b.booking_id);
            await releaseEscrows(bookingIds);
        }
        
        async function releaseEscrows(bookingIds) {
            try {
                showAlert('info', 'Processing escrow releases...');
                
                const response = await fetch('api/escrow/batch_release_escrows.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        booking_ids: bookingIds,
                        admin_id: <?php echo $_SESSION['admin_id']; ?>
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const successCount = data.results.success.length;
                    const failCount = data.results.failed.length;
                    const totalAmount = data.results.total_released;
                    
                    let message = `✅ Successfully released ${successCount} escrow(s) totaling ₱${formatMoney(totalAmount)}`;
                    
                    if (failCount > 0) {
                        message += `\n⚠️ ${failCount} failed to release`;
                    }
                    
                    showAlert('success', message);
                    
                    // Reload data
                    setTimeout(loadData, 1500);
                } else {
                    showAlert('danger', data.message || 'Failed to release escrows');
                }
            } catch (error) {
                showAlert('danger', 'Error releasing escrows: ' + error.message);
            }
        }
        
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alert);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        function formatMoney(amount) {
            return parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }
    </script>
</body>
</html>
