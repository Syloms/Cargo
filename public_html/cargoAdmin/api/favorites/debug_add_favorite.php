<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../../include/db.php';

$debug = [
    'received_post' => $_POST,
    'server_method' => $_SERVER['REQUEST_METHOD'],
];

// Simulate what add_favorite.php does
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$vehicle_type = isset($_POST['vehicle_type']) ? $_POST['vehicle_type'] : '';
$vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;

$debug['parsed'] = [
    'user_id' => $user_id,
    'vehicle_type' => $vehicle_type,
    'vehicle_id' => $vehicle_id,
];

// Validation
$debug['validations'] = [
    'user_id_valid' => $user_id > 0,
    'vehicle_type_valid' => in_array($vehicle_type, ['car', 'motorcycle']),
    'vehicle_id_valid' => $vehicle_id > 0,
];

if ($user_id > 0 && in_array($vehicle_type, ['car', 'motorcycle']) && $vehicle_id > 0) {
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'favorites'");
    $debug['table_exists'] = $table_check && $table_check->num_rows > 0;
    
    if ($debug['table_exists']) {
        // Check for duplicate
        $check_sql = "SELECT id FROM favorites WHERE user_id = ? AND vehicle_type = ? AND vehicle_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("isi", $user_id, $vehicle_type, $vehicle_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        $debug['already_exists'] = $check_result->num_rows > 0;
        
        if ($check_result->num_rows === 0) {
            // Try to insert
            $insert_sql = "INSERT INTO favorites (user_id, vehicle_type, vehicle_id) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isi", $user_id, $vehicle_type, $vehicle_id);
            
            $debug['insert_success'] = $insert_stmt->execute();
            $debug['insert_error'] = $insert_stmt->error;
            $debug['inserted_id'] = $insert_stmt->insert_id;
            
            $insert_stmt->close();
        }
        
        $check_stmt->close();
    } else {
        $debug['error'] = 'Favorites table does not exist! Run create_table_if_not_exists.php first';
    }
}

// Get all favorites for this user
if ($user_id > 0 && $debug['table_exists']) {
    $favorites = $conn->query("SELECT * FROM favorites WHERE user_id = $user_id");
    $debug['user_favorites'] = [];
    while ($row = $favorites->fetch_assoc()) {
        $debug['user_favorites'][] = $row;
    }
}

echo json_encode($debug, JSON_PRETTY_PRINT);
$conn->close();
?>
