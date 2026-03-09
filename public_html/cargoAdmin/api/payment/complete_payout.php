<?php
/**
 * =====================================================
 * COMPLETE PAYOUT HANDLER (FIXED VERSION)
 * Handles manual GCash payouts to car owners
 * FIXED: Prevents PHP warnings from breaking JSON output
 * =====================================================
 */

// IMPORTANT: Start output buffering to catch any warnings/errors
ob_start();

session_start();

// Clear any output that might have been generated
ob_clean();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../../include/db.php";
require_once __DIR__ . "/transaction_logger.php";

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Admin login required."
    ]);
    exit;
}

$adminId = intval($_SESSION['admin_id']);

// Validate inputs
$bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : null;
$payoutReference = isset($_POST['reference']) ? trim($_POST['reference']) : null;
$gcashNumber = isset($_POST['gcash_number']) ? trim($_POST['gcash_number']) : null;

if (!$bookingId || !$payoutReference) {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields: booking_id and reference are required"
    ]);
    exit;
}

// Validate reference format (optional but recommended)
if (strlen($payoutReference) < 8) {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "message" => "Invalid reference number. Must be at least 8 characters."
    ]);
    exit;
}

// Handle file upload
$proofPath = null;
if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../uploads/payout_proofs/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $fileType = $_FILES['proof']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        ob_end_clean();
        echo json_encode([
            "success" => false,
            "message" => "Invalid file type. Only JPG and PNG images are allowed."
        ]);
        exit;
    }
    
    if ($_FILES['proof']['size'] > 5 * 1024 * 1024) { // 5MB limit
        ob_end_clean();
        echo json_encode([
            "success" => false,
            "message" => "File too large. Maximum size is 5MB."
        ]);
        exit;
    }
    
    $extension = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
    $filename = 'payout_' . $bookingId . '_' . time() . '.' . $extension;
    $fullPath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['proof']['tmp_name'], $fullPath)) {
        $proofPath = 'uploads/payout_proofs/' . $filename;
    } else {
        ob_end_clean();
        echo json_encode([
            "success" => false,
            "message" => "Failed to upload proof of transfer"
        ]);
        exit;
    }
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    $logger = new TransactionLogger($conn);
    
    // Step 1: Get booking and verify state
    $stmt = $conn->prepare("
        SELECT 
            b.id,
            b.owner_id,
            b.owner_payout,
            b.platform_fee,
            b.total_amount,
            b.escrow_status,
            b.payout_status,
            b.status AS booking_status,
            u.fullname AS owner_name,
            u.gcash_number AS owner_gcash,
            e.id AS escrow_id
        FROM bookings b
        INNER JOIN users u ON b.owner_id = u.id
        LEFT JOIN escrow e ON b.id = e.booking_id AND e.status = 'released'
        WHERE b.id = ?
        FOR UPDATE
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Booking not found");
    }
    
    $booking = $result->fetch_assoc();
    
    // Step 2: Validate booking state
    if ($booking['payout_status'] === 'completed') {
        throw new Exception("Payout already completed for this booking");
    }
    
    if ($booking['escrow_status'] !== 'released_to_owner') {
        throw new Exception("Escrow must be released before completing payout. Current status: " . ($booking['escrow_status'] ?? 'NULL'));
    }
    
    if ($booking['booking_status'] !== 'completed') {
        throw new Exception("Booking must be completed before payout. Current status: " . $booking['booking_status']);
    }
    
    // Step 3: Check if payout record exists, create if not
    $stmt = $conn->prepare("SELECT id FROM payouts WHERE booking_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $payoutResult = $stmt->get_result();
    
    if ($payoutResult->num_rows === 0) {
        // Create payout record
        $stmt = $conn->prepare("
            INSERT INTO payouts (
                booking_id,
                owner_id,
                escrow_id,
                amount,
                platform_fee,
                net_amount,
                status,
                scheduled_at,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'processing', NOW(), NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param(
            "iiiddd",
            $bookingId,
            $booking['owner_id'],
            $booking['escrow_id'],
            $booking['total_amount'],
            $booking['platform_fee'],
            $booking['owner_payout']
        );
        $stmt->execute();
        $payoutId = $conn->insert_id;
    } else {
        $payoutId = $payoutResult->fetch_assoc()['id'];
    }
    
    // Step 4: Complete the payout
    $stmt = $conn->prepare("
        UPDATE payouts SET
            status = 'completed',
            completion_reference = ?,
            payout_account = ?,
            transfer_proof = ?,
            processed_at = NOW(),
            processed_by = ?
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $gcashToUse = $gcashNumber ?: $booking['owner_gcash'];
    
    $stmt->bind_param(
        "sssii",
        $payoutReference,
        $gcashToUse,
        $proofPath,
        $adminId,
        $payoutId
    );
    $stmt->execute();
    
    // Step 5: Update booking payout status
    $stmt = $conn->prepare("
        UPDATE bookings SET
            escrow_status = 'released_to_owner',
            payout_status = 'completed',
            payout_completed_at = NOW(),
            payout_reference = ?
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("si", $payoutReference, $bookingId);
    $stmt->execute();
    
    // Step 6: Update escrow to fully released
    if ($booking['escrow_id']) {
        $stmt = $conn->prepare("
            UPDATE escrow SET
                status = 'released',
                released_at = NOW(),
                release_reason = 'Payout completed to owner',
                processed_by = ?
            WHERE id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $adminId, $booking['escrow_id']);
        $stmt->execute();
    }
    
    // Step 7: Log the transaction
    $logger->log(
        $bookingId,
        'payout',
        $booking['owner_payout'],
        "Payout completed to {$booking['owner_name']}. GCash ref: {$payoutReference}",
        $adminId,
        [
            'payout_id' => $payoutId,
            'transfer_reference' => $payoutReference,
            'gcash_number' => $gcashToUse,
            'proof_path' => $proofPath
        ]
    );
    
    // Step 8: Notify the owner
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, created_at)
        VALUES (?, 'Payout Completed 💸', ?, NOW())
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $message = sprintf(
        'Your payout of ₱%s for booking #BK-%04d has been transferred to your GCash account. Reference: %s',
        number_format($booking['owner_payout'], 2),
        $bookingId,
        $payoutReference
    );
    
    $stmt->bind_param("is", $booking['owner_id'], $message);
    $stmt->execute();
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Clear output buffer and send success response
    ob_end_clean();
    echo json_encode([
        "success" => true,
        "message" => sprintf(
            "Payout completed successfully! ₱%s transferred to %s",
            number_format($booking['owner_payout'], 2),
            $booking['owner_name']
        ),
        "data" => [
            "payout_id" => $payoutId,
            "amount" => $booking['owner_payout'],
            "reference" => $payoutReference,
            "owner" => $booking['owner_name'],
            "gcash_number" => $gcashToUse
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    // Log error
    error_log("Payout Error (Booking #{$bookingId}): " . $e->getMessage());
    
    // Clear output buffer and send error response
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();