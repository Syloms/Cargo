<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../include/db.php';

// This script sets all users to offline
// Run this once to reset the database

try {
    // Set all users to offline in the database (using mysqli)
    $stmt = $conn->prepare("UPDATE users SET is_online = 0, last_seen = NOW()");
    $stmt->execute();
    
    $affected = $stmt->affected_rows;
    
    echo json_encode([
        'success' => true,
        'message' => "Reset $affected users to offline status",
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
