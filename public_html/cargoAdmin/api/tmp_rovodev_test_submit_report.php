<?php
/**
 * Test Script for Submit Report
 * This simulates the Flutter app submitting a report
 */

header("Content-Type: application/json");
require_once "../include/db.php";

echo "=== Testing Report Submission ===\n\n";

// 1. Check if tables exist
echo "1. Checking tables...\n";
$tables = ['reports', 'report_logs', 'users', 'cars'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo "   - $table: " . ($result->num_rows > 0 ? "EXISTS" : "MISSING") . "\n";
}

echo "\n2. Checking reports table structure...\n";
$reportTableCheck = $conn->query("SHOW TABLES LIKE 'reports'");
if ($reportTableCheck->num_rows > 0) {
    $columns = $conn->query("DESCRIBE reports");
    echo "   Columns: ";
    $cols = [];
    while ($row = $columns->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    echo implode(", ", $cols) . "\n";
} else {
    echo "   ❌ reports table does NOT exist!\n";
    echo "\n   You need to run the setup script first.\n";
    echo "   Access: /cargo/public_html/cargoAdmin/api/tmp_rovodev_setup_reports_system.php\n";
    exit;
}

echo "\n3. Testing sample report insertion...\n";

// Get a real user ID
$userQuery = $conn->query("SELECT id, fullname FROM users LIMIT 1");
if ($userQuery->num_rows == 0) {
    echo "   ❌ No users found in database\n";
    exit;
}
$user = $userQuery->fetch_assoc();
$testReporterId = $user['id'];
echo "   Using reporter ID: $testReporterId ({$user['fullname']})\n";

// Get a real car ID
$carQuery = $conn->query("SELECT id, brand, model FROM cars LIMIT 1");
if ($carQuery->num_rows == 0) {
    echo "   ❌ No cars found in database\n";
    exit;
}
$car = $carQuery->fetch_assoc();
$testCarId = $car['id'];
echo "   Using car ID: $testCarId ({$car['brand']} {$car['model']})\n";

// Test data
$testData = [
    'reporter_id' => $testReporterId,
    'report_type' => 'car',
    'reported_id' => $testCarId,
    'reason' => 'Misleading information',
    'details' => 'This is a test report with at least 20 characters to meet validation requirements.',
    'image_path' => null
];

try {
    $stmt = $conn->prepare("
        INSERT INTO reports 
        (reporter_id, report_type, reported_id, reason, details, image_path, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    
    $stmt->bind_param(
        "isssss",
        $testData['reporter_id'],
        $testData['report_type'],
        $testData['reported_id'],
        $testData['reason'],
        $testData['details'],
        $testData['image_path']
    );
    
    if ($stmt->execute()) {
        $reportId = $conn->insert_id;
        echo "   ✅ Report inserted successfully! ID: $reportId\n";
        
        // Test report_logs insertion
        echo "\n4. Testing report_logs insertion...\n";
        $logStmt = $conn->prepare("
            INSERT INTO report_logs 
            (report_id, action, performed_by, notes, created_at)
            VALUES (?, 'created', ?, 'Test log entry', NOW())
        ");
        $logStmt->bind_param("ii", $reportId, $testReporterId);
        
        if ($logStmt->execute()) {
            echo "   ✅ Log entry created successfully!\n";
        } else {
            echo "   ❌ Failed to create log: " . $logStmt->error . "\n";
        }
        
        // Retrieve the report
        echo "\n5. Verifying report retrieval...\n";
        $checkStmt = $conn->prepare("SELECT * FROM reports WHERE id = ?");
        $checkStmt->bind_param("i", $reportId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $report = $result->fetch_assoc();
            echo "   ✅ Report retrieved:\n";
            echo "      - Type: {$report['report_type']}\n";
            echo "      - Reason: {$report['reason']}\n";
            echo "      - Status: {$report['status']}\n";
            echo "      - Created: {$report['created_at']}\n";
        }
        
        // Clean up test data
        echo "\n6. Cleaning up test data...\n";
        $conn->query("DELETE FROM report_logs WHERE report_id = $reportId");
        $conn->query("DELETE FROM reports WHERE id = $reportId");
        echo "   ✅ Test data cleaned up\n";
        
    } else {
        echo "   ❌ Failed to insert report: " . $stmt->error . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "\nIf all tests passed, the reporting system is ready!\n";
echo "If not, check the error messages above.\n";

$conn->close();
?>
