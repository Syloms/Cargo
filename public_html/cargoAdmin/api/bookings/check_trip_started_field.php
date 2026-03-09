<?php
/**
 * Quick check to see if trip_started_at field exists in bookings table
 */
header('Content-Type: application/json');
require_once '../../include/db.php';

// Check if column exists
$checkSql = "SHOW COLUMNS FROM bookings LIKE 'trip_started_at'";
$result = $conn->query($checkSql);

if ($result && $result->num_rows > 0) {
    echo json_encode([
        'success' => true,
        'field_exists' => true,
        'message' => 'trip_started_at field exists',
        'column_info' => $result->fetch_assoc()
    ]);
} else {
    echo json_encode([
        'success' => false,
        'field_exists' => false,
        'message' => 'trip_started_at field does NOT exist. Please run migration.',
        'migration_sql' => 'ALTER TABLE bookings ADD COLUMN trip_started_at DATETIME NULL AFTER pickup_time;'
    ]);
}

$conn->close();
?>
