<?php
/**
 * Get provider details for editing
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../include/db.php';

$providerId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$providerId) {
    echo json_encode(['success' => false, 'message' => 'Provider ID is required']);
    exit;
}

try {
    $query = "
        SELECT 
            ip.*,
            (SELECT COUNT(*) FROM insurance_policies WHERE provider_id = ip.id) as total_policies,
            (SELECT COUNT(*) FROM insurance_policies WHERE provider_id = ip.id AND status = 'active') as active_policies,
            (SELECT SUM(premium_amount) FROM insurance_policies WHERE provider_id = ip.id) as total_premiums
        FROM insurance_providers ip
        WHERE ip.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $providerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    
    $provider = [
        'id' => $row['id'],
        'provider_name' => $row['provider_name'],
        'contact_phone' => $row['contact_phone'],
        'contact_email' => $row['contact_email'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'statistics' => [
            'total_policies' => intval($row['total_policies']),
            'active_policies' => intval($row['active_policies']),
            'total_premiums' => floatval($row['total_premiums'] ?? 0)
        ]
    ];
    
    echo json_encode(['success' => true, 'data' => $provider]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
