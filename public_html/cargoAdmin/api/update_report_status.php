<?php
session_start();
header("Content-Type: application/json");
require_once "../include/db.php";

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = $_SESSION['admin_id'];
$reportId = intval($_POST['report_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($reportId <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$allowedStatuses = ['pending', 'under_review', 'resolved', 'dismissed'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$conn->begin_transaction();

try {
    // Update report
    $stmt = $conn->prepare("
        UPDATE reports 
        SET status = ?, 
            reviewed_by = ?,
            admin_notes = ?,
            reviewed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("sisi", $status, $adminId, $notes, $reportId);
    $stmt->execute();

    // Log activity
    $logStmt = $conn->prepare("
        INSERT INTO report_logs (report_id, action, performed_by, notes, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $action = "status_changed_to_" . $status;
    $logStmt->bind_param("isis", $reportId, $action, $adminId, $notes);
    $logStmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Report updated successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update report: ' . $e->getMessage()
    ]);
}

$conn->close();
?>