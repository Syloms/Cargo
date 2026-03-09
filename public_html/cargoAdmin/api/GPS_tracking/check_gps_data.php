<?php
// ========================================
// FILE 1: check_gps_data.php
// Save as: carGOAdmin/api/GPS_tracking/check_gps_data.php
// ========================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../include/db.php';

try {
    // Count total GPS records
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM gps_locations");
    $total = $stmt->fetch()['total'];
    
    // Count records for booking #41
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM gps_locations WHERE booking_id = ?");
    $stmt->execute([41]);
    $booking41 = $stmt->fetch()['total'];
    
    // Count recent records (last 24 hours)
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM gps_locations 
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recent24 = $stmt->fetch()['total'];
    
    // Get sample records for booking #41
    $stmt = $pdo->prepare("
        SELECT * FROM gps_locations 
        WHERE booking_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 5
    ");
    $stmt->execute([41]);
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_records' => $total,
        'booking_41_records' => $booking41,
        'recent_24h_records' => $recent24,
        'sample_records' => $samples
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>