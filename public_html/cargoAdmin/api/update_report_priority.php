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
$priority = strtolower(trim($_POST['priority'] ?? ''));

if ($reportId <= 0 || !in_array($priority, ['low', 'medium', 'high'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$stmt = $conn->prepare("UPDATE reports SET priority = ? WHERE id = ?");
$stmt->bind_param("si", $priority, $reportId);

if ($stmt->execute()) {
    // Log activity
    $logStmt = $conn->prepare("
        INSERT INTO report_logs (report_id, action, performed_by, notes)
        VALUES (?, 'priority_changed', ?, ?)
    ");
    $note = "Priority changed to " . $priority;
    $logStmt->bind_param("iis", $reportId, $adminId, $note);
    $logStmt->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$conn->close();
?>