<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$response = [
    'test' => 'Favorites API is accessible',
    'timestamp' => date('Y-m-d H:i:s'),
    'get_params' => $_GET,
];

// Test 1: Check if db.php exists
$db_file = '../../include/db.php';
$response['db_file_exists'] = file_exists($db_file);

// Test 2: Try to include db.php
try {
    require_once $db_file;
    $response['db_included'] = true;
    
    // Test 3: Check connection
    if (isset($conn)) {
        $response['db_connected'] = true;
        
        // Test 4: Check if favorites table exists
        $result = $conn->query("SHOW TABLES LIKE 'favorites'");
        $response['favorites_table_exists'] = ($result && $result->num_rows > 0);
        
        if ($response['favorites_table_exists']) {
            // Test 5: Count favorites
            $count_result = $conn->query("SELECT COUNT(*) as total FROM favorites");
            $count_row = $count_result->fetch_assoc();
            $response['total_favorites'] = $count_row['total'];
            
            // Test 6: Get user_id parameter
            $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
            $response['requested_user_id'] = $user_id;
            
            if ($user_id > 0) {
                // Test 7: Get favorites for this user
                $user_favorites = $conn->query("SELECT * FROM favorites WHERE user_id = $user_id");
                $response['user_favorites_count'] = $user_favorites->num_rows;
                
                $favorites = [];
                while ($row = $user_favorites->fetch_assoc()) {
                    $favorites[] = $row;
                }
                $response['user_favorites'] = $favorites;
            }
        }
    } else {
        $response['db_connected'] = false;
        $response['error'] = 'Connection object not found';
    }
} catch (Exception $e) {
    $response['db_included'] = false;
    $response['error'] = $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
