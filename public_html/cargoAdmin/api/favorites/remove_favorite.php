<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../include/db.php';
require_once __DIR__ . '/../security/suspension_guard.php';

try {
    // Get POST data
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $vehicle_type = isset($_POST['vehicle_type']) ? $_POST['vehicle_type'] : '';
    $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;

    // Validate inputs
    if ($user_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid user ID'
        ]);
        exit;
    }

    // Block suspended users
    require_not_suspended($conn, $user_id);

    if (!in_array($vehicle_type, ['car', 'motorcycle'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid vehicle type'
        ]);
        exit;
    }

    if ($vehicle_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid vehicle ID'
        ]);
        exit;
    }

    // Delete favorite
    $delete_sql = "DELETE FROM favorites WHERE user_id = ? AND vehicle_type = ? AND vehicle_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("isi", $user_id, $vehicle_type, $vehicle_id);

    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Removed from favorites'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Favorite not found'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to remove from favorites'
        ]);
    }

    $delete_stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
