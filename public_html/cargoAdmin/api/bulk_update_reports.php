<?php
session_start();
header("Content-Type: application/json");
require_once "../include/db.php";

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = $_SESSION['admin_id'];
$input = json_decode(file_get_contents('php://input'), true);
$reportIds = $input['report_ids'] ?? [];
$status = $input['status'] ?? '';

if (empty($reportIds) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$updated = 0;
$conn->begin_transaction();

try {
    $placeholders = str_repeat('?,', count($reportIds) - 1) . '?';
    $stmt = $conn->prepare("
        UPDATE reports 
        SET status = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
        WHERE id IN ($placeholders)
    ");
    
    $types = 'si' . str_repeat('i', count($reportIds));
    $params = array_merge([$status, $adminId], $reportIds);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $updated = $stmt->affected_rows;

    // Log each update
    foreach ($reportIds as $reportId) {
        $logStmt = $conn->prepare("
            INSERT INTO report_logs (report_id, action, performed_by, notes)
            VALUES (?, ?, ?, 'Bulk status update')
        ");
        $action = "status_changed_to_" . $status;
        $logStmt->bind_param("isi", $reportId, $action, $adminId);
        $logStmt->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'updated' => $updated
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>