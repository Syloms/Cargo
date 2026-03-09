<?php
/**
 * ============================================================================
 * TESTING DASHBOARD - Central Hub
 * Main entry point for all testing tools
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
    <title>CarGo Testing Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .hero-section {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .hero-title {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }
        .hero-subtitle {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 30px;
        }
        .tool-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .tool-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        .tool-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
        }
        .tool-icon-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .tool-icon-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .tool-icon-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .tool-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .tool-description {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .tool-features {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
            text-align: left;
        }
        .tool-features li {
            padding: 8px 0;
            font-size: 13px;
            color: #495057;
        }
        .tool-features li i {
            color: #28a745;
            margin-right: 8px;
        }
        .btn-launch {
            width: 100%;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .info-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .workflow-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0;
            flex-wrap: wrap;
            gap: 20px;
        }
        .workflow-step {
            flex: 1;
            min-width: 200px;
            text-align: center;
            position: relative;
        }
        .workflow-step::after {
            content: '→';
            position: absolute;
            right: -30px;
            top: 20px;
            font-size: 30px;
            color: #667eea;
        }
        .workflow-step:last-child::after {
            display: none;
        }
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-weight: bold;
            font-size: 20px;
        }
        .step-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .step-desc {
            font-size: 12px;
            color: #6c757d;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Hero Section -->
    <div class="hero-section">
        <h1 class="hero-title"><i class="bi bi-bug-fill"></i> Testing Dashboard</h1>
        <p class="hero-subtitle">Comprehensive testing and verification tools for CarGo Admin & Flutter App</p>
        
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number">3</div>
                <div class="stat-label">Testing Tools</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">40+</div>
                <div class="stat-label">API Endpoints</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">18</div>
                <div class="stat-label">Live Tests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">5</div>
                <div class="stat-label">Critical Tables</div>
            </div>
        </div>
    </div>

    <!-- Testing Workflow -->
    <div class="info-section">
        <h3 class="text-center mb-4"><i class="bi bi-diagram-3"></i> Recommended Testing Workflow</h3>
        <div class="workflow-steps">
            <div class="workflow-step">
                <div class="step-number">1</div>
                <div class="step-title">Database Check</div>
                <div class="step-desc">Verify tables & columns</div>
            </div>
            <div class="workflow-step">
                <div class="step-number">2</div>
                <div class="step-title">API Analysis</div>
                <div class="step-desc">Check endpoint files</div>
            </div>
            <div class="workflow-step">
                <div class="step-number">3</div>
                <div class="step-title">Live Testing</div>
                <div class="step-desc">Execute API calls</div>
            </div>
            <div class="workflow-step">
                <div class="step-number">4</div>
                <div class="step-title">Review Results</div>
                <div class="step-desc">Fix issues found</div>
            </div>
        </div>
    </div>

    <!-- Testing Tools Grid -->
    <div class="tool-grid">
        <!-- Tool 1: Database Analyzer -->
        <div class="tool-card" onclick="window.location.href='tmp_rovodev_database_query_analyzer.php'">
            <div class="tool-icon tool-icon-1">
                <i class="bi bi-database"></i>
            </div>
            <div class="tool-title">Database Query Analyzer</div>
            <div class="tool-description">
                Comprehensive database structure verification and query testing
            </div>
            <ul class="tool-features">
                <li><i class="bi bi-check-circle-fill"></i> Verify table structures</li>
                <li><i class="bi bi-check-circle-fill"></i> Check required columns</li>
                <li><i class="bi bi-check-circle-fill"></i> Test SQL queries</li>
                <li><i class="bi bi-check-circle-fill"></i> Get recommendations</li>
            </ul>
            <button class="btn btn-primary btn-launch">
                <i class="bi bi-play-fill"></i> Launch Database Analyzer
            </button>
        </div>

        <!-- Tool 2: Flutter API Analyzer -->
        <div class="tool-card" onclick="window.location.href='tmp_rovodev_flutter_api_analyzer.php'">
            <div class="tool-icon tool-icon-2">
                <i class="bi bi-phone"></i>
            </div>
            <div class="tool-title">Flutter API Analyzer</div>
            <div class="tool-description">
                Scan and verify all API endpoints used by the Flutter application
            </div>
            <ul class="tool-features">
                <li><i class="bi bi-check-circle-fill"></i> List all endpoints</li>
                <li><i class="bi bi-check-circle-fill"></i> Check file existence</li>
                <li><i class="bi bi-check-circle-fill"></i> Verify implementations</li>
                <li><i class="bi bi-check-circle-fill"></i> Scan service files</li>
            </ul>
            <button class="btn btn-warning btn-launch">
                <i class="bi bi-play-fill"></i> Launch API Analyzer
            </button>
        </div>

        <!-- Tool 3: API Endpoint Tester -->
        <div class="tool-card" onclick="window.location.href='tmp_rovodev_api_endpoint_tester.php'">
            <div class="tool-icon tool-icon-3">
                <i class="bi bi-rocket-takeoff"></i>
            </div>
            <div class="tool-title">API Endpoint Tester</div>
            <div class="tool-description">
                Live testing of all API endpoints with real HTTP requests and validation
            </div>
            <ul class="tool-features">
                <li><i class="bi bi-check-circle-fill"></i> Execute live API calls</li>
                <li><i class="bi bi-check-circle-fill"></i> Validate responses</li>
                <li><i class="bi bi-check-circle-fill"></i> Check expected fields</li>
                <li><i class="bi bi-check-circle-fill"></i> Real-time results</li>
            </ul>
            <button class="btn btn-success btn-launch">
                <i class="bi bi-play-fill"></i> Launch API Tester
            </button>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="info-section">
        <h3 class="mb-3"><i class="bi bi-lightning-charge"></i> Quick Actions</h3>
        <div class="d-flex gap-2 flex-wrap justify-content-center">
            <a href="tmp_rovodev_database_query_analyzer.php" class="btn btn-outline-primary">
                <i class="bi bi-1-circle"></i> Start with Database
            </a>
            <a href="tmp_rovodev_api_endpoint_tester.php" class="btn btn-outline-success">
                <i class="bi bi-lightning"></i> Quick API Test
            </a>
            <a href="payouts.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Admin
            </a>
            <button onclick="window.open('../../COMPREHENSIVE_TESTING_GUIDE.md')" class="btn btn-outline-info">
                <i class="bi bi-book"></i> View Documentation
            </button>
        </div>
    </div>

    <!-- Recent Updates -->
    <div class="info-section">
        <h3 class="mb-3"><i class="bi bi-clock-history"></i> Recent Updates</h3>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <strong>Migration Completed:</strong> transfer_proof column added to payouts table
        </div>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> <strong>New Feature:</strong> Owners can now view transfer proof images in Flutter app
        </div>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> <strong>Action Needed:</strong> Run comprehensive tests to ensure all systems operational
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-4" style="color: white;">
        <p><small>CarGo Testing Dashboard v1.0 | Created for comprehensive system verification</small></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
