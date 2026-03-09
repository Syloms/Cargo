<?php
/**
 * Upload Claim Evidence Photos
 * Handles multipart file uploads for insurance claim evidence
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get claim_id from POST data
$claimId = isset($_POST['claim_id']) ? intval($_POST['claim_id']) : 0;

if ($claimId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid claim ID']);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../../uploads/claims/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Create .htaccess to allow access to images
$htaccessPath = $uploadDir . '.htaccess';
if (!file_exists($htaccessPath)) {
    file_put_contents($htaccessPath, "Options -Indexes\n<Files ~ \"\\.(jpg|jpeg|png|gif)$\">\n    Order allow,deny\n    Allow from all\n</Files>");
}

// Create index.php to prevent directory listing
$indexPath = $uploadDir . 'index.php';
if (!file_exists($indexPath)) {
    file_put_contents($indexPath, "<?php http_response_code(403); ?>");
}

$uploadedPhotos = [];
$errors = [];

// Check if files were uploaded
if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
    echo json_encode(['success' => false, 'message' => 'No photos uploaded']);
    exit;
}

// Process each uploaded file
$fileCount = count($_FILES['photos']['name']);
for ($i = 0; $i < $fileCount; $i++) {
    // Check for upload errors
    if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading photo " . ($i + 1);
        continue;
    }

    $fileName = $_FILES['photos']['name'][$i];
    $fileTmpName = $_FILES['photos']['tmp_name'][$i];
    $fileSize = $_FILES['photos']['size'][$i];
    $fileType = $_FILES['photos']['type'][$i];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        $errors[] = "Invalid file type for: $fileName";
        continue;
    }

    // Validate file size (max 5MB)
    if ($fileSize > 5 * 1024 * 1024) {
        $errors[] = "File too large: $fileName (max 5MB)";
        continue;
    }

    // Generate unique filename
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $newFileName = "claim_{$claimId}_photo_" . ($i + 1) . "_" . time() . "." . $fileExtension;
    $targetPath = $uploadDir . $newFileName;

    // Move uploaded file
    if (move_uploaded_file($fileTmpName, $targetPath)) {
        $relativePath = "uploads/claims/" . $newFileName;
        $uploadedPhotos[] = $relativePath;
    } else {
        $errors[] = "Failed to save: $fileName";
    }
}

// Check if any photos were successfully uploaded
if (empty($uploadedPhotos)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload photos',
        'errors' => $errors
    ]);
    exit;
}

// Merge with existing evidence_photos on the claim
$existing = [];
$stmt = $conn->prepare("SELECT evidence_photos FROM insurance_claims WHERE id = ?");
$stmt->bind_param("i", $claimId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $existingJson = $row['evidence_photos'] ?? '[]';
    $decoded = json_decode($existingJson, true);
    if (is_array($decoded)) {
        $existing = $decoded;
    }
}
$merged = array_values(array_unique(array_merge($existing, $uploadedPhotos)));
$mergedJson = json_encode($merged);
$stmt = $conn->prepare("UPDATE insurance_claims SET evidence_photos = ? WHERE id = ?");
$stmt->bind_param("si", $mergedJson, $claimId);
$stmt->execute();
$stmt->close();

// Build full URLs for response
$fullUrls = [];
if (defined('BASE_URL')) {
    foreach ($uploadedPhotos as $p) {
        $fullUrls[] = BASE_URL . '/' . $p;
    }
} else {
    $fullUrls = $uploadedPhotos;
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Photos uploaded successfully',
    'data' => [
        'uploaded_count' => count($uploadedPhotos),
        'photo_urls' => $fullUrls,
        'relative_paths' => $uploadedPhotos,
        'errors' => $errors
    ]
]);

if (isset($conn)) {
    $conn->close();
}
?>
