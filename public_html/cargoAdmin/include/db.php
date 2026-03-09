<?php
// ========================================
// DATABASE CONNECTION (MySQLi + PDO)
// ========================================

// Load global configuration
require_once __DIR__ . '/config.php';

// Use configuration constants
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db = DB_NAME;

// MySQLi Connection (for existing code)
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    if (DEBUG_MODE) {
        die("MySQLi Error: " . $conn->connect_error);
    } else {
        die("Database connection failed. Please try again later.");
    }
}

// Set charset to UTF-8mb4 for proper emoji/unicode support
$conn->set_charset("utf8mb4");

// PDO Connection (for GPS tracking and prepared statements)
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("PDO Error: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}