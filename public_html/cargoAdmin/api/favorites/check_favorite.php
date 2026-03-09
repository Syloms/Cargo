<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../include/db.php';

try {
    // Get query parameters
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $vehicle_type = isset($_GET['vehicle_type']) ? $_GET['vehicle_type'] : '';
    $vehicle_id = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

    if ($user_id <= 0 || $vehicle_id <= 0 || !in_array($vehicle_type, ['car', 'motorcycle'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid parameters',
            'is_favorite' => false
        ]);
        exit;
    }

    // Check if favorite exists
    $check_sql = "SELECT id FROM favorites WHERE user_id = ? AND vehicle_type = ? AND vehicle_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("isi", $user_id, $vehicle_type, $vehicle_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    $is_favorite = $result->num_rows > 0;

    echo json_encode([
        'status' => 'success',
        'is_favorite' => $is_favorite
    ]);

    $check_stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'is_favorite' => false
    ]);
}
?>
