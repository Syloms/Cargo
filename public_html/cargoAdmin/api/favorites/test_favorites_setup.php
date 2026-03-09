<?php
header('Content-Type: application/json');
require_once '../../include/db.php';

// Test if favorites table exists
$test_results = [
    'table_exists' => false,
    'table_structure' => [],
    'sample_insert_test' => false,
    'api_endpoints' => [],
];

// 1. Check if table exists
$check_table = $conn->query("SHOW TABLES LIKE 'favorites'");
$test_results['table_exists'] = $check_table && $check_table->num_rows > 0;

if ($test_results['table_exists']) {
    // 2. Get table structure
    $structure = $conn->query("DESCRIBE favorites");
    while ($row = $structure->fetch_assoc()) {
        $test_results['table_structure'][] = $row;
    }
    
    // 3. Count existing favorites
    $count = $conn->query("SELECT COUNT(*) as total FROM favorites");
    $count_row = $count->fetch_assoc();
    $test_results['total_favorites'] = $count_row['total'];
    
    // 4. Get sample favorites
    $sample = $conn->query("SELECT * FROM favorites LIMIT 5");
    $test_results['sample_data'] = [];
    while ($row = $sample->fetch_assoc()) {
        $test_results['sample_data'][] = $row;
    }
} else {
    $test_results['error'] = 'Favorites table does not exist. Please run the migration SQL.';
}

// 5. Check API files
$api_files = [
    'add_favorite.php',
    'remove_favorite.php',
    'get_favorites.php',
    'check_favorite.php'
];

foreach ($api_files as $file) {
    $test_results['api_endpoints'][$file] = file_exists(__DIR__ . '/' . $file);
}

echo json_encode($test_results, JSON_PRETTY_PRINT);
$conn->close();
?>
