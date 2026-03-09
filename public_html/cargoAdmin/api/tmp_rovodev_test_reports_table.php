<?php
header("Content-Type: application/json");
require_once "../include/db.php";

// Check if reports table exists
$result = $conn->query("SHOW TABLES LIKE 'reports'");
$tableExists = $result->num_rows > 0;

echo json_encode([
    "table_exists" => $tableExists,
    "table_count" => $result->num_rows
]);

if ($tableExists) {
    // Get table structure
    $columns = $conn->query("DESCRIBE reports");
    $columnList = [];
    while ($row = $columns->fetch_assoc()) {
        $columnList[] = $row;
    }
    
    echo "\n\nTable Structure:\n";
    echo json_encode($columnList, JSON_PRETTY_PRINT);
    
    // Check for report_logs table
    $logsResult = $conn->query("SHOW TABLES LIKE 'report_logs'");
    echo "\n\nreport_logs table exists: " . ($logsResult->num_rows > 0 ? "YES" : "NO");
} else {
    echo "\n\nERROR: reports table does not exist!";
}

$conn->close();
?>
