<?php
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

    if ($booking_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid booking ID'
        ]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT latitude, longitude, speed, accuracy, timestamp
            FROM gps_locations
            WHERE booking_id = ?
            ORDER BY timestamp DESC
            LIMIT 1
        ");
        
        $stmt->execute([$booking_id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($location) {
            echo json_encode([
                'success' => true,
                'location' => $location
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No location data found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}