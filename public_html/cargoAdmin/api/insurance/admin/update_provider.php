<?php
/**
 * Update provider details
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../../include/db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$providerId = $input['id'] ?? 0;
$providerName = $input['provider_name'] ?? '';
$contactPhone = $input['contact_phone'] ?? '';
$contactEmail = $input['contact_email'] ?? '';
$status = $input['status'] ?? 'active';

// Validation
if (!$providerId) {
    echo json_encode(['success' => false, 'message' => 'Provider ID is required']);
    exit;
}

if (empty($providerName)) {
    echo json_encode(['success' => false, 'message' => 'Provider name is required']);
    exit;
}

if (empty($contactEmail) && empty($contactPhone)) {
    echo json_encode(['success' => false, 'message' => 'At least one contact method is required']);
    exit;
}

try {
    $query = "
        UPDATE insurance_providers 
        SET 
            provider_name = ?,
            contact_phone = ?,
            contact_email = ?,
            status = ?
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssi', $providerName, $contactPhone, $contactEmail, $status, $providerId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Provider updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update provider');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
