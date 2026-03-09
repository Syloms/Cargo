<?php
/**
 * Generate Insurance Policy PDF Certificate
 * Creates a professional PDF document for insurance policies
 */

// Error handling - but buffer output for PDF download
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors - they break PDF output
ini_set('log_errors', 1);

// Start output buffering to prevent any output before PDF
ob_start();

// Check if files exist before requiring
$dbPath = __DIR__ . '/../../include/db.php';
$tcpdfPath = __DIR__ . '/../../vendor/tcpdf/TCPDF-main/tcpdf.php';

if (!file_exists($dbPath)) {
    ob_end_clean();
    die("Error: Database config file not found");
}

if (!file_exists($tcpdfPath)) {
    ob_end_clean();
    die("Error: TCPDF library not found");
}

try {
    require_once $dbPath;
    require_once $tcpdfPath;
} catch (Exception $e) {
    ob_end_clean();
    die("Error loading libraries: " . $e->getMessage());
} catch (Error $e) {
    ob_end_clean();
    die("Fatal error loading libraries: " . $e->getMessage());
}

if (!isset($_GET['policy_id'])) {
    ob_end_clean();
    die("Error: Policy ID required");
}

$policyId = intval($_GET['policy_id']);

if (!isset($conn) || !$conn) {
    ob_end_clean();
    die("Error: Database connection failed");
}

// Fetch policy details
$query = "
    SELECT 
        ip.*,
        prov.provider_name,
        prov.contact_email as provider_email,
        prov.contact_phone as provider_phone,
        prov.address as provider_address,
        b.pickup_date,
        b.return_date,
        owner.fullname as owner_name,
        owner.email as owner_email,
        owner.phone as owner_phone,
        renter.fullname as renter_name,
        renter.email as renter_email,
        renter.phone as renter_phone,
        CASE 
            WHEN ip.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model, ' (', c.car_year, ')')
            WHEN ip.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model, ' (', m.motorcycle_year, ')')
        END as vehicle_name,
        CASE 
            WHEN ip.vehicle_type = 'car' THEN c.plate_number
            WHEN ip.vehicle_type = 'motorcycle' THEN m.plate_number
        END as plate_number
    FROM insurance_policies ip
    JOIN insurance_providers prov ON ip.provider_id = prov.id
    JOIN bookings b ON ip.booking_id = b.id
    JOIN users owner ON ip.owner_id = owner.id
    JOIN users renter ON ip.user_id = renter.id
    LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
    LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
    WHERE ip.id = ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    ob_end_clean();
    die("Error preparing query: " . $conn->error);
}

$stmt->bind_param("i", $policyId);
if (!$stmt->execute()) {
    ob_end_clean();
    die("Error executing query: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    ob_end_clean();
    die("Error: Policy not found for ID: " . $policyId);
}

$policy = $result->fetch_assoc();

// Create PDF
try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
} catch (Exception $e) {
    ob_end_clean();
    die("Error creating PDF: " . $e->getMessage());
}

// Set document information
$pdf->SetCreator('Cargo Platform');
$pdf->SetAuthor('Cargo Insurance');
$pdf->SetTitle('Insurance Policy Certificate - ' . $policy['policy_number']);
$pdf->SetSubject('Insurance Policy Certificate');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Logo and Header (skip if image fails)
try {
    @$pdf->Image('https://cargoph.online/assets/cargo.png', 15, 15, 30, 0, 'PNG');
} catch (Exception $e) {
    // Continue without logo if it fails
}
$pdf->SetXY(50, 15);
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(255, 152, 0);
$pdf->Cell(0, 10, 'INSURANCE CERTIFICATE', 0, 1, 'L');

// Provider info
$pdf->SetXY(50, 25);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, $policy['provider_name'], 0, 1, 'L');
$pdf->SetX(50);
$pdf->Cell(0, 5, 'Email: ' . $policy['provider_email'], 0, 1, 'L');
$pdf->SetX(50);
$pdf->Cell(0, 5, 'Phone: ' . $policy['provider_phone'], 0, 1, 'L');

$pdf->Ln(10);

// Policy Number Box
$pdf->SetFillColor(255, 152, 0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Policy Number: ' . $policy['policy_number'], 0, 1, 'C', true);

$pdf->Ln(5);

// Policy Details Section
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'POLICY INFORMATION', 0, 1, 'L');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);

// Two column layout
$leftX = 15;
$rightX = 110;
$y = $pdf->GetY();

// Left column
$pdf->SetXY($leftX, $y);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Policy Start:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, date('M d, Y', strtotime($policy['policy_start'])), 0, 1);

$pdf->SetX($leftX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Policy End:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, date('M d, Y', strtotime($policy['policy_end'])), 0, 1);

$pdf->SetX($leftX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Status:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, strtoupper($policy['status']), 0, 1);

$pdf->SetX($leftX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Coverage Type:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, strtoupper($policy['coverage_type']), 0, 1);

// Right column
$pdf->SetXY($rightX, $y);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(35, 6, 'Premium:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, '₱' . number_format($policy['premium_amount'], 2), 0, 1);

$pdf->SetXY($rightX, $y + 6);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(35, 6, 'Coverage Limit:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, '₱' . number_format($policy['coverage_limit'], 2), 0, 1);

$pdf->SetXY($rightX, $y + 12);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(35, 6, 'Deductible:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, '₱' . number_format($policy['deductible'], 2), 0, 1);

$pdf->Ln(10);

// Vehicle Information
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'INSURED VEHICLE', 0, 1, 'L');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Vehicle:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['vehicle_name'], 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Plate Number:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['plate_number'] ?? 'N/A', 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Vehicle Type:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, strtoupper($policy['vehicle_type']), 0, 1);

$pdf->Ln(8);

// Owner Information
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'VEHICLE OWNER', 0, 1, 'L');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Name:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['owner_name'], 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Email:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['owner_email'], 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Phone:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['owner_phone'], 0, 1);

$pdf->Ln(8);

// Renter Information
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'RENTER (INSURED DRIVER)', 0, 1, 'L');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Name:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['renter_name'], 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Email:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['renter_email'], 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Phone:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['renter_phone'], 0, 1);

$pdf->Ln(8);

// Coverage Details
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'COVERAGE DETAILS', 0, 1, 'L');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(245, 245, 245);

$coverageItems = [
    ['Coverage', 'Amount'],
    ['Collision Damage', '₱' . number_format($policy['collision_coverage'], 2)],
    ['Third-Party Liability', '₱' . number_format($policy['liability_coverage'], 2)],
    ['Theft Coverage', '₱' . number_format($policy['theft_coverage'], 2)],
    ['Personal Injury', '₱' . number_format($policy['personal_injury_coverage'], 2)],
    ['Roadside Assistance', $policy['roadside_assistance'] ? 'Included' : 'Not Included'],
];

foreach ($coverageItems as $index => $item) {
    $fill = $index === 0;
    $pdf->Cell(95, 6, $item[0], 1, 0, 'L', $fill);
    $pdf->Cell(95, 6, $item[1], 1, 1, 'R', $fill);
}

$pdf->Ln(10);

// Footer
$pdf->SetY(-30);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell(0, 5, 'This is a computer-generated document. No signature required.', 0, 1, 'C');
$pdf->Cell(0, 5, 'Issued on ' . date('F d, Y \a\t H:i:s'), 0, 1, 'C');
$pdf->Cell(0, 5, 'For inquiries, contact: insurance@cargoph.online', 0, 1, 'C');

// Clear output buffer before sending PDF
ob_end_clean();

// Save or output PDF
$download = isset($_GET['download']) && $_GET['download'] == 1;

try {
    if ($download) {
        // Force download - output directly
        $pdf->Output('Policy_' . $policy['policy_number'] . '.pdf', 'D');
    } else {
        // Return base64 encoded PDF for API
        $pdfContent = $pdf->Output('', 'S');
        $base64 = base64_encode($pdfContent);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdf' => $base64,
            'filename' => 'Policy_' . $policy['policy_number'] . '.pdf'
        ]);
    }
} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
}

if (isset($conn)) {
    $conn->close();
}
?>
