<?php
/**
 * Verify All Created Endpoints
 * Quick verification that all endpoints now exist
 */

session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Access denied. Admin login required.");
}

$requiredEndpoints = [
    'api/insurance/get_insurance_policies.php' => 'Insurance - Get Policies',
    'api/insurance/purchase_insurance.php' => 'Insurance - Purchase',
    'api/notifications/get_notifications.php' => 'Notifications - Get',
    'api/notifications/mark_read.php' => 'Notifications - Mark Read',
    'api/analytics/get_owner_analytics.php' => 'Analytics - Owner Stats',
    'api/dashboard/get_dashboard_stats.php' => 'Dashboard - Stats',
    'api/GPS_tracking/get_location.php' => 'GPS - Get Location',
    'api/overdue/check_overdue.php' => 'Overdue - Check',
];

$basePath = __DIR__;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Endpoint Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f5f7fa; padding: 40px; }
        .verify-card { background: white; border-radius: 12px; padding: 30px; max-width: 800px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .endpoint-check { padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #28a745; background: #f8f9fa; }
        .endpoint-missing { border-left-color: #dc3545; }
        .success-banner { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; text-align: center; margin-bottom: 30px; }
    </style>
</head>
<body>
<div class="container">
    <?php
    $allExist = true;
    $existCount = 0;
    
    foreach ($requiredEndpoints as $endpoint => $name) {
        if (file_exists($basePath . '/' . $endpoint)) {
            $existCount++;
        } else {
            $allExist = false;
        }
    }
    
    if ($allExist) {
        echo '<div class="success-banner">';
        echo '<h1><i class="bi bi-check-circle-fill"></i> All Endpoints Ready!</h1>';
        echo '<p class="mb-0">All ' . count($requiredEndpoints) . ' required endpoints have been created successfully.</p>';
        echo '</div>';
    }
    ?>
    
    <div class="verify-card">
        <h2 class="mb-4"><i class="bi bi-list-check"></i> Endpoint Verification Report</h2>
        
        <?php
        foreach ($requiredEndpoints as $endpoint => $name) {
            $fullPath = $basePath . '/' . $endpoint;
            $exists = file_exists($fullPath);
            $class = $exists ? 'endpoint-check' : 'endpoint-check endpoint-missing';
            $icon = $exists ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>';
            
            echo "<div class='$class'>";
            echo "$icon <strong>$name</strong><br>";
            echo "<small class='text-muted'>$endpoint</small>";
            if ($exists) {
                $fileSize = filesize($fullPath);
                echo "<br><small class='text-success'>File size: " . number_format($fileSize) . " bytes</small>";
            }
            echo "</div>";
        }
        ?>
        
        <div class="mt-4 p-3 bg-light rounded">
            <h5>Summary:</h5>
            <div class="row text-center">
                <div class="col-6">
                    <h2 class="text-success"><?= $existCount ?></h2>
                    <small>Created</small>
                </div>
                <div class="col-6">
                    <h2 class="text-<?= $allExist ? 'success' : 'danger' ?>"><?= count($requiredEndpoints) - $existCount ?></h2>
                    <small>Missing</small>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="tmp_rovodev_flutter_api_analyzer.php" class="btn btn-primary">
                <i class="bi bi-arrow-clockwise"></i> Re-run Full API Analyzer
            </a>
            <a href="tmp_rovodev_api_endpoint_tester.php" class="btn btn-success">
                <i class="bi bi-play-fill"></i> Test Endpoints
            </a>
            <a href="tmp_rovodev_testing_dashboard.php" class="btn btn-secondary">
                <i class="bi bi-house"></i> Testing Dashboard
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
