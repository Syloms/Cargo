<?php
/**
 * Simple PDF test to diagnose the issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PDF Generation Test</h1>";

// Test 1: Check TCPDF exists
echo "<h2>Test 1: Check TCPDF Library</h2>";
$tcpdfPath = __DIR__ . '/../../vendor/tcpdf/TCPDF-main/tcpdf.php';
if (file_exists($tcpdfPath)) {
    echo "✓ TCPDF found at: $tcpdfPath<br>";
} else {
    echo "✗ TCPDF NOT found at: $tcpdfPath<br>";
    die("Cannot continue without TCPDF");
}

// Test 2: Load TCPDF
echo "<h2>Test 2: Load TCPDF</h2>";
try {
    require_once $tcpdfPath;
    echo "✓ TCPDF loaded successfully<br>";
} catch (Exception $e) {
    echo "✗ Error loading TCPDF: " . $e->getMessage() . "<br>";
    die();
}

// Test 3: Create PDF object
echo "<h2>Test 3: Create PDF Object</h2>";
try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    echo "✓ PDF object created successfully<br>";
} catch (Exception $e) {
    echo "✗ Error creating PDF: " . $e->getMessage() . "<br>";
    die();
}

// Test 4: Check database
echo "<h2>Test 4: Check Database Connection</h2>";
$dbPath = __DIR__ . '/../../include/db.php';
if (file_exists($dbPath)) {
    echo "✓ Database config found<br>";
    require_once $dbPath;
    if (isset($conn) && $conn) {
        echo "✓ Database connected<br>";
        
        // Test 5: Check for policy
        echo "<h2>Test 5: Check Policy Data</h2>";
        $policyId = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 1;
        echo "Checking for policy ID: $policyId<br>";
        
        $query = "SELECT * FROM insurance_policies WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $policyId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "✓ Policy found<br>";
            $policy = $result->fetch_assoc();
            echo "<pre>";
            print_r(array_keys($policy));
            echo "</pre>";
        } else {
            echo "✗ Policy not found<br>";
        }
    } else {
        echo "✗ Database connection failed<br>";
    }
} else {
    echo "✗ Database config not found at: $dbPath<br>";
}

echo "<h2>Test 6: Simple PDF Generation</h2>";
try {
    $pdf->SetCreator('Test');
    $pdf->SetTitle('Test PDF');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Test PDF Generation', 0, 1);
    
    // Try to output
    $pdf->Output('test.pdf', 'D');
    echo "✓ PDF generated and downloaded<br>";
} catch (Exception $e) {
    echo "✗ Error generating PDF: " . $e->getMessage() . "<br>";
}
?>
