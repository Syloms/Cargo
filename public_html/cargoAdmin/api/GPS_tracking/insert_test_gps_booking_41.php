<?php
// ========================================
// Save as: api/GPS_tracking/insert_test_gps_booking_41.php
// Run once to add test GPS data for booking #41
// ========================================
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../../include/db.php';

try {
    $booking_id = 41;
    
    // Base coordinates (San Francisco, Agusan del Sur - from your database)
    $base_lat = 8.4319;
    $base_lng = 125.9831;
    
    // Delete old test data for booking 41
    $stmt = $pdo->prepare("DELETE FROM gps_locations WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    
    $inserted = 0;
    $sample_data = [];
    
    // Insert 20 test locations simulating a route
    for ($i = 0; $i < 20; $i++) {
        // Create movement pattern (simulating driving around)
        $lat = $base_lat + (($i - 10) * 0.002); // Move north/south
        $lng = $base_lng + (($i - 10) * 0.002); // Move east/west
        $speed = rand(20, 60) * 1.0; // Random speed 20-60 km/h
        $accuracy = rand(5, 15) * 1.0; // Random accuracy 5-15m
        
        // Create timestamps - most recent first, going back in time
        $minutes_ago = $i * 2; // 2 minutes apart
        
        $stmt = $pdo->prepare("
            INSERT INTO gps_locations 
            (booking_id, latitude, longitude, speed, accuracy, timestamp) 
            VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? MINUTE))
        ");
        
        $stmt->execute([
            $booking_id,
            $lat,
            $lng,
            $speed,
            $accuracy,
            $minutes_ago
        ]);
        
        $inserted++;
        
        if ($i < 5) {
            $sample_data[] = [
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => $speed,
                'minutes_ago' => $minutes_ago
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Inserted $inserted GPS records for booking #41",
        'booking_id' => $booking_id,
        'inserted_count' => $inserted,
        'sample_data' => $sample_data,
        'instructions' => [
            'Now test the tracking screen again',
            'You should see the car marker on the map',
            'Speed and Last Update should show values'
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}