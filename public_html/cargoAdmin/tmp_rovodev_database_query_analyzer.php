<?php
/**
 * ============================================================================
 * DATABASE QUERY ANALYZER
 * Analyzes all database queries for errors, missing columns, and issues
 * ============================================================================
 */

session_start();
include "include/db.php";

if (!isset($_SESSION['admin_id'])) {
    die("Access denied. Admin login required.");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Query Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f5f7fa; padding: 20px; }
        .analysis-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-status { padding: 10px; border-radius: 4px; margin: 5px 0; }
        .status-good { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-error { background: #f8d7da; color: #721c24; }
        .column-list { font-family: monospace; font-size: 12px; }
        .missing-column { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
<div class="container-fluid">
    <h1><i class="bi bi-database"></i> Database Query Analyzer</h1>
    <p class="text-muted">Analyzing database structure and common queries</p>

    <?php
    // Critical tables to check
    $criticalTables = [
        'payouts' => [
            'required_columns' => ['id', 'booking_id', 'owner_id', 'amount', 'platform_fee', 'net_amount', 
                                   'status', 'transfer_proof', 'completion_reference', 'processed_at'],
            'queries' => [
                "SELECT p.*, b.car_id FROM payouts p JOIN bookings b ON p.booking_id = b.id WHERE p.status = 'completed' LIMIT 1",
                "SELECT transfer_proof FROM payouts WHERE status = 'completed' AND transfer_proof IS NOT NULL LIMIT 1"
            ]
        ],
        'bookings' => [
            'required_columns' => ['id', 'user_id', 'owner_id', 'car_id', 'vehicle_type', 'status', 
                                   'total_amount', 'owner_payout', 'platform_fee', 'escrow_status', 'payout_status'],
            'queries' => [
                "SELECT * FROM bookings WHERE escrow_status = 'released_to_owner' LIMIT 1",
                "SELECT COUNT(*) as count FROM bookings WHERE payout_status = 'pending'"
            ]
        ],
        'users' => [
            'required_columns' => ['id', 'fullname', 'email', 'gcash_number', 'gcash_name', 'is_online', 'role'],
            'queries' => [
                "SELECT gcash_number, gcash_name FROM users WHERE gcash_number IS NOT NULL LIMIT 1"
            ]
        ],
        'cars' => [
            'required_columns' => ['id', 'owner_id', 'brand', 'model', 'car_year', 'price_per_day', 'status', 'image'],
            'queries' => [
                "SELECT * FROM cars WHERE status = 'available' LIMIT 1"
            ]
        ],
        'motorcycles' => [
            'required_columns' => ['id', 'owner_id', 'brand', 'model', 'motorcycle_year', 'price_per_day', 'status', 'image'],
            'queries' => [
                "SELECT * FROM motorcycles WHERE status = 'available' LIMIT 1"
            ]
        ]
    ];

    foreach ($criticalTables as $tableName => $tableInfo) {
        echo "<div class='analysis-card'>";
        echo "<h3><i class='bi bi-table'></i> Table: <code>$tableName</code></h3>";
        
        // Check if table exists
        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
        if (mysqli_num_rows($tableCheck) == 0) {
            echo "<div class='table-status status-error'><i class='bi bi-x-circle'></i> Table does not exist!</div>";
            echo "</div>";
            continue;
        }
        
        echo "<div class='table-status status-good'><i class='bi bi-check-circle'></i> Table exists</div>";
        
        // Get actual columns
        $columnsResult = mysqli_query($conn, "DESCRIBE $tableName");
        $actualColumns = [];
        while ($col = mysqli_fetch_assoc($columnsResult)) {
            $actualColumns[] = $col['Field'];
        }
        
        // Check required columns
        $missingColumns = array_diff($tableInfo['required_columns'], $actualColumns);
        
        if (count($missingColumns) > 0) {
            echo "<div class='table-status status-error'>";
            echo "<i class='bi bi-exclamation-triangle'></i> <strong>Missing Columns:</strong><br>";
            echo "<span class='missing-column'>" . implode(', ', $missingColumns) . "</span>";
            echo "</div>";
        } else {
            echo "<div class='table-status status-good'><i class='bi bi-check-circle'></i> All required columns present</div>";
        }
        
        // Show all columns
        echo "<details class='mt-3'>";
        echo "<summary><strong>All Columns (" . count($actualColumns) . ")</strong></summary>";
        echo "<div class='column-list mt-2'>" . implode(', ', $actualColumns) . "</div>";
        echo "</details>";
        
        // Test queries
        if (!empty($tableInfo['queries'])) {
            echo "<h5 class='mt-3'>Query Tests:</h5>";
            foreach ($tableInfo['queries'] as $query) {
                echo "<div class='mt-2'><code>" . htmlspecialchars($query) . "</code><br>";
                $testResult = mysqli_query($conn, $query);
                if ($testResult) {
                    $rowCount = mysqli_num_rows($testResult);
                    echo "<span class='badge bg-success'>✓ Success</span> ";
                    echo "<small>($rowCount rows)</small>";
                } else {
                    echo "<span class='badge bg-danger'>✗ Failed</span> ";
                    echo "<small class='text-danger'>" . mysqli_error($conn) . "</small>";
                }
                echo "</div>";
            }
        }
        
        echo "</div>";
    }
    
    // Check for common API query patterns
    echo "<div class='analysis-card'>";
    echo "<h3><i class='bi bi-code-square'></i> Common API Query Patterns</h3>";
    
    $apiQueries = [
        'Payout with Transfer Proof' => "SELECT p.id, p.transfer_proof, p.completion_reference FROM payouts p WHERE p.status = 'completed' LIMIT 5",
        'Pending Payouts' => "SELECT b.id, b.owner_payout, b.escrow_status, b.payout_status FROM bookings b WHERE b.escrow_status = 'released_to_owner' AND b.payout_status = 'pending' LIMIT 5",
        'Owner with GCash Details' => "SELECT u.id, u.fullname, u.gcash_number, u.gcash_name FROM users u WHERE u.gcash_number IS NOT NULL LIMIT 5",
        'Complete Payout Join' => "SELECT p.*, b.vehicle_type, u.fullname, u.gcash_number FROM payouts p JOIN bookings b ON p.booking_id = b.id JOIN users u ON p.owner_id = u.id WHERE p.status = 'completed' LIMIT 1",
        'Vehicle with Owner' => "SELECT c.id, c.brand, c.model, u.fullname as owner_name FROM cars c JOIN users u ON c.owner_id = u.id LIMIT 1"
    ];
    
    foreach ($apiQueries as $name => $query) {
        echo "<div class='mt-3'>";
        echo "<strong>$name:</strong><br>";
        echo "<code style='font-size: 11px;'>" . htmlspecialchars($query) . "</code><br>";
        
        $result = mysqli_query($conn, $query);
        if ($result) {
            $rowCount = mysqli_num_rows($result);
            echo "<span class='badge bg-success mt-1'>✓ Success</span> ";
            echo "<small>($rowCount rows returned)</small>";
            
            if ($rowCount > 0) {
                $sampleRow = mysqli_fetch_assoc($result);
                echo "<details class='mt-2'>";
                echo "<summary>Sample Result</summary>";
                echo "<pre style='font-size: 11px;'>" . print_r($sampleRow, true) . "</pre>";
                echo "</details>";
            }
        } else {
            echo "<span class='badge bg-danger mt-1'>✗ Failed</span> ";
            echo "<div class='alert alert-danger mt-2' style='font-size: 12px;'>" . mysqli_error($conn) . "</div>";
        }
        echo "</div>";
    }
    
    echo "</div>";
    
    // Recommendations
    echo "<div class='analysis-card'>";
    echo "<h3><i class='bi bi-lightbulb'></i> Recommendations</h3>";
    
    $recommendations = [];
    
    // Check transfer_proof usage
    $transferProofCheck = mysqli_query($conn, "SELECT COUNT(*) as count FROM payouts WHERE transfer_proof IS NOT NULL");
    if ($transferProofCheck) {
        $count = mysqli_fetch_assoc($transferProofCheck)['count'];
        if ($count == 0) {
            $recommendations[] = "No payouts have transfer_proof set. Ensure admins are uploading proof when completing payouts.";
        } else {
            $recommendations[] = "<span class='text-success'>✓ $count payouts have transfer proof uploaded.</span>";
        }
    }
    
    // Check GCash details
    $gcashCheck = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE gcash_number IS NULL AND role = 'owner'");
    if ($gcashCheck) {
        $count = mysqli_fetch_assoc($gcashCheck)['count'];
        if ($count > 0) {
            $recommendations[] = "⚠️ $count owners don't have GCash details set. They won't be able to receive payouts.";
        }
    }
    
    // Check pending payouts
    $pendingCheck = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE escrow_status = 'released_to_owner' AND payout_status = 'pending'");
    if ($pendingCheck) {
        $count = mysqli_fetch_assoc($pendingCheck)['count'];
        if ($count > 0) {
            $recommendations[] = "ℹ️ $count bookings are ready for payout.";
        }
    }
    
    foreach ($recommendations as $rec) {
        echo "<div class='alert alert-info'>$rec</div>";
    }
    
    echo "</div>";
    ?>

    <div class="text-center mt-4">
        <a href="tmp_rovodev_api_endpoint_tester.php" class="btn btn-primary"><i class="bi bi-arrow-right"></i> Continue to API Endpoint Tester</a>
        <a href="payouts.php" class="btn btn-secondary">Back to Payouts</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
