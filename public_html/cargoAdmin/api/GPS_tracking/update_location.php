<?php
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $booking_id = isset($input['booking_id']) ? intval($input['booking_id']) : 0;
    $latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
    $longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;
    $speed = isset($input['speed']) ? floatval($input['speed']) : 0;
    $accuracy = isset($input['accuracy']) ? floatval($input['accuracy']) : 0;

    if ($booking_id <= 0 || $latitude === null || $longitude === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid parameters'
        ]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO gps_locations 
            (booking_id, latitude, longitude, speed, accuracy, timestamp) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $booking_id,
            $latitude,
            $longitude,
            $speed,
            $accuracy
        ]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Location updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update location'
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