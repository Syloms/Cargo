<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../include/db.php';

if (!isset($_GET['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required'
    ]);
    exit;
}

$userId = intval($_GET['user_id']);

try {
    $query = "
        SELECT 
            gcash_number,
            gcash_name
        FROM users
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $settings = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>