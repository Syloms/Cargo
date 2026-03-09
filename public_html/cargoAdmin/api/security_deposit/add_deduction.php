<?php
/**
 * Add Security Deposit Deduction API
 * Admin endpoint to add deductions to security deposit
 */
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../../include/db.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Admin login required.'
    ]);
    exit;
}

$adminId = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Validate inputs
$required = ['booking_id', 'deduction_type', 'amount', 'description'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode([
            'success' => false,
            'message' => "Field '$field' is required"
        ]);
        exit;
    }
}

$bookingId = intval($_POST['booking_id']);
$deductionType = $_POST['deduction_type'];
$amount = floatval($_POST['amount']);
$description = trim($_POST['description']);
$evidenceImage = $_POST['evidence_image'] ?? null;

// Validate deduction type
$validTypes = ['damage', 'violation', 'late_fee', 'cleaning', 'fuel', 'other'];
if (!in_array($deductionType, $validTypes)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid deduction type'
    ]);
    exit;
}

if ($amount <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Deduction amount must be greater than 0'
    ]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Check if booking exists and has security deposit
    $stmt = $conn->prepare("
        SELECT 
            id,
            security_deposit_amount,
            security_deposit_deductions,
            security_deposit_status
        FROM bookings
        WHERE id = ?
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Booking not found');
    }
    
    $booking = $result->fetch_assoc();
    
    if ($booking['security_deposit_amount'] <= 0) {
        throw new Exception('No security deposit for this booking');
    }
    
    if ($booking['security_deposit_status'] === 'refunded' || 
        $booking['security_deposit_status'] === 'forfeited') {
        throw new Exception('Cannot add deduction - deposit already processed');
    }
    
    // Check if total deductions would exceed deposit
    $newTotalDeductions = $booking['security_deposit_deductions'] + $amount;
    if ($newTotalDeductions > $booking['security_deposit_amount']) {
        throw new Exception('Total deductions cannot exceed security deposit amount');
    }
    
    // Insert deduction record
    $stmt = $conn->prepare("
        INSERT INTO security_deposit_deductions 
        (booking_id, deduction_type, amount, description, evidence_image, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "isdssi",
        $bookingId,
        $deductionType,
        $amount,
        $description,
        $evidenceImage,
        $adminId
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add deduction record');
    }
    
    // Update booking's total deductions
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET security_deposit_deductions = security_deposit_deductions + ?
        WHERE id = ?
    ");
    $stmt->bind_param("di", $amount, $bookingId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update booking deductions');
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Deduction added successfully',
        'data' => [
            'booking_id' => $bookingId,
            'deduction_type' => $deductionType,
            'amount' => $amount,
            'total_deductions' => $newTotalDeductions,
            'remaining_deposit' => $booking['security_deposit_amount'] - $newTotalDeductions
        ]
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
