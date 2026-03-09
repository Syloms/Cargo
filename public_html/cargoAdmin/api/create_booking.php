<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../include/db.php";
require_once __DIR__ . "/security/suspension_guard.php";

$response = ["success" => false, "message" => ""];

/* =========================================================
   1️⃣ AUTHENTICATION (MOBILE + WEB)
========================================================= */
$userId = null;

if (!empty($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    $stmt = $conn->prepare("
        SELECT u.id,
               CASE WHEN uv.status = 'approved' THEN 1 ELSE 0 END AS is_verified
        FROM users u
        LEFT JOIN user_verifications uv ON u.id = uv.user_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        echo json_encode(["success"=>false,"message"=>"User not found"]);
        exit;
    }

    // Block suspended users
    require_not_suspended($conn, $userId);

    if ($user['is_verified'] != 1) {
        echo json_encode(["success"=>false,"message"=>"Account not verified"]);
        exit;
    }

} elseif (!empty($_SESSION['user_id'])) {
    $userId = intval($_SESSION['user_id']);
} else {
    echo json_encode(["success"=>false,"message"=>"Unauthorized"]);
    exit;
}

/* =========================================================
   2️⃣ BASIC VALIDATION
========================================================= */
if (
    empty($_POST['vehicle_type']) ||
    empty($_POST['vehicle_id']) ||
    empty($_POST['pickup_date']) ||
    empty($_POST['return_date'])
) {
    echo json_encode(["success"=>false,"message"=>"Missing required fields"]);
    exit;
}

$vehicleType = $_POST['vehicle_type'];
$vehicleId   = intval($_POST['vehicle_id']);

if (!in_array($vehicleType, ['car','motorcycle'])) {
    echo json_encode(["success"=>false,"message"=>"Invalid vehicle type"]);
    exit;
}

$table = ($vehicleType === 'motorcycle') ? 'motorcycles' : 'cars';


/* =========================================================
   3️⃣ TIME + OTHER INPUTS
========================================================= */
$pickupDate = $_POST['pickup_date'];
$returnDate = $_POST['return_date'];

$pickupTime = $_POST['pickup_time'] ?? '09:00';
$returnTime = $_POST['return_time'] ?? '18:00';

$pickupTime = date("H:i:s", strtotime($pickupTime));
$returnTime = date("H:i:s", strtotime($returnTime));

$rentalPeriod = $_POST['rental_period'] ?? 'Day';
$needsDelivery = intval($_POST['needs_delivery'] ?? 0);
$fullName = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$contact = $_POST['contact'] ?? '';

mysqli_begin_transaction($conn);

try {

    /* =========================================================
       4️⃣ FETCH VEHICLE (CAR OR MOTORCYCLE)
    ========================================================= */
    $stmt = $conn->prepare("
        SELECT price_per_day, owner_id, status
        FROM {$table}
        WHERE id = ?
    ");
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();

    if (!$vehicle) {
        throw new Exception(ucfirst($vehicleType) . " not found");
    }

    if ($vehicle['status'] !== 'approved') {
        throw new Exception(ucfirst($vehicleType) . " not available");
    }

    /* =========================================================
       5️⃣ CALCULATE TOTAL
    ========================================================= */
    $pickup = strtotime($pickupDate);
    $return = strtotime($returnDate);
    $days = max(1, ceil(($return - $pickup) / 86400));

    $baseRental = $days * $vehicle['price_per_day'];
    $serviceFee = $baseRental * 0.05; // 5% service fee
    $totalAmount = $baseRental + $serviceFee;

    // Calculate security deposit (20% of rental amount, min ₱500, max ₱10,000)
    $securityDeposit = $totalAmount * 0.20;
    if ($securityDeposit < 500) {
        $securityDeposit = 500;
    } elseif ($securityDeposit > 10000) {
        $securityDeposit = 10000;
    }
    $securityDeposit = round($securityDeposit, 2);

    /* =========================================================
       6️⃣ CREATE BOOKING
    ========================================================= */
    $stmt = $conn->prepare("
        INSERT INTO bookings
        (user_id, vehicle_type, car_id, owner_id, pickup_date, return_date, pickup_time, return_time,
         total_amount, price_per_day, rental_period, needs_delivery,
         full_name, email, contact, security_deposit_amount, status, payment_status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'awaiting_payment', 'pending', NOW())
    ");

    $stmt->bind_param(
        "isiissssddsisssd",
        $userId,
        $vehicleType,
        $vehicleId,
        $vehicle['owner_id'],
        $pickupDate,
        $returnDate,
        $pickupTime,
        $returnTime,
        $totalAmount,
        $vehicle['price_per_day'],
        $rentalPeriod,
        $needsDelivery,
        $fullName,
        $email,
        $contact,
        $securityDeposit
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to create booking");
    }

    $bookingId = $conn->insert_id;

    /* =========================================================
       7️⃣ STORE PAYMENT RECORD (Manual GCash Payment)
    ========================================================= */
    $stmt = $conn->prepare("
        INSERT INTO payments
        (booking_id, user_id, amount, payment_method, payment_status, created_at)
        VALUES (?, ?, ?, 'gcash', 'pending', NOW())
    ");
    $stmt->bind_param("iid", $bookingId, $userId, $totalAmount);
    $stmt->execute();
    $paymentId = $conn->insert_id;

    mysqli_commit($conn);

    echo json_encode([
        "success" => true,
        "message" => "Booking created successfully. Please proceed to payment.",
        "data" => [
            "booking_id"       => $bookingId,
            "payment_id"       => $paymentId,
            "base_rental"      => $baseRental,
            "service_fee"      => $serviceFee,
            "total_amount"     => $totalAmount,
            "security_deposit" => $securityDeposit,
            "grand_total"      => $totalAmount + $securityDeposit,
            "payment_method"   => "gcash"
        ]
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
}

$conn->close();
