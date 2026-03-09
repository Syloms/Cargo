<?php
/**
 * Complete Insurance System API Test
 * Tests all insurance endpoints with real data
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'include/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insurance System Test Suite</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .number { font-size: 36px; font-weight: bold; color: #667eea; }
        .test-section { background: white; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .test-header { background: #667eea; color: white; padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .test-header:hover { background: #5568d3; }
        .test-body { padding: 20px; display: none; }
        .test-body.active { display: block; }
        .test-item { margin-bottom: 20px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .test-button { background: #667eea; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.3s; }
        .test-button:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
        .test-button:disabled { background: #ccc; cursor: not-allowed; }
        .result { margin-top: 15px; padding: 15px; border-radius: 8px; }
        .success { background: #e8f5e9; border-left: 4px solid #4caf50; }
        .error { background: #ffebee; border-left: 4px solid #f44336; }
        .loading { background: #e3f2fd; border-left: 4px solid #2196f3; }
        .warning { background: #fff3e0; border-left: 4px solid #ff9800; }
        pre { background: #263238; color: #aed581; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 12px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #4caf50; color: white; }
        .badge-error { background: #f44336; color: white; }
        .badge-warning { background: #ff9800; color: white; }
        .badge-info { background: #2196f3; color: white; }
        input, textarea, select { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        label { font-weight: 600; display: block; margin-top: 10px; color: #333; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .chevron { transition: transform 0.3s; }
        .chevron.down { transform: rotate(180deg); }
        .timestamp { color: #999; font-size: 12px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ Insurance System Test Suite</h1>
        <p>Complete testing for all insurance API endpoints</p>
    </div>

    <div class="container">
        <!-- Database Statistics -->
        <div class="stats">
            <?php
            // Get statistics
            $stats = [];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM insurance_policies");
            $stats['total_policies'] = $result->fetch_assoc()['total'] ?? 0;
            
            $result = $conn->query("SELECT COUNT(*) as total FROM insurance_policies WHERE status = 'active'");
            $stats['active_policies'] = $result->fetch_assoc()['total'] ?? 0;
            
            $result = $conn->query("SELECT COUNT(*) as total FROM insurance_claims");
            $stats['total_claims'] = $result->fetch_assoc()['total'] ?? 0;
            
            $result = $conn->query("SELECT COUNT(*) as total FROM insurance_claims WHERE status IN ('submitted', 'under_review')");
            $stats['pending_claims'] = $result->fetch_assoc()['total'] ?? 0;
            
            $result = $conn->query("SELECT SUM(premium_amount) as total FROM insurance_policies");
            $stats['total_premium'] = $result->fetch_assoc()['total'] ?? 0;
            
            $result = $conn->query("SELECT COUNT(*) as total FROM insurance_coverage_types WHERE is_active = 1");
            $stats['coverage_types'] = $result->fetch_assoc()['total'] ?? 0;
            ?>
            
            <div class="stat-card">
                <h3>Total Policies</h3>
                <div class="number"><?php echo $stats['total_policies']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Policies</h3>
                <div class="number"><?php echo $stats['active_policies']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Claims</h3>
                <div class="number"><?php echo $stats['total_claims']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Claims</h3>
                <div class="number"><?php echo $stats['pending_claims']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Premium</h3>
                <div class="number">₱<?php echo number_format($stats['total_premium'], 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Coverage Types</h3>
                <div class="number"><?php echo $stats['coverage_types']; ?></div>
            </div>
        </div>

        <!-- Test Section 1: Coverage Types -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>📋 Test 1: Get Coverage Types</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>Fetch all available insurance coverage types with their features and pricing.</p>
                    <button class="test-button" onclick="testGetCoverageTypes()">Run Test</button>
                    <div id="result-coverage-types"></div>
                </div>
            </div>
        </div>

        <!-- Test Section 2: Create Policy -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>✅ Test 2: Create Insurance Policy</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>Create a new insurance policy for a booking.</p>
                    <div class="form-grid">
                        <div>
                            <label>Booking ID:</label>
                            <input type="number" id="create-booking-id" value="1" placeholder="Enter booking ID">
                            
                            <label>User ID:</label>
                            <input type="number" id="create-user-id" value="7" placeholder="Enter user ID">
                            
                            <label>Coverage Type:</label>
                            <select id="create-coverage-type">
                                <option value="basic">Basic</option>
                                <option value="standard">Standard</option>
                                <option value="premium">Premium</option>
                                <option value="comprehensive">Comprehensive</option>
                            </select>
                        </div>
                        <div>
                            <label>Rental Amount (₱):</label>
                            <input type="number" id="create-rental-amount" value="1680" placeholder="Enter rental amount">
                            
                            <label>Rental Days:</label>
                            <input type="number" id="create-rental-days" value="1" placeholder="Enter number of days">
                            
                            <label>Terms Accepted:</label>
                            <select id="create-terms-accepted">
                                <option value="true">Yes</option>
                                <option value="false">No</option>
                            </select>
                        </div>
                    </div>
                    <button class="test-button" onclick="testCreatePolicy()">Create Policy</button>
                    <div id="result-create-policy"></div>
                </div>
            </div>
        </div>

        <!-- Test Section 3: Get Policy -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>🔍 Test 3: Get Policy Details</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>Retrieve insurance policy details for a specific booking.</p>
                    <label>Booking ID:</label>
                    <input type="number" id="get-policy-booking-id" value="1" placeholder="Enter booking ID">
                    <label>User ID:</label>
                    <input type="number" id="get-policy-user-id" value="7" placeholder="Enter user ID">
                    <button class="test-button" onclick="testGetPolicy()">Get Policy</button>
                    <div id="result-get-policy"></div>
                </div>
            </div>
        </div>

        <!-- Test Section 4: File Claim -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>🚨 Test 4: File Insurance Claim</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>File a new insurance claim for an incident.</p>
                    <div class="form-grid">
                        <div>
                            <label>Policy ID:</label>
                            <input type="number" id="claim-policy-id" value="1" placeholder="Enter policy ID">
                            
                            <label>Booking ID:</label>
                            <input type="number" id="claim-booking-id" value="1" placeholder="Enter booking ID">
                            
                            <label>User ID:</label>
                            <input type="number" id="claim-user-id" value="7" placeholder="Enter user ID">
                            
                            <label>Claim Type:</label>
                            <select id="claim-type">
                                <option value="collision">Collision</option>
                                <option value="theft">Theft</option>
                                <option value="liability">Third Party Liability</option>
                                <option value="personal_injury">Personal Injury</option>
                                <option value="property_damage">Property Damage</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label>Incident Date:</label>
                            <input type="date" id="claim-incident-date" value="<?php echo date('Y-m-d'); ?>">
                            
                            <label>Incident Location:</label>
                            <input type="text" id="claim-incident-location" value="San Francisco, Caraga" placeholder="Location">
                            
                            <label>Claimed Amount (₱):</label>
                            <input type="number" id="claim-amount" value="5000" placeholder="Enter claimed amount">
                            
                            <label>Police Report Number (Optional):</label>
                            <input type="text" id="claim-police-report" placeholder="PR-2024-XXXXX">
                        </div>
                    </div>
                    <label>Incident Description:</label>
                    <textarea id="claim-description" rows="4" placeholder="Describe the incident in detail...">Vehicle collision with another car during rental period. Front bumper and headlight damaged.</textarea>
                    <button class="test-button" onclick="testFileClaim()">File Claim</button>
                    <div id="result-file-claim"></div>
                </div>
            </div>
        </div>

        <!-- Test Section 5: Get Claims -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>📄 Test 5: Get User Claims</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>Retrieve all claims filed by a user.</p>
                    <label>User ID:</label>
                    <input type="number" id="get-claims-user-id" value="7" placeholder="Enter user ID">
                    <label>Status Filter:</label>
                    <select id="get-claims-status">
                        <option value="all">All</option>
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="paid">Paid</option>
                    </select>
                    <button class="test-button" onclick="testGetClaims()">Get Claims</button>
                    <div id="result-get-claims"></div>
                </div>
            </div>
        </div>

        <!-- Test Section 6: Admin - Get All Policies -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>👨‍💼 Test 6: Admin - Get All Policies</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>Admin view: Get all insurance policies with filtering.</p>
                    <label>Status Filter:</label>
                    <select id="admin-policies-status">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="claimed">Claimed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <label>Search (Optional):</label>
                    <input type="text" id="admin-policies-search" placeholder="Search by policy number, user email...">
                    <button class="test-button" onclick="testAdminGetPolicies()">Get All Policies</button>
                    <div id="result-admin-policies"></div>
                </div>
            </div>
        </div>

        <!-- Test Section 7: Admin - Get All Claims -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>👨‍💼 Test 7: Admin - Get All Claims</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>Admin view: Get all insurance claims for review.</p>
                    <label>Status Filter:</label>
                    <select id="admin-claims-status">
                        <option value="all">All</option>
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button class="test-button" onclick="testAdminGetClaims()">Get All Claims</button>
                    <div id="result-admin-claims"></div>
                </div>
            </div>
        </div>

        <!-- Test Section 8: Admin - Approve Claim -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>✅ Test 8: Admin - Approve Claim</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>Admin action: Approve an insurance claim and set payout amount.</p>
                    <label>Claim ID:</label>
                    <input type="number" id="approve-claim-id" value="1" placeholder="Enter claim ID">
                    <label>Approved Amount (₱):</label>
                    <input type="number" id="approve-amount" value="4500" placeholder="Enter approved amount">
                    <label>Review Notes:</label>
                    <textarea id="approve-notes" rows="3" placeholder="Add review notes...">Claim verified. Approved for payout after deductible.</textarea>
                    <button class="test-button" onclick="testApproveClaim()">Approve Claim</button>
                    <div id="result-approve-claim"></div>
                </div>
            </div>
        </div>

        <!-- Test Section 9: Admin - Reject Claim -->
        <div class="test-section">
            <div class="test-header" onclick="toggleSection(this)">
                <span>❌ Test 9: Admin - Reject Claim</span>
                <span class="chevron">▼</span>
            </div>
            <div class="test-body">
                <div class="test-item">
                    <p>Admin action: Reject an insurance claim with reason.</p>
                    <label>Claim ID:</label>
                    <input type="number" id="reject-claim-id" value="2" placeholder="Enter claim ID">
                    <label>Rejection Reason:</label>
                    <textarea id="reject-reason" rows="3" placeholder="Provide reason for rejection...">Insufficient evidence provided. Incident does not fall under policy coverage.</textarea>
                    <button class="test-button" onclick="testRejectClaim()">Reject Claim</button>
                    <div id="result-reject-claim"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSection(header) {
            const body = header.nextElementSibling;
            const chevron = header.querySelector('.chevron');
            body.classList.toggle('active');
            chevron.classList.toggle('down');
        }

        async function apiCall(endpoint, method = 'GET', data = null) {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };

            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }

            const url = method === 'GET' && data 
                ? `${endpoint}?${new URLSearchParams(data)}`
                : endpoint;

            const response = await fetch(url, options);
            return await response.json();
        }

        function displayResult(resultId, success, message, data = null) {
            const resultDiv = document.getElementById(resultId);
            const statusClass = success ? 'success' : 'error';
            const statusBadge = success 
                ? '<span class="badge badge-success">SUCCESS</span>' 
                : '<span class="badge badge-error">ERROR</span>';
            
            let html = `<div class="result ${statusClass}">
                ${statusBadge}
                <strong>${message}</strong>`;
            
            if (data) {
                html += `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            }
            
            html += `<div class="timestamp">⏰ ${new Date().toLocaleString()}</div></div>`;
            resultDiv.innerHTML = html;
        }

        async function testGetCoverageTypes() {
            displayResult('result-coverage-types', true, 'Testing...', null);
            try {
                const result = await apiCall('api/insurance/get_coverage_types.php');
                displayResult('result-coverage-types', result.success, result.message || 'Coverage types retrieved', result.data);
            } catch (error) {
                displayResult('result-coverage-types', false, 'API Error: ' + error.message);
            }
        }

        async function testCreatePolicy() {
            const data = {
                booking_id: parseInt(document.getElementById('create-booking-id').value),
                user_id: parseInt(document.getElementById('create-user-id').value),
                coverage_type: document.getElementById('create-coverage-type').value,
                rental_amount: parseFloat(document.getElementById('create-rental-amount').value),
                rental_days: parseInt(document.getElementById('create-rental-days').value),
                terms_accepted: document.getElementById('create-terms-accepted').value === 'true'
            };
            
            displayResult('result-create-policy', true, 'Creating policy...', null);
            try {
                const result = await apiCall('api/insurance/create_policy.php', 'POST', data);
                displayResult('result-create-policy', result.success, result.message, result.data);
            } catch (error) {
                displayResult('result-create-policy', false, 'API Error: ' + error.message);
            }
        }

        async function testGetPolicy() {
            const data = {
                booking_id: document.getElementById('get-policy-booking-id').value,
                user_id: document.getElementById('get-policy-user-id').value
            };
            
            displayResult('result-get-policy', true, 'Fetching policy...', null);
            try {
                const result = await apiCall('api/insurance/get_policy.php', 'GET', data);
                displayResult('result-get-policy', result.success, result.message || 'Policy retrieved', result.data);
            } catch (error) {
                displayResult('result-get-policy', false, 'API Error: ' + error.message);
            }
        }

        async function testFileClaim() {
            const data = {
                policy_id: parseInt(document.getElementById('claim-policy-id').value),
                booking_id: parseInt(document.getElementById('claim-booking-id').value),
                user_id: parseInt(document.getElementById('claim-user-id').value),
                claim_type: document.getElementById('claim-type').value,
                incident_date: document.getElementById('claim-incident-date').value,
                incident_location: document.getElementById('claim-incident-location').value,
                incident_description: document.getElementById('claim-description').value,
                claimed_amount: parseFloat(document.getElementById('claim-amount').value),
                police_report_number: document.getElementById('claim-police-report').value
            };
            
            displayResult('result-file-claim', true, 'Filing claim...', null);
            try {
                const result = await apiCall('api/insurance/file_claim.php', 'POST', data);
                displayResult('result-file-claim', result.success, result.message, result.data);
            } catch (error) {
                displayResult('result-file-claim', false, 'API Error: ' + error.message);
            }
        }

        async function testGetClaims() {
            const data = {
                user_id: document.getElementById('get-claims-user-id').value,
                status: document.getElementById('get-claims-status').value
            };
            
            displayResult('result-get-claims', true, 'Fetching claims...', null);
            try {
                const result = await apiCall('api/insurance/get_claims.php', 'GET', data);
                displayResult('result-get-claims', result.success, result.message || `Found ${result.count || 0} claims`, result.data);
            } catch (error) {
                displayResult('result-get-claims', false, 'API Error: ' + error.message);
            }
        }

        async function testAdminGetPolicies() {
            const data = {
                status: document.getElementById('admin-policies-status').value,
                search: document.getElementById('admin-policies-search').value,
                page: 1,
                limit: 20
            };
            
            displayResult('result-admin-policies', true, 'Fetching all policies...', null);
            try {
                const result = await apiCall('api/insurance/admin/get_all_policies.php', 'GET', data);
                displayResult('result-admin-policies', result.success, 
                    result.message || `Found ${result.pagination?.total || 0} policies`, 
                    { policies: result.data, pagination: result.pagination });
            } catch (error) {
                displayResult('result-admin-policies', false, 'API Error: ' + error.message);
            }
        }

        async function testAdminGetClaims() {
            const data = {
                status: document.getElementById('admin-claims-status').value,
                page: 1
            };
            
            displayResult('result-admin-claims', true, 'Fetching all claims...', null);
            try {
                const result = await apiCall('api/insurance/admin/get_all_claims.php', 'GET', data);
                displayResult('result-admin-claims', result.success, 
                    result.message || `Found ${result.data?.length || 0} claims`, 
                    result.data);
            } catch (error) {
                displayResult('result-admin-claims', false, 'API Error: ' + error.message);
            }
        }

        async function testApproveClaim() {
            const data = {
                claim_id: parseInt(document.getElementById('approve-claim-id').value),
                approved_amount: parseFloat(document.getElementById('approve-amount').value),
                review_notes: document.getElementById('approve-notes').value,
                admin_id: 1
            };
            
            displayResult('result-approve-claim', true, 'Approving claim...', null);
            try {
                const result = await apiCall('api/insurance/admin/approve_claim.php', 'POST', data);
                displayResult('result-approve-claim', result.success, result.message, result.data);
            } catch (error) {
                displayResult('result-approve-claim', false, 'API Error: ' + error.message);
            }
        }

        async function testRejectClaim() {
            const data = {
                claim_id: parseInt(document.getElementById('reject-claim-id').value),
                rejection_reason: document.getElementById('reject-reason').value,
                admin_id: 1
            };
            
            displayResult('result-reject-claim', true, 'Rejecting claim...', null);
            try {
                const result = await apiCall('api/insurance/admin/reject_claim.php', 'POST', data);
                displayResult('result-reject-claim', result.success, result.message);
            } catch (error) {
                displayResult('result-reject-claim', false, 'API Error: ' + error.message);
            }
        }

        // Open first section by default
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.test-header').click();
        });
    </script>
</body>
</html>
