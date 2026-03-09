<?php
/**
 * Debug script to check claim data including evidence photos
 */

require_once __DIR__ . '/include/db.php';

// Get a claim to debug
$claimId = isset($_GET['id']) ? intval($_GET['id']) : 1;

echo "<h2>Debugging Claim ID: $claimId</h2>";

$query = "SELECT * FROM insurance_claims WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $claimId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color:red;'>No claim found with ID $claimId</p>";
    
    // Show all claims
    $allClaims = $conn->query("SELECT id, claim_number, status FROM insurance_claims ORDER BY id DESC LIMIT 10");
    echo "<h3>Available Claims:</h3><ul>";
    while ($claim = $allClaims->fetch_assoc()) {
        echo "<li><a href='?id={$claim['id']}'>Claim #{$claim['id']}: {$claim['claim_number']} - {$claim['status']}</a></li>";
    }
    echo "</ul>";
    exit;
}

$claim = $result->fetch_assoc();

echo "<h3>Raw Database Data:</h3>";
echo "<pre>";
print_r($claim);
echo "</pre>";

echo "<hr>";

echo "<h3>Evidence Photos Field:</h3>";
echo "<p><strong>Raw Value:</strong> " . htmlspecialchars($claim['evidence_photos'] ?? 'NULL') . "</p>";
echo "<p><strong>Type:</strong> " . gettype($claim['evidence_photos']) . "</p>";
echo "<p><strong>Length:</strong> " . strlen($claim['evidence_photos'] ?? '') . "</p>";

if (!empty($claim['evidence_photos'])) {
    $decoded = json_decode($claim['evidence_photos'], true);
    echo "<p><strong>JSON Valid:</strong> " . (json_last_error() === JSON_ERROR_NONE ? 'Yes' : 'No - ' . json_last_error_msg()) . "</p>";
    echo "<p><strong>Decoded Data:</strong></p>";
    echo "<pre>";
    print_r($decoded);
    echo "</pre>";
    
    if (is_array($decoded) && count($decoded) > 0) {
        echo "<h3>Photos Found:</h3><ul>";
        foreach ($decoded as $photo) {
            echo "<li>$photo</li>";
        }
        echo "</ul>";
    }
}

echo "<hr>";
echo "<h3>Claimant Contact:</h3>";
echo "<p><strong>User ID:</strong> {$claim['user_id']}</p>";

$userQuery = "SELECT id, fullname, email, phone FROM users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param('i', $claim['user_id']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

echo "<pre>";
print_r($user);
echo "</pre>";

$conn->close();
?>
