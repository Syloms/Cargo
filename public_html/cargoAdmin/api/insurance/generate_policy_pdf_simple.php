<?php
/**
 * Simple PDF Generation - Working Version
 */

// Start output buffering
ob_start();

// Error logging only
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

// Load required files
try {
    require_once __DIR__ . '/../../include/db.php';
    require_once __DIR__ . '/../../vendor/tcpdf/TCPDF-main/tcpdf.php';
} catch (Exception $e) {
    ob_end_clean();
    die("Library Error: " . $e->getMessage());
}

// Get policy ID
if (!isset($_GET['policy_id'])) {
    ob_end_clean();
    die("Policy ID required");
}

$policyId = intval($_GET['policy_id']);

// Check database connection
if (!isset($conn) || !$conn) {
    ob_end_clean();
    die("Database connection failed");
}

// Auto-expire policies before generating PDF
$expireStmt = $conn->prepare("
    UPDATE insurance_policies 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND policy_end < NOW()
");
$expireStmt->execute();
$expireStmt->close();

// Get policy data with basic info
$query = "
    SELECT 
        ip.*,
        prov.provider_name,
        prov.contact_email as provider_email,
        prov.contact_phone as provider_phone,
        u.fullname as renter_name,
        u.email as renter_email,
        u.phone as renter_phone
    FROM insurance_policies ip
    LEFT JOIN insurance_providers prov ON ip.provider_id = prov.id
    LEFT JOIN users u ON ip.user_id = u.id
    WHERE ip.id = ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    ob_end_clean();
    die("Query Error: " . $conn->error);
}

$stmt->bind_param("i", $policyId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    ob_end_clean();
    die("Policy not found");
}

$policy = $result->fetch_assoc();

// Create PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document info
$pdf->SetCreator('Cargo Car Rental Platform');
$pdf->SetAuthor('Cargo Insurance Services');
$pdf->SetTitle('Insurance Certificate - ' . $policy['policy_number']);
$pdf->SetSubject('Vehicle Rental Insurance Policy');
$pdf->SetKeywords('Insurance, Policy, Cargo, Certificate, Vehicle Rental');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);

// Add page
$pdf->AddPage();

// ====================
// HEADER WITH BRANDING
// ====================
$pdf->SetFillColor(255, 152, 0); // Cargo Orange
$pdf->Rect(0, 0, 210, 35, 'F');

// Logo placeholder (text-based)
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 28);
$pdf->SetXY(15, 8);
$pdf->Cell(0, 10, 'CARGO', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(15, 18);
$pdf->Cell(0, 5, 'Car Rental Platform', 0, 1, 'L');

// Certificate title on right
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetXY(100, 8);
$pdf->Cell(95, 10, 'INSURANCE CERTIFICATE', 0, 1, 'R');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(100, 18);
$pdf->Cell(95, 5, 'Official Insurance Policy Document', 0, 1, 'R');

$pdf->Ln(10);

// Policy Number Badge
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(240, 248, 255); // Light blue background
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 12, 'POLICY NUMBER: ' . $policy['policy_number'], 0, 1, 'C', true);
$pdf->Ln(3);

// Status Badge
$statusColor = $policy['status'] === 'active' ? [76, 175, 80] : [244, 67, 54]; // Green or Red
$pdf->SetFillColor($statusColor[0], $statusColor[1], $statusColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'STATUS: ' . strtoupper($policy['status']), 0, 1, 'C', true);
$pdf->Ln(8);

// Reset colors
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 11);

// ====================
// POLICY INFORMATION SECTION
// ====================
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(255, 152, 0);
$pdf->Cell(0, 10, '  POLICY INFORMATION', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

// Two-column layout for policy details
$leftX = 15;
$rightX = 110;
$currentY = $pdf->GetY();

// Left column
$pdf->SetXY($leftX, $currentY);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(45, 6, 'Policy Number:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $policy['policy_number'], 0, 1);

$pdf->SetX($leftX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(45, 6, 'Coverage Type:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, strtoupper($policy['coverage_type'] ?: 'BASIC'), 0, 1);

$pdf->SetX($leftX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(45, 6, 'Policy Start Date:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, date('F d, Y', strtotime($policy['policy_start'])), 0, 1);

$pdf->SetX($leftX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(45, 6, 'Policy End Date:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, date('F d, Y', strtotime($policy['policy_end'])), 0, 1);

// Right column
$pdf->SetXY($rightX, $currentY);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Premium Amount:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(76, 175, 80); // Green
$pdf->Cell(0, 6, 'PHP ' . number_format($policy['premium_amount'], 2), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetX($rightX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Coverage Limit:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(33, 150, 243); // Blue
$pdf->Cell(0, 6, 'PHP ' . number_format($policy['coverage_limit'], 2), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetX($rightX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Deductible:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'PHP ' . number_format($policy['deductible'], 2), 0, 1);

$pdf->Ln(8);

// ====================
// INSURED RENTER SECTION
// ====================
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(255, 152, 0);
$pdf->Cell(0, 10, '  INSURED RENTER', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);

$renterDetails = [
    'Full Name' => $policy['renter_name'] ?: 'N/A',
    'Email Address' => $policy['renter_email'] ?: 'N/A',
    'Contact Number' => $policy['renter_phone'] ?: 'N/A',
];

foreach ($renterDetails as $label => $value) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(55, 7, $label . ':', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, $value, 0, 1);
}

$pdf->Ln(6);

// ====================
// COVERAGE DETAILS SECTION
// ====================
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(255, 152, 0);
$pdf->Cell(0, 10, '  COVERAGE DETAILS', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

// Enhanced Coverage Table
$pdf->SetFillColor(255, 152, 0); // Orange header
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(120, 8, 'Coverage Type', 1, 0, 'L', true);
$pdf->Cell(70, 8, 'Amount', 1, 1, 'R', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 10);

$coverages = [
    'Collision Coverage' => $policy['collision_coverage'] ?? 0,
    'Third-Party Liability' => $policy['liability_coverage'] ?? 0,
    'Theft Coverage' => $policy['theft_coverage'] ?? 0,
    'Personal Injury Coverage' => $policy['personal_injury_coverage'] ?? 0,
    'Roadside Assistance' => ($policy['roadside_assistance'] ? 'Included' : 'Not Included'),
];

$rowCount = 0;
foreach ($coverages as $label => $value) {
    // Alternate row colors
    $fillColor = ($rowCount % 2 == 0) ? [255, 255, 255] : [250, 250, 250];
    $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
    
    $pdf->Cell(120, 7, '  ' . $label, 1, 0, 'L', true);
    
    if (is_numeric($value)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(70, 7, 'PHP ' . number_format($value, 2) . '  ', 1, 1, 'R', true);
        $pdf->SetFont('helvetica', '', 10);
    } else {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(70, 7, $value . '  ', 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);
    }
    
    $rowCount++;
}

// Total Coverage with prominent styling
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(76, 175, 80); // Green
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(120, 10, '  TOTAL COVERAGE LIMIT', 1, 0, 'L', true);
$pdf->Cell(70, 10, 'PHP ' . number_format($policy['coverage_limit'], 2) . '  ', 1, 1, 'R', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);

// ====================
// INSURANCE PROVIDER SECTION
// ====================
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(255, 152, 0);
$pdf->Cell(0, 10, '  INSURANCE PROVIDER', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);

$providerDetails = [
    'Provider Name' => $policy['provider_name'] ?: 'Cargo Platform Insurance',
    'Email Address' => $policy['provider_email'] ?: 'insurance@cargo.ph',
    'Contact Number' => $policy['provider_phone'] ?: '+63-917-XXX-XXXX',
];

foreach ($providerDetails as $label => $value) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(55, 7, $label . ':', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, $value, 0, 1);
}

$pdf->Ln(8);

// ====================
// IMPORTANT NOTES SECTION
// ====================
$pdf->SetFillColor(255, 243, 224); // Light orange
$pdf->SetDrawColor(255, 152, 0);
$pdf->Rect(15, $pdf->GetY(), 180, 25, 'FD');

$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(230, 81, 0);
$pdf->Cell(0, 7, 'IMPORTANT NOTICE', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(0, 4, 
    '• This insurance certificate is valid only for the rental period specified above.' . "\n" .
    '• In case of accident or incident, contact the insurance provider immediately.' . "\n" .
    '• Keep this document with you during your rental period.' . "\n" .
    '• Deductible applies to all claims as specified in the policy terms.'
);

$pdf->SetTextColor(0, 0, 0);

// ====================
// FOOTER
// ====================
$pdf->SetY(-30);
$pdf->SetDrawColor(255, 152, 0);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor(255, 152, 0);
$pdf->Cell(0, 4, 'CARGO CAR RENTAL PLATFORM', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 4, 'Official Insurance Certificate - Digitally Generated', 0, 1, 'C');
$pdf->Cell(0, 4, 'Document Generated: ' . date('F d, Y \a\t h:i A'), 0, 1, 'C');

$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(0, 4, 'For inquiries: insurance@cargo.ph | support@cargo.ph | www.cargo.ph', 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetTextColor(150, 150, 150);
$pdf->Cell(0, 4, 'This is a legally binding document. Retain for your records.', 0, 1, 'C');

// Clear buffer and output
ob_end_clean();

// Output PDF
$filename = 'Policy_' . $policy['policy_number'] . '.pdf';
$pdf->Output($filename, 'D');

$conn->close();
?>
