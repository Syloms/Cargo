<?php
/**
 * Test Insurance API Endpoints
 * This script tests all insurance API endpoints to ensure they work correctly
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insurance API Test Suite</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test-section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        .test-section h2 { margin-top: 0; color: #333; }
        .test-button { 
            background: #ff6b35; color: white; border: none; padding: 10px 20px; 
            border-radius: 5px; cursor: pointer; font-size: 14px; margin: 5px;
        }
        .test-button:hover { background: #ff5722; }
        .result { 
            margin-top: 10px; padding: 15px; border-radius: 5px; 
            background: #f9f9f9; border-left: 4px solid #ccc;
        }
        .success { border-left-color: #4caf50; background: #e8f5e9; }
        .error { border-left-color: #f44336; background: #ffebee; }
        .loading { border-left-color: #2196f3; background: #e3f2fd; }
        pre { background: #263238; color: #aed581; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .badge { 
            display: inline-block; padding: 3px 8px; border-radius: 3px; 
            font-size: 12px; font-weight: bold;
        }
        .badge-success { background: #4caf50; color: white; }
        .badge-error { background: #f44336; color: white; }
        .badge-pending { background: #ff9800; color: white; }
        input, textarea { 
            width: 100%; padding: 8px; margin: 5px 0; 
            border: 1px solid #ddd; border-radius: 4px; 
        }
        label { font-weight: bold; display: block; margin-top: 10px; }
    </style>
    <script>
        async function testAPI(endpoint, method = 'GET', data = null, resultId) {
            const resultDiv = document.getElementById(resultId);
            resultDiv.innerHTML = '<div class="result loading">⏳ Testing...</div>';
            
            try {
                const options = {
                    method: method,
                    headers: { 'Content-Type': 'application/json' }
                };
                
                if (data && method === 'POST') {
                    options.body = JSON.stringify(data);
                }
                
                const response = await fetch(endpoint, options);
                const result = await response.json();
                
                const status = result.success ? 'success' : 'error';
                const badge = result.success ? 
                    '<span class="badge badge-success">✓ SUCCESS</span>' : 
                    '<span class="badge badge-error">✗ FAILED</span>';
                
                resultDiv.innerHTML = `
                    <div class="result ${status}">
                        ${badge}
                        <h4>Response:</h4>
                        <pre>${JSON.stringify(result, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="result error">
                        <span class="badge badge-error">✗ ERROR</span>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        function testGetCoverageTypes() {
            testAPI('get_coverage_types.php', 'GET', null, 'result1');
        }
        
        function testCreatePolicy() {
            const bookingId = document.getElementById('bookingId').value || 1;
            const userId = document.getElementById('userId').value || 1;
            const coverageType = document.getElementById('coverageType').value || 'basic';
            
            const data = {
                booking_id: parseInt(bookingId),
                user_id: parseInt(userId),
                coverage_type: coverageType
            };
            
            testAPI('create_policy.php', 'POST', data, 'result2');
        }
        
        function testGetPolicy() {
            const bookingId = document.getElementById('getPolicyBookingId').value;
            const userId = document.getElementById('getPolicyUserId').value || 1;
            
            let endpoint = `get_policy.php?user_id=${userId}`;
            if (bookingId) {
                endpoint += `&booking_id=${bookingId}`;
            }
            
            testAPI(endpoint, 'GET', null, 'result3');
        }
        
        function testFileClaim() {
            const policyId = document.getElementById('claimPolicyId').value || 1;
            const bookingId = document.getElementById('claimBookingId').value || 1;
            const userId = document.getElementById('claimUserId').value || 1;
            
            const data = {
                policy_id: parseInt(policyId),
                booking_id: parseInt(bookingId),
                user_id: parseInt(userId),
                claim_type: 'collision',
                incident_date: new Date().toISOString(),
                incident_description: 'Test claim - Minor collision at parking lot',
                claimed_amount: 5000,
                incident_location: 'Test Location',
                police_report_number: 'PR-2026-TEST-001'
            };
            
            testAPI('file_claim.php', 'POST', data, 'result4');
        }
        
        function testGetClaims() {
            const userId = document.getElementById('getClaimsUserId').value || 1;
            const status = document.getElementById('claimsStatus').value || 'all';
            
            testAPI(`get_claims.php?user_id=${userId}&status=${status}`, 'GET', null, 'result5');
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>🔒 Insurance API Test Suite</h1>
        <p>Test all insurance system API endpoints to verify functionality.</p>
        
        <!-- Test 1: Get Coverage Types -->
        <div class="test-section">
            <h2>1. Get Coverage Types</h2>
            <p>Retrieve all available insurance coverage types.</p>
            <button class="test-button" onclick="testGetCoverageTypes()">🧪 Test Get Coverage Types</button>
            <div id="result1"></div>
        </div>
        
        <!-- Test 2: Create Policy -->
        <div class="test-section">
            <h2>2. Create Insurance Policy</h2>
            <p>Create a new insurance policy for a booking.</p>
            
            <label>Booking ID:</label>
            <input type="number" id="bookingId" value="1" placeholder="Enter Booking ID">
            
            <label>User ID:</label>
            <input type="number" id="userId" value="1" placeholder="Enter User ID">
            
            <label>Coverage Type:</label>
            <select id="coverageType" style="width: 100%; padding: 8px;">
                <option value="basic">Basic</option>
                <option value="standard">Standard</option>
                <option value="premium">Premium</option>
                <option value="comprehensive">Comprehensive</option>
            </select>
            
            <button class="test-button" onclick="testCreatePolicy()">🧪 Test Create Policy</button>
            <div id="result2"></div>
        </div>
        
        <!-- Test 3: Get Policy -->
        <div class="test-section">
            <h2>3. Get Policy Details</h2>
            <p>Retrieve insurance policy details for a booking.</p>
            
            <label>Booking ID:</label>
            <input type="number" id="getPolicyBookingId" placeholder="Enter Booking ID">
            
            <label>User ID:</label>
            <input type="number" id="getPolicyUserId" value="1" placeholder="Enter User ID">
            
            <button class="test-button" onclick="testGetPolicy()">🧪 Test Get Policy</button>
            <div id="result3"></div>
        </div>
        
        <!-- Test 4: File Claim -->
        <div class="test-section">
            <h2>4. File Insurance Claim</h2>
            <p>Submit a new insurance claim.</p>
            
            <label>Policy ID:</label>
            <input type="number" id="claimPolicyId" value="1" placeholder="Enter Policy ID">
            
            <label>Booking ID:</label>
            <input type="number" id="claimBookingId" value="1" placeholder="Enter Booking ID">
            
            <label>User ID:</label>
            <input type="number" id="claimUserId" value="1" placeholder="Enter User ID">
            
            <button class="test-button" onclick="testFileClaim()">🧪 Test File Claim</button>
            <div id="result4"></div>
        </div>
        
        <!-- Test 5: Get Claims -->
        <div class="test-section">
            <h2>5. Get User Claims</h2>
            <p>Retrieve all claims for a user.</p>
            
            <label>User ID:</label>
            <input type="number" id="getClaimsUserId" value="1" placeholder="Enter User ID">
            
            <label>Status Filter:</label>
            <select id="claimsStatus" style="width: 100%; padding: 8px;">
                <option value="all">All Claims</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            
            <button class="test-button" onclick="testGetClaims()">🧪 Test Get Claims</button>
            <div id="result5"></div>
        </div>
        
        <hr>
        <p style="color: #666; font-size: 14px;">
            💡 <strong>Note:</strong> Make sure the database migration has been run and 
            you have test data in the bookings table before testing policy creation.
        </p>
    </div>
</body>
</html>
