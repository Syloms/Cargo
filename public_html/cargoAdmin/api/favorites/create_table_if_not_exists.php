<?php
header('Content-Type: application/json');
require_once '../../include/db.php';

$response = ['status' => 'error', 'message' => ''];

try {
    // Check if table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'favorites'");
    
    if ($check_table && $check_table->num_rows > 0) {
        $response['status'] = 'success';
        $response['message'] = 'Favorites table already exists';
        
        // Get count
        $count = $conn->query("SELECT COUNT(*) as total FROM favorites");
        $count_row = $count->fetch_assoc();
        $response['total_favorites'] = $count_row['total'];
    } else {
        // Create table
        $sql = "CREATE TABLE IF NOT EXISTS favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            vehicle_type ENUM('car', 'motorcycle') NOT NULL,
            vehicle_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_favorite (user_id, vehicle_type, vehicle_id),
            INDEX idx_user_id (user_id),
            INDEX idx_vehicle_type (vehicle_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            $response['status'] = 'success';
            $response['message'] = 'Favorites table created successfully';
            $response['total_favorites'] = 0;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Failed to create table: ' . $conn->error;
        }
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
$conn->close();
?>
