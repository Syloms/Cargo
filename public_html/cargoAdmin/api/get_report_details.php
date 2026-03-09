<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header("Content-Type: application/json");
require_once "../include/db.php";

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$reportId = intval($_GET['id'] ?? 0);

if ($reportId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

// Get report details
$query = "
SELECT 
    r.id,
    r.report_type,
    r.reported_id,
    r.reason,
    r.details,
    r.status,
    r.priority,
    r.image_path,
    r.created_at,
    r.updated_at,
    r.reviewed_at,
    r.reviewed_by,
    r.admin_notes,

    reporter.fullname AS reporter_name,
    reporter.email AS reporter_email,
    reporter.phone AS reporter_phone,
    admin.fullname AS reviewer_name,

    CASE 
        WHEN r.report_type = 'car' THEN 
            (SELECT CONCAT(brand, ' ', model) FROM cars WHERE id = r.reported_id)
        WHEN r.report_type = 'motorcycle' THEN 
            (SELECT CONCAT(brand, ' ', model) FROM motorcycles WHERE id = r.reported_id)
        WHEN r.report_type = 'user' THEN 
            (SELECT fullname FROM users WHERE id = r.reported_id)
        WHEN r.report_type = 'booking' THEN 
            CONCAT('Booking #', r.reported_id)
        ELSE 'Unknown'
    END AS reported_item_name

FROM reports r
LEFT JOIN users reporter ON r.reporter_id = reporter.id
LEFT JOIN admin ON r.reviewed_by = admin.id
WHERE r.id = ?
";


$stmt = $conn->prepare($query);
$stmt->bind_param("i", $reportId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$report = $result->fetch_assoc();

// Get timeline
$timelineQuery = "
SELECT 
    rl.*,
    COALESCE(u.fullname, a.fullname, 'System') AS performed_by_name
FROM report_logs rl
LEFT JOIN users u ON rl.performed_by = u.id
LEFT JOIN admin a ON rl.performed_by = a.id
WHERE rl.report_id = ?
ORDER BY rl.created_at DESC
";

$timelineStmt = $conn->prepare($timelineQuery);
$timelineStmt->bind_param("i", $reportId);
$timelineStmt->execute();
$timelineResult = $timelineStmt->get_result();

$timeline = [];
while ($row = $timelineResult->fetch_assoc()) {
    $timeline[] = [
        'action' => ucwords(str_replace('_', ' ', $row['action'])),
        'performed_by' => $row['performed_by_name'],
        'notes' => $row['notes'],
        'created_at' => date('M d, Y h:i A', strtotime($row['created_at']))
    ];
}

$report['timeline'] = $timeline;

echo json_encode([
    'success' => true,
    'report' => $report
]);

$conn->close();
?>