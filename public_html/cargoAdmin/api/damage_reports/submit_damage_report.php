<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../../include/db.php";
require_once __DIR__ . "/../../include/notification_helper.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// -------------------------------------------------------
// INPUTS
// -------------------------------------------------------
$bookingId    = intval($_POST['booking_id'] ?? 0);
$ownerId      = intval($_POST['owner_id'] ?? 0);
$damageTypes  = trim($_POST['damage_types'] ?? '');   // JSON array string
$description  = trim($_POST['description'] ?? '');
$estimatedCost = floatval($_POST['estimated_cost'] ?? 0);

// Basic validation
if ($bookingId <= 0 || $ownerId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing booking_id or owner_id"]);
    exit;
}

if (empty($description) || strlen($description) < 10) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please provide a description (min 10 characters)"]);
    exit;
}

if ($estimatedCost <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Estimated cost must be greater than 0"]);
    exit;
}

// Validate damage_types JSON
$decodedTypes = json_decode($damageTypes, true);
if (!is_array($decodedTypes) || empty($decodedTypes)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please select at least one damage type"]);
    exit;
}

// -------------------------------------------------------
// VERIFY BOOKING BELONGS TO THIS OWNER AND IS VALID
// -------------------------------------------------------
$stmt = $conn->prepare("
    SELECT id, user_id, status, security_deposit_amount
    FROM bookings
    WHERE id = ? AND owner_id = ?
");
$stmt->bind_param("ii", $bookingId, $ownerId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Booking not found or unauthorized"]);
    exit;
}

$allowedStatuses = ['approved', 'active', 'completed'];
if (!in_array($booking['status'], $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Damage reports can only be submitted for active or completed bookings"]);
    exit;
}

$renterId = intval($booking['user_id']);

// -------------------------------------------------------
// CHECK FOR DUPLICATE REPORT (one per booking)
// -------------------------------------------------------
$dupCheck = $conn->prepare("
    SELECT id FROM damage_reports
    WHERE booking_id = ? AND status NOT IN ('rejected')
");
$dupCheck->bind_param("i", $bookingId);
$dupCheck->execute();
$dupCheck->store_result();
if ($dupCheck->num_rows > 0) {
    $dupCheck->close();
    http_response_code(409);
    echo json_encode(["success" => false, "message" => "A damage report for this booking already exists"]);
    exit;
}
$dupCheck->close();

// -------------------------------------------------------
// UPLOAD IMAGES (up to 4)
// -------------------------------------------------------
$uploadDir = __DIR__ . "/../../uploads/damage_reports/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$imagePaths = [null, null, null, null];
$allowedMime = ['image/jpeg', 'image/jpg', 'image/png'];

for ($i = 1; $i <= 4; $i++) {
    $fileKey = "image_$i";
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        continue;
    }

    $file = $_FILES[$fileKey];
    $mime = mime_content_type($file['tmp_name']);

    if (!in_array($mime, $allowedMime)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Only JPG/PNG images allowed"]);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Each image must be under 5MB"]);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = "damage_{$bookingId}_" . uniqid() . ".$ext";
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to upload image $i"]);
        exit;
    }

    $imagePaths[$i - 1] = "uploads/damage_reports/$fileName";
}

// -------------------------------------------------------
// INSERT DAMAGE REPORT
// -------------------------------------------------------
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO damage_reports
        (booking_id, owner_id, renter_id, damage_types, description, estimated_cost,
         image_1, image_2, image_3, image_4, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    if (!$stmt) {
        throw new Exception("Failed to prepare insert: " . $conn->error);
    }
    $stmt->bind_param(
        "iiissdssss",
        $bookingId, $ownerId, $renterId,
        $damageTypes, $description, $estimatedCost,
        $imagePaths[0], $imagePaths[1], $imagePaths[2], $imagePaths[3]
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to save damage report: " . $stmt->error);
    }

    $reportId = $conn->insert_id;
    $stmt->close();

    // Notify admin via admin_notifications
    $adminMsg = "New damage report #$reportId submitted for booking #$bookingId. Estimated cost: ₱" . number_format($estimatedCost, 2);
    $notifStmt = $conn->prepare("
        INSERT INTO admin_notifications (type, title, message, link, icon, priority, read_status, created_at)
        VALUES ('damage_report', 'Damage Report Submitted', ?, ?, 'car_crash', 'high', 'unread', NOW())
    ");
    if ($notifStmt) {
        $link = "damage_reports.php?id=$reportId";
        $notifStmt->bind_param("ss", $adminMsg, $link);
        $notifStmt->execute();
        $notifStmt->close();
    }

    // Notify the renter
    $renterStmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, created_at)
        VALUES (?, 'Damage Report Filed',
                'The vehicle owner has filed a damage report for your rental. Admin will review and contact you.',
                NOW())
    ");
    if ($renterStmt) {
        $renterStmt->bind_param("i", $renterId);
        $renterStmt->execute();
        $renterStmt->close();
    }

    $conn->commit();

    echo json_encode([
        "success"   => true,
        "message"   => "Damage report submitted successfully. Our admin team will review it shortly.",
        "report_id" => $reportId
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();
?>
