<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../include/db.php";
require_once "../include/smtp_mailer.php";
require_once __DIR__ . "/security/suspension_guard.php";

// ---------------------------
// ALLOW POST ONLY
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}


// ---------------------------
// OPTIONAL IMAGE UPLOAD
// ---------------------------
$imagePath = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $fileType = mime_content_type($_FILES['image']['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Only JPG and PNG images are allowed"
        ]);
        exit;
    }

    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Image must be under 5MB"
        ]);
        exit;
    }

    $uploadDir = __DIR__ . "/../uploads/reports/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid("report_") . "." . strtolower($ext);

    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to upload image"
        ]);
        exit;
    }

    // Store relative path for DB
    $imagePath = "uploads/reports/" . $fileName;
}


// ---------------------------
// GET INPUTS
// ---------------------------
$reporterId = intval($_POST['reporter_id'] ?? 0);

// Block suspended users
require_not_suspended($conn, $reporterId);

$reportType = strtolower(trim($_POST['report_type'] ?? ""));
$reportedId = trim($_POST['reported_id'] ?? ""); // Keep as string for flexibility
$reason     = trim($_POST['reason'] ?? "");
$details    = trim($_POST['details'] ?? "");

// ---------------------------
// VALIDATION
// ---------------------------
$errors = [];

if ($reporterId <= 0) {
    $errors[] = "Invalid reporter ID";
}

if (empty($reportType)) {
    $errors[] = "Report type is required";
}

if (empty($reportedId)) {
    $errors[] = "Reported item ID is required";
}

if (empty($reason)) {
    $errors[] = "Reason is required";
}

if (empty($details)) {
    $errors[] = "Details are required";
} elseif (strlen($details) < 20) {
    $errors[] = "Details must be at least 20 characters";
} elseif (strlen($details) > 500) {
    $errors[] = "Details must not exceed 500 characters";
}

// Validate report type
$allowedTypes = ['car', 'motorcycle', 'user', 'booking', 'chat'];
if (!in_array($reportType, $allowedTypes)) {
    $errors[] = "Invalid report type";
}

// Validate reason based on type
$validReasons = [
    'car' => ['Misleading information', 'Fake photos', 'Vehicle not as described', 'Safety concerns', 'Suspicious pricing', 'Unavailable vehicle', 'Other'],
    'motorcycle' => ['Misleading information', 'Fake photos', 'Vehicle not as described', 'Safety concerns', 'Suspicious pricing', 'Unavailable vehicle', 'Other'],
    'user' => ['Inappropriate behavior', 'Harassment', 'Fraud/Scam', 'Fake profile', 'Suspicious activity', 'Spam', 'Other'],
    'booking' => ['No-show', 'Late pickup/return', 'Vehicle damage', 'Cleanliness issues', 'Payment dispute', 'Cancellation issues', 'Other'],
    'chat' => ['Harassment', 'Spam messages', 'Inappropriate content', 'Scam attempt', 'Threatening behavior', 'Other']
];

if (isset($validReasons[$reportType]) && !in_array($reason, $validReasons[$reportType])) {
    $errors[] = "Invalid reason for this report type";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => implode(", ", $errors)
    ]);
    exit;
}

// ---------------------------
// VERIFY USER EXISTS
// ---------------------------
$checkUser = $conn->prepare("SELECT id, fullname, email FROM users WHERE id = ?");
$checkUser->bind_param("i", $reporterId);
$checkUser->execute();
$userResult = $checkUser->get_result();

if ($userResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Reporter not found"
    ]);
    exit;
}

$reporter = $userResult->fetch_assoc();
$checkUser->close();

// ---------------------------
// RATE LIMITING CHECK
// ---------------------------
function checkRateLimit($conn, $reporterId) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE reporter_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->bind_param("i", $reporterId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['count'] < 5; // Max 5 reports per hour
}

if (!checkRateLimit($conn, $reporterId)) {
    http_response_code(429);
    echo json_encode([
        "status" => "error",
        "message" => "Too many reports. You can submit up to 5 reports per hour. Please try again later."
    ]);
    exit;
}

// ---------------------------
// DUPLICATE REPORT CHECK
// ---------------------------
function isDuplicateReport($conn, $reporterId, $reportedId, $reportType) {
    $stmt = $conn->prepare("
        SELECT id, created_at 
        FROM reports 
        WHERE reporter_id = ? 
        AND reported_id = ? 
        AND report_type = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND status IN ('pending', 'under_review')

    ");
    $stmt->bind_param("iss", $reporterId, $reportedId, $reportType);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

if (isDuplicateReport($conn, $reporterId, $reportedId, $reportType)) {
    http_response_code(409);
    echo json_encode([
        "status" => "error",
        "message" => "You have already reported this item in the last 24 hours. Please wait before submitting another report."
    ]);
    exit;
}

// ---------------------------
// VERIFY REPORTED ITEM EXISTS
// ---------------------------
function verifyReportedItemExists($conn, $reportType, $reportedId) {
    $table = null;
    
    switch ($reportType) {
        case 'car':
            $table = 'cars';
            break;
        case 'motorcycle':
            $table = 'motorcycles';
            break;
        case 'user':
            $table = 'users';
            break;
        case 'booking':
            $table = 'bookings';
            break;
        case 'chat':
            // For chat, we might need different validation
            return true;
        default:
            return false;
    }
    
    if ($table === null) return false;
    
    $stmt = $conn->prepare("SELECT id FROM $table WHERE id = ?");
    $stmt->bind_param("s", $reportedId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

if (!verifyReportedItemExists($conn, $reportType, $reportedId)) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "The reported item does not exist or has been removed"
    ]);
    exit;
}

// ---------------------------
// SANITIZE INPUTS
// ---------------------------
$details = htmlspecialchars($details, ENT_QUOTES, 'UTF-8');
$reason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

// ---------------------------
// START TRANSACTION
// ---------------------------
$conn->begin_transaction();

try {
    // ---------------------------
    // INSERT REPORT
    // ---------------------------
    $stmt = $conn->prepare("
        INSERT INTO reports 
        (reporter_id, report_type, reported_id, reason, details, image_path, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ");

    $stmt->bind_param(
        "isssss",
        $reporterId,
        $reportType,
        $reportedId,
        $reason,
        $details,
        $imagePath
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to insert report: " . $stmt->error);
    }

    $reportId = $conn->insert_id;
    $stmt->close();

    // ---------------------------
    // LOG AUDIT TRAIL
    // ---------------------------
    $logStmt = $conn->prepare("
        INSERT INTO report_logs 
        (report_id, action, performed_by, notes, created_at)
        VALUES (?, 'created', ?, 'Report submitted by user', NOW())
    ");
    $logStmt->bind_param("ii", $reportId, $reporterId);
    $logStmt->execute();
    $logStmt->close();

    // ---------------------------
    // INCREMENT REPORT COUNTER FOR REPORTED ITEM (optional)
    // ---------------------------
    if ($reportType === 'user') {
        $counterStmt = $conn->prepare("
            UPDATE users 
            SET report_count = COALESCE(report_count, 0) + 1 
            WHERE id = ?
        ");
        $counterStmt->bind_param("s", $reportedId);
        $counterStmt->execute();
        $counterStmt->close();
    } elseif ($reportType === 'car') {
        $counterStmt = $conn->prepare("
            UPDATE cars 
            SET report_count = COALESCE(report_count, 0) + 1 
            WHERE id = ?
        ");
        $counterStmt->bind_param("s", $reportedId);
        $counterStmt->execute();
        $counterStmt->close();
    } elseif ($reportType === 'motorcycle') {
        $counterStmt = $conn->prepare("
            UPDATE motorcycles 
            SET report_count = COALESCE(report_count, 0) + 1 
            WHERE id = ?
        ");
        $counterStmt->bind_param("s", $reportedId);
        $counterStmt->execute();
        $counterStmt->close();
    }

    // Commit transaction
    $conn->commit();

    // ---------------------------
    // SEND EMAIL NOTIFICATION TO ADMIN (optional)
    // ---------------------------
    // Send email notification to admin via SMTP
    // ---------------------------
    try {
        $adminEmail = SMTP_FROM_EMAIL; // Send to admin email
        $subject = "🚨 New Report Submitted - #$reportId";
        
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #d32f2f; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; border: 1px solid #ddd; }
                .info-row { padding: 10px 0; border-bottom: 1px solid #eee; }
                .label { font-weight: bold; color: #555; }
                .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ New Report Submitted</h1>
                    <p>CarGO Admin Alert</p>
                </div>
                <div class='content'>
                    <h2>Report Details</h2>
                    <div class='info-row'>
                        <span class='label'>Report ID:</span> #$reportId
                    </div>
                    <div class='info-row'>
                        <span class='label'>Type:</span> $reportType
                    </div>
                    <div class='info-row'>
                        <span class='label'>Reason:</span> $reason
                    </div>
                    <div class='info-row'>
                        <span class='label'>Reporter:</span> {$reporter['fullname']} ({$reporter['email']})
                    </div>
                    <p style='margin-top: 20px;'>
                        <strong>Action Required:</strong> Please review this report within 24-48 hours.
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated notification from CarGO Admin System</p>
                    <p>&copy; " . date('Y') . " CarGO. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        send_smtp_email($adminEmail, $subject, $htmlBody);
    } catch (Exception $mailErr) {
        // Log error but don't fail the report submission
        error_log("Failed to send report notification email: " . $mailErr->getMessage());
    }

    // ---------------------------
    // SUCCESS RESPONSE
    // ---------------------------
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Report submitted successfully. Our team will review it within 24-48 hours.",
        "report_id" => $reportId,
        "data" => [
            "report_id" => $reportId,
            "status" => "pending",
            "created_at" => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to submit report. Please try again later.",
        "debug" => $e->getMessage() // Remove in production
    ]);
}

$conn->close();
?>