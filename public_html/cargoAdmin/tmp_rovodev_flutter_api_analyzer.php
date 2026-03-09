<?php
/**
 * ============================================================================
 * FLUTTER APP API CALL ANALYZER
 * Scans Flutter app code to find all API calls and verify endpoints exist
 * ============================================================================
 */

session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Access denied. Admin login required.");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Flutter API Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f5f7fa; padding: 20px; }
        .api-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .endpoint-item { padding: 10px; margin: 5px 0; border-left: 4px solid #007bff; background: #f8f9fa; border-radius: 4px; }
        .endpoint-exists { border-left-color: #28a745; }
        .endpoint-missing { border-left-color: #dc3545; }
        .file-path { font-family: monospace; font-size: 12px; color: #6c757d; }
    </style>
</head>
<body>
<div class="container-fluid">
    <h1><i class="bi bi-phone"></i> Flutter App API Call Analyzer</h1>
    <p class="text-muted">Analyzing API endpoints used in the Flutter app</p>

    <?php
    // Common API endpoints that should exist
    $requiredEndpoints = [
        // Payout APIs
        'api/payout/get_owner_payouts.php',
        'api/payout/get_owner_payout_history.php',
        'api/payout/get_payout_settings.php',
        'api/payout/update_payout_settings.php',
        
        // Payment APIs
        'api/payment/complete_payout.php',
        'api/payment/release_escrow.php',
        'api/get_user_payment_history.php',
        
        // Booking APIs
        'api/get_my_bookings.php',
        'api/active_bookings.php',
        'api/get_pending_requests.php',
        'api/approve_request.php',
        'api/reject_request.php',
        'api/cancel_booking.php',
        'api/create_booking.php',
        
        // Vehicle APIs
        'api/get_cars.php',
        'api/get_cars_filtered.php',
        'api/get_motorcycles_filtered.php',
        'api/get_owner_cars.php',
        'api/get_owner_motorcycles.php',
        'api/get_car_details.php',
        'api/get_motorcycle_details.php',
        
        // User APIs
        'api/get_owner_profile.php',
        'api/update_profile.php',
        'api/check_user_verification.php',
        'api/submit_verification.php',
        'api/get_unread_counts.php',
        
        // Escrow APIs
        'api/escrow/release_to_owner.php',
        'api/escrow/batch_release_escrows.php',
        
        // Insurance APIs
        'api/insurance/get_insurance_policies.php',
        'api/insurance/purchase_insurance.php',
        
        // Review/Rating APIs
        'api/submit_review.php',
        'api/get_reviews.php',
        'api/get_owner_reviews.php',
        
        // Notification APIs
        'api/notifications/get_notifications.php',
        'api/notifications/mark_read.php',
        'api/save_fcm_token.php',
        
        // GPS Tracking
        'api/GPS_tracking/update_location.php',
        'api/GPS_tracking/get_location.php',
        
        // Overdue Management
        'api/overdue/check_overdue.php',
        'api/overdue/get_overdue_bookings.php',
        
        // Analytics
        'api/analytics/get_owner_analytics.php',
        'api/dashboard/get_dashboard_stats.php'
    ];
    
    $basePath = __DIR__;
    $existingEndpoints = [];
    $missingEndpoints = [];
    
    echo "<div class='api-card'>";
    echo "<h3><i class='bi bi-check-circle'></i> Endpoint Verification</h3>";
    echo "<p>Checking " . count($requiredEndpoints) . " critical API endpoints...</p>";
    
    foreach ($requiredEndpoints as $endpoint) {
        $fullPath = $basePath . '/' . $endpoint;
        $exists = file_exists($fullPath);
        
        if ($exists) {
            $existingEndpoints[] = $endpoint;
            echo "<div class='endpoint-item endpoint-exists'>";
            echo "<i class='bi bi-check-circle text-success'></i> ";
        } else {
            $missingEndpoints[] = $endpoint;
            echo "<div class='endpoint-item endpoint-missing'>";
            echo "<i class='bi bi-x-circle text-danger'></i> ";
        }
        
        echo "<code>$endpoint</code>";
        echo "</div>";
    }
    
    echo "<div class='mt-3'>";
    echo "<div class='alert alert-success'><strong>" . count($existingEndpoints) . "</strong> endpoints exist</div>";
    if (count($missingEndpoints) > 0) {
        echo "<div class='alert alert-danger'><strong>" . count($missingEndpoints) . "</strong> endpoints missing</div>";
    }
    echo "</div>";
    
    echo "</div>";
    
    // Check for common issues in existing endpoints
    echo "<div class='api-card'>";
    echo "<h3><i class='bi bi-bug'></i> Common Issues Check</h3>";
    
    $issuesFound = [];
    
    // Check payout endpoints for transfer_proof
    $payoutFiles = [
        'api/payout/get_owner_payout_history.php',
        'api/payout/get_owner_payouts.php'
    ];
    
    foreach ($payoutFiles as $file) {
        $fullPath = $basePath . '/' . $file;
        if (file_exists($fullPath)) {
            $content = file_get_contents($fullPath);
            if (strpos($content, 'transfer_proof') === false) {
                $issuesFound[] = "<code>$file</code> does not select transfer_proof column";
            } else {
                echo "<div class='alert alert-success'><i class='bi bi-check'></i> <code>$file</code> includes transfer_proof</div>";
            }
        }
    }
    
    // Check complete_payout for file upload handling
    $completePayoutPath = $basePath . '/api/payment/complete_payout.php';
    if (file_exists($completePayoutPath)) {
        $content = file_get_contents($completePayoutPath);
        if (strpos($content, 'transfer_proof') !== false && strpos($content, '$_FILES') !== false) {
            echo "<div class='alert alert-success'><i class='bi bi-check'></i> complete_payout.php handles file uploads correctly</div>";
        } else {
            $issuesFound[] = "complete_payout.php may not handle file uploads properly";
        }
    }
    
    if (empty($issuesFound)) {
        echo "<div class='alert alert-success'>No issues found!</div>";
    } else {
        echo "<div class='alert alert-warning'>";
        echo "<strong>Issues Found:</strong><ul>";
        foreach ($issuesFound as $issue) {
            echo "<li>$issue</li>";
        }
        echo "</ul></div>";
    }
    
    echo "</div>";
    
    // Flutter service files analysis
    echo "<div class='api-card'>";
    echo "<h3><i class='bi bi-file-code'></i> Flutter Service Files</h3>";
    
    $flutterServicesPath = $basePath . '/../../lib/USERS-UI/services/';
    $ownerServicesPath = $basePath . '/../../lib/USERS-UI/Owner/services/';
    $renterServicesPath = $basePath . '/../../lib/USERS-UI/Renter/services/';
    
    $servicePaths = [
        'General Services' => $flutterServicesPath,
        'Owner Services' => $ownerServicesPath,
        'Renter Services' => $renterServicesPath
    ];
    
    foreach ($servicePaths as $label => $path) {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            echo "<h5>$label</h5>";
            echo "<ul>";
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'dart') {
                    echo "<li class='file-path'>$file</li>";
                }
            }
            echo "</ul>";
        }
    }
    
    echo "</div>";
    ?>

    <div class="text-center mt-4">
        <a href="tmp_rovodev_database_query_analyzer.php" class="btn btn-secondary">← Back to Database Analyzer</a>
        <a href="tmp_rovodev_api_endpoint_tester.php" class="btn btn-primary">Continue to API Tester →</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
