<?php
// Disable strict types temporarily to debug
// declare(strict_types=1);

// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Lightweight health-check to confirm the deployed file/version
// Usage: GET /api/refund/request_refund.php?ping=1
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ping'])) {
    echo json_encode([
        'success' => true,
        'service' => 'request_refund',
        // Bump this string when deploying changes
        'version' => '2026-02-11-status-alias-fix',
        'file_mtime' => @date('c', @filemtime(__FILE__)) ?: null,
    ]);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Try to load database connection
try {
    require_once '../../include/db.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

/* =========================================================
   HELPER - Check if function already exists in config.php
========================================================= */
$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');

if (!function_exists('jsonError')) {
    function jsonError(string $message, int $code = 400, array $extra = []): void {
        $debug = (isset($_GET['debug']) && $_GET['debug'] === '1');
        http_response_code($code);
        $payload = ['success' => false, 'message' => $message];
        if ($debug && !empty($extra)) {
            $payload['debug'] = $extra;
        }
        echo json_encode($payload);
        exit;
    }
}

/* =========================================================
   METHOD CHECK
========================================================= */
// IMPORTANT: Return method errors before auth checks, so browser GET requests don't look like auth failures.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Use POST method', 405, [
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'hint' => 'Call this endpoint via POST (application/x-www-form-urlencoded or JSON).'
    ]);
}

/* =========================================================
   AUTH - TOKEN OR BOOKING OWNERSHIP
========================================================= */
$token = null;
$userId = null;

// Debug: Log all possible token sources
error_log("=== REFUND REQUEST DEBUG ===");
error_log("HTTP_AUTHORIZATION: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'not set'));
error_log("REDIRECT_HTTP_AUTHORIZATION: " . ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'not set'));
error_log("POST token: " . ($_POST['token'] ?? 'not set'));
error_log("POST booking_id: " . ($_POST['booking_id'] ?? 'not set'));

if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
} elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
} elseif (!empty($_POST['token'])) {
    $token = $_POST['token'];
}

error_log("Final token: " . ($token ?? 'NULL'));

// Try token authentication first
if ($token) {
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE api_token = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $userId = (int)$user['id'];
                error_log("Authenticated via token - user ID: " . $userId);
            } else {
                error_log("Token not found in database: " . $token);
            }
        }
    } catch (Exception $e) {
        error_log("Token auth error: " . $e->getMessage());
    }
}

// FALLBACK: If no token, try to authenticate via booking ownership
// This allows users who haven't logged in recently to still request refunds
// Use REQUEST so it works for both POST form-data and controlled GET debugging.
$incomingBookingId = $_REQUEST['booking_id'] ?? null;
if (!$userId && !empty($incomingBookingId)) {
    $tempBookingId = (int)$incomingBookingId;
    error_log("No valid token, checking booking ownership for booking: " . $tempBookingId);
    
    try {
        $stmt = $conn->prepare("SELECT user_id FROM bookings WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $tempBookingId);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res->num_rows > 0) {
                $booking = $res->fetch_assoc();
                $userId = (int)$booking['user_id'];
                error_log("Authenticated via booking ownership - user ID: " . $userId);
            }
        }
    } catch (Exception $e) {
        error_log("Booking ownership check error: " . $e->getMessage());
    }
}

// If still no user, reject
if (!$userId || $userId <= 0) {
    error_log("Authentication failed - no valid token or booking ownership");
    jsonError('Authentication required. Please log out and log back in to refresh your session.', 401);
}

/* =========================================================
   INPUT
========================================================= */
$bookingId               = (int)($_POST['booking_id'] ?? 0);
$refundMethod           = trim($_POST['refund_method'] ?? '');
$accountNumber         = trim($_POST['account_number'] ?? '');
$accountName           = trim($_POST['account_name'] ?? '');
$bankName              = trim($_POST['bank_name'] ?? '');
$refundReason          = trim($_POST['refund_reason'] ?? '');
$reasonDetails         = trim($_POST['reason_details'] ?? '');
$originalPayMethod    = trim($_POST['original_payment_method'] ?? '');
$originalPayReference = trim($_POST['original_payment_reference'] ?? '');

// Debug input
error_log("Input - booking_id: " . $bookingId);
error_log("Input - refund_method: " . $refundMethod);
error_log("Input - account_number: " . $accountNumber);

/* =========================================================
   VALIDATION
========================================================= */
if ($bookingId <= 0) jsonError('Invalid booking ID');
if (!in_array($refundMethod, ['gcash', 'bank', 'original_method'], true)) {
    jsonError('Invalid refund method');
}
if ($accountNumber === '') jsonError('Account number is required');
if ($accountName === '') jsonError('Account name is required');
if ($refundMethod === 'gcash' && !preg_match('/^09\d{9}$/', $accountNumber)) {
    jsonError('Invalid GCash number');
}
if ($refundReason === '') jsonError('Refund reason is required');

/* =========================================================
   GET BOOKING + LATEST PAYMENT
========================================================= */
error_log("Fetching booking ID: " . $bookingId);

$sql = "
SELECT 
    b.*, 
    b.payment_status AS booking_payment_status,
    p.id     AS payment_id,
    p.amount AS payment_amount,
    p.payment_status AS payment_verification_status
FROM bookings b
LEFT JOIN payments p 
  ON p.id = (
    SELECT id 
    FROM payments 
    WHERE booking_id = b.id 
    ORDER BY created_at DESC 
    LIMIT 1
  )
WHERE b.id = ?
LIMIT 1
";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        error_log("Booking not found: " . $bookingId);
        jsonError('Booking not found', 404);
    }

    $booking = $res->fetch_assoc();
    error_log(
        "Booking found - ID: " . $booking['id'] .
        ", Status: " . $booking['status'] .
        ", Payment ID: " . ($booking['payment_id'] ?? 'NULL') .
        ", Booking payment_status: " . ($booking['booking_payment_status'] ?? 'NULL') .
        ", Payment payment_status: " . ($booking['payment_verification_status'] ?? 'NULL')
    );
    
} catch (Exception $e) {
    error_log("Booking query error: " . $e->getMessage());
    jsonError('Database query failed: ' . $e->getMessage(), 500);
}

/* =========================================================
   VALIDATE PAYMENT EXISTS
========================================================= */
if (empty($booking['payment_id'])) {
    error_log("No payment_id for booking: " . $bookingId);
    jsonError('No payment found for this booking', 400, [
        'booking_id' => $bookingId,
        'payment_id' => $booking['payment_id'] ?? null,
        'booking_payment_status' => $booking['booking_payment_status'] ?? null,
        'payment_verification_status' => $booking['payment_verification_status'] ?? null,
    ]);
}

/* =========================================================
   SECURITY CHECK
========================================================= */
if ((int)$booking['user_id'] !== $userId) {
    jsonError('Not your booking', 403);
}

/* =========================================================
   REFUND ELIGIBILITY
========================================================= */
if ($booking['refund_status'] === 'completed') {
    jsonError('This booking has already been refunded');
}

// Check if there's an existing refund request
$checkRefund = $conn->prepare("SELECT id, status FROM refunds WHERE booking_id = ? LIMIT 1");
$checkRefund->bind_param("i", $bookingId);
$checkRefund->execute();
$existingRefund = $checkRefund->get_result()->fetch_assoc();

// If there's an existing refund that's not rejected, block the request
if ($existingRefund) {
    if ($existingRefund['status'] === 'rejected') {
        // Delete the rejected refund to allow a new request
        $deleteRefund = $conn->prepare("DELETE FROM refunds WHERE id = ?");
        $deleteRefund->bind_param("i", $existingRefund['id']);
        $deleteRefund->execute();
    } else if (in_array($existingRefund['status'], ['pending', 'approved', 'processing'])) {
        jsonError('Refund already requested and is being processed');
    }
}

if (!in_array($booking['status'], ['cancelled', 'rejected'], true)) {
    jsonError('Only cancelled or rejected bookings can be refunded', 400, [
        'booking_id' => $bookingId,
        'booking_status' => $booking['status'] ?? null,
    ]);
}
// IMPORTANT: bookings.payment_status (unpaid/paid/...) is different from payments.payment_status (pending/verified/...)
// For refunds, we require a VERIFIED payment record (or already marked PAID).
$payStatus = $booking['payment_verification_status'] ?? '';
if ($payStatus === '') {
    jsonError('Payment not found or payment status missing', 400, [
        'booking_id' => $bookingId,
        'payment_id' => $booking['payment_id'] ?? null,
        'payment_verification_status' => $booking['payment_verification_status'] ?? null,
        'booking_payment_status' => $booking['booking_payment_status'] ?? null,
    ]);
}
if (!in_array($payStatus, ['verified', 'paid'], true)) {
    jsonError('Payment not verified', 400, [
        'booking_id' => $bookingId,
        'payment_id' => $booking['payment_id'] ?? null,
        'payment_verification_status' => $booking['payment_verification_status'] ?? null,
        'booking_payment_status' => $booking['booking_payment_status'] ?? null,
    ]);
}

/* =========================================================
   TRANSACTION
========================================================= */
error_log("Starting transaction for refund");

$conn->begin_transaction();

try {
    // Generate Refund ID
    $refundId = 'REF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    error_log("Generated refund ID: " . $refundId);

    $stmt = $conn->prepare("SELECT id FROM refunds WHERE refund_id = ?");
    $stmt->bind_param("s", $refundId);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $refundId .= rand(10, 99);
        error_log("Duplicate refund ID, regenerated: " . $refundId);
    }

    // Normalize nullable values and validate
    $ownerId        = !empty($booking['owner_id']) ? (int)$booking['owner_id'] : null;
    $paymentId      = (int)$booking['payment_id'];
    $userId         = (int)$booking['user_id'];
    $refundAmount  = (float)$booking['payment_amount'];
    $originalAmt   = !empty($booking['payment_amount']) ? (float)$booking['payment_amount'] : null;
    $bankName      = $bankName ?: null;
    $reasonDetails = $reasonDetails ?: null;
    $origMethod   = $originalPayMethod ?: null;
    $origRef      = $originalPayReference ?: null;
    
    error_log("Refund params - Owner: " . ($ownerId ?? 'NULL') . ", Payment: " . $paymentId . ", User: " . $userId . ", Amount: " . $refundAmount);

    /* =====================================================
       INSERT REFUND
    ===================================================== */
    error_log("Preparing INSERT refund statement");
    
    $insert = "
    INSERT INTO refunds (
        refund_id,
        booking_id,
        payment_id,
        user_id,
        owner_id,
        refund_amount,
        original_amount,
        deduction_amount,
        refund_method,
        account_number,
        account_name,
        bank_name,
        refund_reason,
        reason_details,
        status,
        original_payment_method,
        original_payment_reference,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
    ";

    $stmt = $conn->prepare($insert);
    if (!$stmt) {
        throw new Exception("Failed to prepare INSERT: " . $conn->error);
    }
    
    error_log("Binding parameters for INSERT");
    $bindResult = $stmt->bind_param(
        "siiiiddssssssss",
        $refundId,
        $bookingId,
        $paymentId,
        $userId,
        $ownerId,
        $refundAmount,
        $originalAmt,
        $refundMethod,
        $accountNumber,
        $accountName,
        $bankName,
        $refundReason,
        $reasonDetails,
        $origMethod,
        $origRef
    );
    
    if (!$bindResult) {
        throw new Exception("Failed to bind parameters: " . $stmt->error);
    }
    
    error_log("Executing INSERT statement");
    $execResult = $stmt->execute();
    
    if (!$execResult) {
        throw new Exception("Failed to execute INSERT: " . $stmt->error);
    }
    
    error_log("Refund record inserted successfully, ID: " . $conn->insert_id);

    /* =====================================================
       UPDATE BOOKING
    ===================================================== */
    error_log("Updating booking status");
    
    $update = "
    UPDATE bookings
    SET refund_requested = 1,
        refund_status = 'requested',
        refund_amount = ?
    WHERE id = ?
    ";

    $stmt = $conn->prepare($update);
    if (!$stmt) {
        throw new Exception("Failed to prepare UPDATE: " . $conn->error);
    }
    
    $stmt->bind_param("di", $refundAmount, $bookingId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute UPDATE: " . $stmt->error);
    }
    
    error_log("Booking updated, committing transaction");
    $conn->commit();
    error_log("Transaction committed successfully");

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("!!! REFUND SQL ERROR !!!");
    error_log("Error message: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
    jsonError("Database error: " . $e->getMessage(), 500);
} catch (Exception $e) {
    $conn->rollback();
    error_log("!!! REFUND GENERAL ERROR !!!");
    error_log("Error message: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    jsonError("Server error: " . $e->getMessage(), 500);
} catch (Throwable $e) {
    $conn->rollback();
    error_log("!!! REFUND THROWABLE ERROR !!!");
    error_log("Error message: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    jsonError("Fatal error: " . $e->getMessage(), 500);
}

/* =========================================================
   SUCCESS
========================================================= */
echo json_encode([
    'success'   => true,
    'refund_id'=> $refundId,
    'amount'   => $refundAmount,
    'status'   => 'pending',
    'message'  => 'Refund request submitted successfully'
]);

$conn->close();
