<?php
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
    $time_range = isset($_GET['time_range']) ? $_GET['time_range'] : 'all';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

    if ($booking_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid booking ID',
            'history' => [],
            'count' => 0
        ]);
        exit;
    }

    try {
        $time_filter = '';
        $time_desc = 'All Time';
        
        switch ($time_range) {
            case '24':
                $time_filter = 'AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)';
                $time_desc = 'Last 24 Hours';
                break;
            case '7':
                $time_filter = 'AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                $time_desc = 'Last 7 Days';
                break;
            case '30':
                $time_filter = 'AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                $time_desc = 'Last 30 Days';
                break;
        }

        $sql = "SELECT latitude, longitude, speed, accuracy, timestamp 
                FROM gps_locations 
                WHERE booking_id = ? $time_filter 
                ORDER BY timestamp ASC 
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $booking_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count_sql = "SELECT COUNT(*) as total FROM gps_locations WHERE booking_id = ? $time_filter";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute([$booking_id]);
        $total_count = $count_stmt->fetch()['total'];

        $response = [
            'success' => true,
            'history' => $history,
            'count' => count($history),
            'total_available' => intval($total_count),
            'message' => count($history) > 0 
                ? "Found " . count($history) . " records ($time_desc)" 
                : "No records found ($time_desc)",
            'debug' => [
                'booking_id' => $booking_id,
                'time_range' => $time_range,
                'time_description' => $time_desc,
                'limit' => $limit,
                'records_returned' => count($history),
                'total_matching' => intval($total_count)
            ]
        ];

        echo json_encode($response);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error',
            'error' => $e->getMessage(),
            'history' => [],
            'count' => 0
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method (use GET)',
        'history' => [],
        'count' => 0
    ]);
}