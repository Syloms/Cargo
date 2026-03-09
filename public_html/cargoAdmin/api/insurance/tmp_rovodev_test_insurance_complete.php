<?php
/**
 * Comprehensive Insurance System Test
 * Tests all insurance API endpoints
 */

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Insurance System API Tests</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; }
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .test-header { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #2196F3; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .info { color: #FF9800; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        button { 
            background: #2196F3; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 4px; 
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #1976D2; }
        .test-result { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
        .test-result.pass { border-left-color: #4CAF50; background: #E8F5E9; }
        .test-result.fail { border-left-color: #f44336; background: #FFEBEE; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ Insurance System API Testing Dashboard</h1>
        
        <div class="test-section">
            <div class="test-header">Test Configuration</div>
            <p>Testing with:</p>
            <ul>
                <li>User ID: <strong id="testUserId">1</strong></li>
                <li>Booking ID: <strong id="testBookingId">1</strong></li>
                <li>API Base URL: <strong><?php echo $_SERVER['HTTP_HOST']; ?></strong></li>
            </ul>
            <button onclick="runAllTests()">▶️ Run All Tests</button>
            <button onclick="clearResults()">🗑️ Clear Results</button>
        </div>
        
        <div id="testResults"></div>
    </div>
    
    <script>
        const BASE_URL = window.location.origin + '/cargoAdmin/api/insurance';
        let testResults = [];
        
        async function runAllTests() {
            clearResults();
            addTestHeader('Starting Insurance System Tests...');
            
            // Test 1: Get Coverage Types
            await testGetCoverageTypes();
            
            // Test 2: Create Policy
            const policyId = await testCreatePolicy();
            
            if (policyId) {
                // Test 3: Get Policy
                await testGetPolicy(policyId);
                
                // Test 4: File Claim
                const claimId = await testFileClaim(policyId);
                
                if (claimId) {
                    // Test 5: Get Claim
                    await testGetClaim(claimId);
                    
                    // Test 6: Get All Claims
                    await testGetAllClaims();
                    
                    // Admin Tests
                    await testAdminGetAllPolicies();
                    await testAdminGetAllClaims();
                }
            }
            
            displaySummary();
        }
        
        async function testGetCoverageTypes() {
            const testName = 'Get Coverage Types';
            try {
                const response = await fetch(`${BASE_URL}/get_coverage_types.php`);
                const data = await response.json();
                
                if (data.success && data.data && data.data.length > 0) {
                    addTestResult(testName, true, `Found ${data.data.length} coverage types`, data);
                    return data.data;
                } else {
                    addTestResult(testName, false, 'No coverage types found', data);
                    return null;
                }
            } catch (error) {
                addTestResult(testName, false, error.message, null);
                return null;
            }
        }
        
        async function testCreatePolicy() {
            const testName = 'Create Insurance Policy';
            const userId = document.getElementById('testUserId').textContent;
            const bookingId = document.getElementById('testBookingId').textContent;
            
            const policyData = {
                user_id: parseInt(userId),
                booking_id: parseInt(bookingId),
                coverage_type_id: 2, // Standard coverage
                coverage_type: 'standard',
                pickup_date: '2026-02-10',
                return_date: '2026-02-15',
                rental_amount: 5000,
                vehicle_type: 'car'
            };
            
            try {
                const response = await fetch(`${BASE_URL}/create_policy.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(policyData)
                });
                const data = await response.json();
                
                if (data.success && data.data && data.data.policy_id) {
                    addTestResult(testName, true, 
                        `Policy created: ${data.data.policy_number} (ID: ${data.data.policy_id})`, 
                        data);
                    return data.data.policy_id;
                } else {
                    addTestResult(testName, false, data.message || 'Failed to create policy', data);
                    return null;
                }
            } catch (error) {
                addTestResult(testName, false, error.message, null);
                return null;
            }
        }
        
        async function testGetPolicy(policyId) {
            const testName = 'Get Policy Details';
            const userId = document.getElementById('testUserId').textContent;
            const bookingId = document.getElementById('testBookingId').textContent;
            
            try {
                const response = await fetch(
                    `${BASE_URL}/get_policy.php?policy_id=${policyId}&user_id=${userId}&booking_id=${bookingId}`
                );
                const data = await response.json();
                
                if (data.success && data.data) {
                    addTestResult(testName, true, 
                        `Retrieved policy ${data.data.policy_number}`, 
                        data);
                    return true;
                } else {
                    addTestResult(testName, false, data.message || 'Failed to get policy', data);
                    return false;
                }
            } catch (error) {
                addTestResult(testName, false, error.message, null);
                return false;
            }
        }
        
        async function testFileClaim(policyId) {
            const testName = 'File Insurance Claim';
            const userId = document.getElementById('testUserId').textContent;
            const bookingId = document.getElementById('testBookingId').textContent;
            
            const claimData = {
                policy_id: policyId,
                booking_id: parseInt(bookingId),
                user_id: parseInt(userId),
                claim_type: 'collision',
                incident_date: '2026-02-12',
                incident_location: 'EDSA, Quezon City',
                incident_description: 'Minor collision with another vehicle while changing lanes',
                claimed_amount: 15000,
                police_report_number: 'PR-2026-001234',
                evidence_photos: ['photo1.jpg', 'photo2.jpg']
            };
            
            try {
                const response = await fetch(`${BASE_URL}/file_claim.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(claimData)
                });
                const data = await response.json();
                
                if (data.success && data.data && data.data.claim_id) {
                    addTestResult(testName, true, 
                        `Claim filed: ${data.data.claim_number} (ID: ${data.data.claim_id})`, 
                        data);
                    return data.data.claim_id;
                } else {
                    addTestResult(testName, false, data.message || 'Failed to file claim', data);
                    return null;
                }
            } catch (error) {
                addTestResult(testName, false, error.message, null);
                return null;
            }
        }
        
        async function testGetClaim(claimId) {
            const testName = 'Get Claim Details';
            const userId = document.getElementById('testUserId').textContent;
            
            try {
                const response = await fetch(
                    `${BASE_URL}/get_claims.php?claim_id=${claimId}&user_id=${userId}`
                );
                const data = await response.json();
                
                if (data.success && data.data) {
                    addTestResult(testName, true, 
                        `Retrieved claim ${data.data.claim_number}`, 
                        data);
                    return true;
                } else {
                    addTestResult(testName, false, data.message || 'Failed to get claim', data);
                    return false;
                }
            } catch (error) {
                addTestResult(testName, false, error.message, null);
                return false;
            }
        }
        
        async function testGetAllClaims() {
            const testName = 'Get All User Claims';
            const userId = document.getElementById('testUserId').textContent;
            
            try {
                const response = await fetch(`${BASE_URL}/get_claims.php?user_id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    addTestResult(testName, true, 
                        `Found ${data.count || 0} claims for user`, 
                        data);
                    return true;
                } else {
                    addTestResult(testName, false, data.message || 'Failed to get claims', data);
                    return false;
                }
            } catch (error) {
                addTestResult(testName, false, error.message, null);
                return false;
            }
        }
        
        async function testAdminGetAllPolicies() {
            const testName = 'Admin: Get All Policies';
            
            try {
                const response = await fetch(`${BASE_URL}/admin/get_all_policies.php`);
                const data = await response.json();
                
                if (data.success) {
                    addTestResult(testName, true, 
                        `Found ${data.total_policies || 0} total policies`, 
                        data);
                    return true;
                } else {
                    addTestResult(testName, false, data.message || 'Failed to get policies', data);
                    return false;
                }
            } catch (error) {
                addTestResult(testName, false, error.message, null);
                return false;
            }
        }
        
        async function testAdminGetAllClaims() {
            const testName = 'Admin: Get All Claims';
            
            try {
                const response = await fetch(`${BASE_URL}/admin/get_all_claims.php`);
                const data = await response.json();
                
                if (data.success) {
                    addTestResult(testName, true, 
                        `Found ${data.total_claims || 0} total claims`, 
                        data);
                    return true;
                } else {
                    addTestResult(testName, false, data.message || 'Failed to get claims', data);
                    return false;
                }
            } catch (error) {
                addTestResult(testName, false, error.message, null);
                return false;
            }
        }
        
        function addTestHeader(message) {
            const resultsDiv = document.getElementById('testResults');
            const header = document.createElement('div');
            header.className = 'test-section';
            header.innerHTML = `<div class="test-header">${message}</div>`;
            resultsDiv.appendChild(header);
        }
        
        function addTestResult(testName, passed, message, data) {
            testResults.push({ testName, passed, message, data });
            
            const resultsDiv = document.getElementById('testResults');
            const resultDiv = document.createElement('div');
            resultDiv.className = `test-result ${passed ? 'pass' : 'fail'}`;
            
            let html = `
                <div style="margin-bottom: 10px;">
                    <span style="font-weight: bold;">${passed ? '✅' : '❌'} ${testName}</span>
                    <br>
                    <span>${message}</span>
                </div>
            `;
            
            if (data) {
                html += `
                    <details>
                        <summary style="cursor: pointer; color: #666;">View Response Data</summary>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </details>
                `;
            }
            
            resultDiv.innerHTML = html;
            resultsDiv.appendChild(resultDiv);
        }
        
        function displaySummary() {
            const passed = testResults.filter(r => r.passed).length;
            const failed = testResults.filter(r => !r.passed).length;
            const total = testResults.length;
            
            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'test-section';
            summaryDiv.innerHTML = `
                <div class="test-header">📊 Test Summary</div>
                <p>
                    Total Tests: <strong>${total}</strong><br>
                    Passed: <span class="success">${passed}</span><br>
                    Failed: <span class="error">${failed}</span><br>
                    Success Rate: <strong>${((passed/total)*100).toFixed(1)}%</strong>
                </p>
            `;
            
            document.getElementById('testResults').appendChild(summaryDiv);
        }
        
        function clearResults() {
            document.getElementById('testResults').innerHTML = '';
            testResults = [];
        }
        
        // Auto-run tests on page load
        window.addEventListener('load', () => {
            console.log('Insurance API Test Dashboard Loaded');
        });
    </script>
</body>
</html>
