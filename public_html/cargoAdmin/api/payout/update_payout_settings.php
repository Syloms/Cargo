<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../../include/db.php';

if (!isset($_POST['user_id'], $_POST['gcash_number'], $_POST['gcash_name'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

$userId = intval($_POST['user_id']);
$gcashNumber = trim($_POST['gcash_number']);
$gcashName = trim($_POST['gcash_name']);

// Validate GCash number format
if (!preg_match('/^09\d{9}$/', $gcashNumber)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid GCash number format'
    ]);
    exit;
}

try {
    $query = "
        UPDATE users
        SET gcash_number = ?,
            gcash_name = ?
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $gcashNumber, $gcashName, $userId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Payout settings updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update settings'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();