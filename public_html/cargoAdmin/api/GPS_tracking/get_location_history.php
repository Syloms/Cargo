<?php
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

    if ($booking_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid booking ID',
            'history' => []
        ]);
        exit;
    }

    try {
        $sql = "SELECT latitude, longitude, speed, accuracy, timestamp 
                FROM gps_locations 
                WHERE booking_id = ? 
                ORDER BY timestamp ASC 
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $booking_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'history' => $history,
            'count' => count($history),
            'booking_id' => $booking_id
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'history' => []
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Use GET.',
        'history' => []
    ]);
}