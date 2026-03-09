<?php
// Save this as: test_connection.php in carGOAdmin folder
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

echo json_encode([
    'status' => 'success',
    'message' => 'PHP is working!',
    'php_version' => phpversion(),
    'time' => date('Y-m-d H:i:s')
]);
?>