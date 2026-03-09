<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

session_start();
require_once __DIR__ . "/../../include/db.php";

// Admin-only endpoint
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized. Admin login required."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$reportId       = intval($_POST['report_id'] ?? 0);
$action         = trim($_POST['action'] ?? '');        // 'approve' or 'reject'
$adminNotes     = trim($_POST['admin_notes'] ?? '');
$approvedAmount = floatval($_POST['approved_amount'] ?? 0);
$adminId        = intval($_SESSION['admin_id']);

if ($reportId <= 0 || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing report_id or invalid action"]);
    exit;
}

if ($action === 'approve' && $approvedAmount <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Approved amount must be greater than 0"]);
    exit;
}

// Fetch the report + booking deposit info
$stmt = $conn->prepare("
    SELECT dr.*,
           b.security_deposit_amount,
           b.security_deposit_deductions,
           b.security_deposit_status
    FROM damage_reports dr
    JOIN bookings b ON dr.booking_id = b.id
    WHERE dr.id = ? AND dr.status = 'pending'
");
$stmt->bind_param("i", $reportId);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Report not found or already reviewed"]);
    exit;
}

$bookingId = intval($report['booking_id']);
$ownerId   = intval($report['owner_id']);
$renterId  = intval($report['renter_id']);

$conn->begin_transaction();

try {
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';

    if ($action === 'approve') {
        // Validate deposit availability
        $deposit          = floatval($report['security_deposit_amount']);
        $currentDeductions = floatval($report['security_deposit_deductions']);

        if ($deposit <= 0) {
            throw new Exception("No security deposit on record for this booking");
        }
        if (in_array($report['security_deposit_status'], ['refunded', 'forfeited'])) {
            throw new Exception("Security deposit has already been processed");
        }
        $remaining = $deposit - $currentDeductions;
        if ($approvedAmount > $remaining) {
            throw new Exception(
                "Approved amount ₱" . number_format($approvedAmount, 2) .
                " exceeds available deposit (₱" . number_format($remaining, 2) . " remaining)"
            );
        }

        // Update damage report — approved with amount
        $stmt = $conn->prepare("
            UPDATE damage_reports
            SET status = 'approved',
                approved_amount = ?,
                admin_notes = ?,
                reviewed_by = ?,
                reviewed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("dsii", $approvedAmount, $adminNotes, $adminId, $reportId);
        if (!$stmt->execute()) throw new Exception("Failed to update damage report");
        $stmt->close();

        // Create security deposit deduction record
        $deductionDesc = "Damage report #$reportId: " . mb_substr($report['description'], 0, 100);
        $evidenceImage = $report['image_1'];
        $stmt = $conn->prepare("
            INSERT INTO security_deposit_deductions
            (booking_id, deduction_type, amount, description, evidence_image, created_by, created_at)
            VALUES (?, 'damage', ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("idssi", $bookingId, $approvedAmount, $deductionDesc, $evidenceImage, $adminId);
        if (!$stmt->execute()) throw new Exception("Failed to create deduction record");
        $stmt->close();

        // Update booking total deductions
        $stmt = $conn->prepare("
            UPDATE bookings
            SET security_deposit_deductions = security_deposit_deductions + ?
            WHERE id = ?
        ");
        $stmt->bind_param("di", $approvedAmount, $bookingId);
        if (!$stmt->execute()) throw new Exception("Failed to update booking deductions");
        $stmt->close();

        // Notify owner — their report was approved
        $ownerMsg = "Your damage report #$reportId has been approved. ₱" .
            number_format($approvedAmount, 2) .
            " will be deducted from the renter's security deposit." .
            ($adminNotes ? " Admin notes: $adminNotes" : "");
        notifyUser($conn, $ownerId, 'Damage Report Approved', $ownerMsg, 'damage_report');

        // Notify renter — deduction applied
        $renterMsg = "A damage report was filed for your booking #BK-" .
            str_pad($bookingId, 4, '0', STR_PAD_LEFT) .
            ". After admin review, ₱" . number_format($approvedAmount, 2) .
            " has been deducted from your security deposit." .
            ($adminNotes ? " Admin notes: $adminNotes" : "");
        notifyUser($conn, $renterId, 'Security Deposit Deducted', $renterMsg, 'damage_report');

    } else {
        // Reject — no deduction
        $stmt = $conn->prepare("
            UPDATE damage_reports
            SET status = 'rejected',
                admin_notes = ?,
                reviewed_by = ?,
                reviewed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $adminNotes, $adminId, $reportId);
        if (!$stmt->execute()) throw new Exception("Failed to update damage report");
        $stmt->close();

        // Notify owner — report rejected
        $ownerMsg = "Your damage report #$reportId has been reviewed and rejected by admin." .
            ($adminNotes ? " Reason: $adminNotes" : "");
        notifyUser($conn, $ownerId, 'Damage Report Rejected', $ownerMsg, 'damage_report');

        // Notify renter — no action taken
        $renterMsg = "The damage report filed for your booking #BK-" .
            str_pad($bookingId, 4, '0', STR_PAD_LEFT) .
            " has been reviewed. Admin found insufficient evidence — no deduction will be made." .
            ($adminNotes ? " Notes: $adminNotes" : "");
        notifyUser($conn, $renterId, 'Damage Report Dismissed', $renterMsg, 'damage_report');
    }

    $conn->commit();

    echo json_encode([
        "success"         => true,
        "message"         => "Damage report " . ($action === 'approve' ? "approved" : "rejected") . " successfully",
        "report_id"       => $reportId,
        "action"          => $action,
        "approved_amount" => $action === 'approve' ? $approvedAmount : 0,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();

function notifyUser($conn, $userId, $title, $message, $type = null) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $userId, $title, $message);
    $stmt->execute();
    $stmt->close();
}
?>
