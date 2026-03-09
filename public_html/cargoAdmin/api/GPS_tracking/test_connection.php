<?php
// api/GPS_tracking/test_connection.php
// Run this to verify GPS tracking is set up correctly

header('Content-Type: application/json');
require_once '../../include/db.php';

$response = [
    'mysqli_connection' => false,
    'pdo_connection' => false,
    'gps_table_exists' => false,
    'can_insert' => false,
    'can_query' => false,
    'errors' => [],
];

// Test 1: MySQLi Connection
if (isset($conn) && $conn->ping()) {
    $response['mysqli_connection'] = true;
} else {
    $response['errors'][] = 'MySQLi connection failed';
}

// Test 2: PDO Connection
if (isset($pdo)) {
    try {
        $pdo->query('SELECT 1');
        $response['pdo_connection'] = true;
    } catch (PDOException $e) {
        $response['errors'][] = 'PDO connection failed: ' . $e->getMessage();
    }
} else {
    $response['errors'][] = 'PDO object not found';
}

// Test 3: GPS Table Exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'gps_locations'");
    if ($stmt->rowCount() > 0) {
        $response['gps_table_exists'] = true;
        
        // Test 4: Can Insert
        try {
            $testInsert = $pdo->prepare("
                INSERT INTO gps_locations 
                (booking_id, latitude, longitude, speed, accuracy, timestamp) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $testInsert->execute([9999, 8.4319, 125.9831, 0, 10]);
            $response['can_insert'] = true;
            
            // Clean up test data
            $pdo->exec("DELETE FROM gps_locations WHERE booking_id = 9999");
            
        } catch (PDOException $e) {
            $response['errors'][] = 'Insert failed: ' . $e->getMessage();
        }
        
        // Test 5: Can Query
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_locations");
            $result = $stmt->fetch();
            $response['can_query'] = true;
            $response['total_records'] = $result['count'];
        } catch (PDOException $e) {
            $response['errors'][] = 'Query failed: ' . $e->getMessage();
        }
        
    } else {
        $response['errors'][] = 'GPS locations table does not exist. Run create_table_gps.php first.';
    }
} catch (PDOException $e) {
    $response['errors'][] = 'Table check failed: ' . $e->getMessage();
}

// Overall Status
$response['status'] = (
    $response['mysqli_connection'] &&
    $response['pdo_connection'] &&
    $response['gps_table_exists'] &&
    $response['can_insert'] &&
    $response['can_query']
) ? 'SUCCESS' : 'FAILED';

echo json_encode($response, JSON_PRETTY_PRINT);
?>