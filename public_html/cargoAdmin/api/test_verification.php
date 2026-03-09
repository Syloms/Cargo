<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Use centralized database connection
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';

try {
    
    // Test query for user 7 (using PDO from db.php)
    $query = "SELECT * FROM user_verifications WHERE user_id = 7";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "debug" => true,
        "user_id" => 7,
        "results" => $result,
        "count" => count($result)
    ], JSON_PRETTY_PRINT);
    
} catch(PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>