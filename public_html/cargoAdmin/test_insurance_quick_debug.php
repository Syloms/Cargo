<?php
/**
 * Quick Debug Script for Insurance APIs
 * Tests each failing endpoint and shows exact error messages
 */

header('Content-Type: text/html; charset=utf-8');
require_once 'include/db.php';

function testAPI($name, $url, $method = 'GET', $data = null) {
    echo "<div style='margin: 20px; padding: 15px; border: 2px solid #ddd; border-radius: 8px;'>";
    echo "<h3 style='color: #333;'>🧪 Testing: $name</h3>";
    echo "<p><strong>URL:</strong> $url</p>";
    echo "<p><strong>Method:</strong> $method</p>";
    
    if ($data) {
        echo "<p><strong>Data:</strong></p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px;'>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data && $method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<p><strong>HTTP Code:</strong> <span style='color: " . ($httpCode == 200 ? 'green' : 'red') . ";'>$httpCode</span></p>";
    
    if ($error) {
        echo "<p style='color: red;'><strong>cURL Error:</strong> $error</p>";
    }
    
    echo "<p><strong>Response:</strong></p>";
    $json = json_decode($response, true);
    
    if ($json) {
        $bgColor = isset($json['success']) && $json['success'] ? '#e8f5e9' : '#ffebee';
        echo "<pre style='background: $bgColor; padding: 15px; border-radius: 4px; border-left: 4px solid " . ($json['success'] ? 'green' : 'red') . ";'>" . json_encode($json, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<pre style='background: #ffebee; padding: 15px; border-radius: 4px;'>$response</pre>";
    }
    
    echo "</div>";
}

// Get base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/api/insurance";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insurance API Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐛 Insurance API Quick Debug</h1>
            <p>Testing failing endpoints to identify exact errors</p>
        </div>

        <?php
        echo "<h2>🔍 Testing Failed Endpoints</h2>";
        
        // Test 2: Create Policy
        echo "<hr style='margin: 30px 0;'>";
        testAPI(
            "Test 2: Create Policy",
            "$baseUrl/create_policy.php",
            "POST",
            [
                'booking_id' => 3,
                'user_id' => 7,
                'coverage_type' => 'basic'
            ]
        );
        
        // Test 3: Get Policy
        echo "<hr style='margin: 30px 0;'>";
        testAPI(
            "Test 3: Get Policy",
            "$baseUrl/get_policy.php?booking_id=3&user_id=7",
            "GET"
        );
        
        // Test 4: File Claim
        echo "<hr style='margin: 30px 0;'>";
        testAPI(
            "Test 4: File Claim",
            "$baseUrl/file_claim.php",
            "POST",
            [
                'policy_id' => 1,
                'booking_id' => 3,
                'user_id' => 7,
                'claim_type' => 'collision',
                'incident_date' => date('Y-m-d'),
                'incident_location' => 'San Francisco, Caraga',
                'incident_description' => 'Test collision incident',
                'claimed_amount' => 5000
            ]
        );
        
        // Test 6: Admin Get All Policies
        echo "<hr style='margin: 30px 0;'>";
        testAPI(
            "Test 6: Admin Get All Policies",
            "$baseUrl/admin/get_all_policies.php?status=all&page=1&limit=20",
            "GET"
        );
        
        // Test 7: Admin Get All Claims
        echo "<hr style='margin: 30px 0;'>";
        testAPI(
            "Test 7: Admin Get All Claims",
            "$baseUrl/admin/get_all_claims.php?status=all&page=1",
            "GET"
        );
        
        // Test 8: Approve Claim
        echo "<hr style='margin: 30px 0;'>";
        testAPI(
            "Test 8: Approve Claim",
            "$baseUrl/admin/approve_claim.php",
            "POST",
            [
                'claim_id' => 1,
                'approved_amount' => 4500,
                'review_notes' => 'Test approval',
                'admin_id' => 1
            ]
        );
        ?>
        
        <div style="margin-top: 40px; padding: 20px; background: white; border-radius: 10px;">
            <h3>📊 Summary</h3>
            <p>Check each test result above for specific error messages.</p>
            <p><strong>Common Issues:</strong></p>
            <ul>
                <li>Missing database columns (firstname vs fullname, contact vs phone)</li>
                <li>Invalid booking/policy IDs in test data</li>
                <li>Foreign key constraints</li>
                <li>Insurance policy already exists for booking</li>
            </ul>
            
            <h4>💡 Next Steps:</h4>
            <ol>
                <li>Check error messages in red boxes above</li>
                <li>Verify test data exists in database (booking_id=3, user_id=7)</li>
                <li>Ensure insurance_providers table has active providers</li>
                <li>Check insurance_coverage_types table has data</li>
            </ol>
        </div>
    </div>
</body>
</html>
