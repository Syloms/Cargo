<?php
// ✅ CRITICAL: Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . "/../include/db.php";
require_once __DIR__ . "/security/suspension_guard.php";

$response = ["success" => false, "message" => ""];

/* =========================================================
   REQUIRED FIELDS
   ========================================================= */
$required = [
  "booking_id",
  "user_id",
  "total_amount",
  "payment_method",
  "gcash_number",
  "payment_reference"
];

foreach ($required as $field) {
  if (!isset($_POST[$field]) || $_POST[$field] === "") {
    echo json_encode([
      "success" => false,
      "message" => "Missing field: $field"
    ]);
    exit;
  }
}

/* =========================================================
   SANITIZE INPUT
   ========================================================= */
$bookingId   = intval($_POST["booking_id"]);
$userId      = intval($_POST["user_id"]);
$amount      = floatval($_POST["total_amount"]);

// Block suspended users
require_not_suspended($conn, $userId);

$method      = mysqli_real_escape_string($conn, $_POST["payment_method"]);
$gcashNo     = mysqli_real_escape_string($conn, $_POST["gcash_number"]);
$refNo       = mysqli_real_escape_string($conn, $_POST["payment_reference"]);

/* =========================================================
   VERIFY BOOKING EXISTS & UNPAID
   ========================================================= */
$check = $conn->prepare("
  SELECT id, payment_status 
  FROM bookings 
  WHERE id = ? AND user_id = ?
");

if (!$check) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$check->bind_param("ii", $bookingId, $userId);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
  echo json_encode([
    "success" => false,
    "message" => "Invalid booking"
  ]);
  exit;
}

$booking = $result->fetch_assoc();

/* Check if payment already exists in payments table to prevent duplicates */
$paymentCheck = $conn->prepare("
  SELECT id, payment_reference FROM payments WHERE booking_id = ?
");
$paymentCheck->bind_param("i", $bookingId);
$paymentCheck->execute();
$existingPayments = $paymentCheck->get_result();

if ($existingPayments->num_rows > 0) {
  // Delete any payment records without payment_reference (incomplete submissions)
  $deleteIncomplete = $conn->prepare("
    DELETE FROM payments WHERE booking_id = ? AND payment_reference IS NULL
  ");
  $deleteIncomplete->bind_param("i", $bookingId);
  $deleteIncomplete->execute();
  
  // Check again if there's a payment with valid reference
  $paymentCheck2 = $conn->prepare("
    SELECT id FROM payments WHERE booking_id = ? AND payment_reference IS NOT NULL
  ");
  $paymentCheck2->bind_param("i", $bookingId);
  $paymentCheck2->execute();
  if ($paymentCheck2->get_result()->num_rows > 0) {
    echo json_encode([
      "success" => false,
      "message" => "Payment already submitted for this booking"
    ]);
    exit;
  }
}

if ($booking["payment_status"] !== "pending") {
  echo json_encode([
    "success" => false,
    "message" => "Payment already processed"
  ]);
  exit;
}

/* =========================================================
   START TRANSACTION
   ========================================================= */
mysqli_begin_transaction($conn);

try {

  /* =========================================================
     INSERT PAYMENT
     ========================================================= */
  $paymentSql = "
    INSERT INTO payments (
      booking_id,
      user_id,
      amount,
      payment_method,
      payment_reference,
      payment_status,
      created_at
    ) VALUES (
      ?, ?, ?, ?, ?, 'pending', NOW()
    )
  ";

  $stmt = $conn->prepare($paymentSql);
  
  if (!$stmt) {
    throw new Exception("Failed to prepare payment statement: " . $conn->error);
  }
  
  $stmt->bind_param(
    "iidss",
    $bookingId,
    $userId,
    $amount,
    $method,
    $refNo
  );

  if (!$stmt->execute()) {
    throw new Exception("Failed to record payment: " . $stmt->error);
  }

  $paymentId = $stmt->insert_id;

  /* =========================================================
     UPDATE BOOKING WITH PAYMENT INFO & SECURITY DEPOSIT
     ✅ FIXED: Handle varbinary columns properly
     ========================================================= */
  $update = "
    UPDATE bookings
    SET 
      payment_id     = ?,
      payment_method = ?,
      status         = 'pending',
      payment_status = 'pending',
      gcash_number   = ?,
      gcash_reference= ?,
      payment_date   = NOW(),
      security_deposit_status = 'held',
      security_deposit_held_at = NOW()
    WHERE id = ?
  ";


  $stmt = $conn->prepare($update);
  
  if (!$stmt) {
    throw new Exception("Failed to prepare booking update: " . $conn->error);
  }
  
  // ✅ FIXED: Use 'ssssi' (all strings) instead of 'isssi'
  // payment_id is varchar(255) in bookings table
  $paymentIdStr = (string)$paymentId;
  
  $stmt->bind_param(
    "ssssi",
    $paymentIdStr,
    $method,
    $gcashNo,
    $refNo,
    $bookingId
  );

  if (!$stmt->execute()) {
    throw new Exception("Failed to update booking: " . $stmt->error);
  }

  /* =========================================================
     CREATE ADMIN NOTIFICATION
     ========================================================= */
  // Get booking and vehicle details for notification
  $detailsQuery = "SELECT b.id, b.user_id, u.fullname as renter_name,
                   CONCAT(COALESCE(c.brand, m.brand), ' ', COALESCE(c.model, m.model)) as vehicle_name,
                   b.total_amount
                   FROM bookings b
                   LEFT JOIN users u ON b.user_id = u.id
                   LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
                   LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
                   WHERE b.id = ?";
  
  $detailsStmt = $conn->prepare($detailsQuery);
  $detailsStmt->bind_param("i", $bookingId);
  $detailsStmt->execute();
  $detailsResult = $detailsStmt->get_result();
  $details = $detailsResult->fetch_assoc();
  
  if ($details) {
    $adminMessage = "New payment of ₱" . number_format($amount, 2) . " submitted by {$details['renter_name']} for {$details['vehicle_name']} (Booking #{$bookingId}). Please verify.";
    
    // Check if admin_notifications table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'admin_notifications'");
    if (mysqli_num_rows($tableCheck) > 0) {
      $notifQuery = "INSERT INTO admin_notifications 
                     (type, title, message, link, icon, priority, read_status, created_at) 
                     VALUES ('payment', 'New Payment Pending', ?, 'payment.php', 'credit-card', 'high', 'unread', NOW())";
      
      $notifStmt = $conn->prepare($notifQuery);
      if ($notifStmt) {
        $notifStmt->bind_param("s", $adminMessage);
        $notifStmt->execute();
      }
    }
  }

  /* =========================================================
     COMMIT
     ========================================================= */
  mysqli_commit($conn);

  echo json_encode([
    "success"     => true,
    "message"     => "Payment submitted successfully. Awaiting verification.",
    "payment_id"  => $paymentId
  ]);

} catch (Exception $e) {

  mysqli_rollback($conn);

  echo json_encode([
    "success" => false,
    "message" => $e->getMessage()
  ]);
}

$conn->close();
?>