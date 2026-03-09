<?php
/**
 * Update Claim Evidence Photos
 * Updates the evidence_photos field in insurance_claims table
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$claimId = isset($input['claim_id']) ? intval($input['claim_id']) : 0;
$photoUrls = isset($input['photo_urls']) ? $input['photo_urls'] : [];

if ($claimId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid claim ID']);
    exit;
}

if (!is_array($photoUrls) || empty($photoUrls)) {
    echo json_encode(['success' => false, 'message' => 'No photo URLs provided']);
    exit;
}

try {
    // Update the claim with photo URLs
    $photoUrlsJson = json_encode($photoUrls);
    
    $stmt = $conn->prepare("UPDATE insurance_claims SET evidence_photos = ? WHERE id = ?");
    $stmt->bind_param("si", $photoUrlsJson, $claimId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update claim photos');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Claim photos updated successfully',
        'data' => [
            'claim_id' => $claimId,
            'photo_count' => count($photoUrls)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
