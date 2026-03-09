<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
            'message' => 'Invalid vehicle type. Must be "car" or "motorcycle"'
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

    // Check if favorite already exists
    $check_sql = "SELECT id FROM favorites WHERE user_id = ? AND vehicle_type = ? AND vehicle_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("isi", $user_id, $vehicle_type, $vehicle_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'This item is already in your favorites'
        ]);
        exit;
    }

    // Insert favorite
    $insert_sql = "INSERT INTO favorites (user_id, vehicle_type, vehicle_id) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("isi", $user_id, $vehicle_type, $vehicle_id);

    if ($insert_stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Added to favorites',
            'favorite_id' => $insert_stmt->insert_id
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to add to favorites'
        ]);
    }

    $insert_stmt->close();
    $check_stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
