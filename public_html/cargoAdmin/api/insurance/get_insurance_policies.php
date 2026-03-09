<?php
/**
 * Get Insurance Policies for Owner
 * Wrapper endpoint for Flutter app compatibility
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../include/db.php';

try {
    $ownerId = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : (isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0);
    
    if ($ownerId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid owner ID'
        ]);
        exit;
    }

    // Get all policies for the owner
    $query = "
        SELECT 
            p.*,
            b.car_id,
            b.vehicle_type,
            COALESCE(c.brand, m.brand) as vehicle_brand,
            COALESCE(c.model, m.model) as vehicle_model,
            COALESCE(c.car_year, m.motorcycle_year) as vehicle_year,
            ct.name as coverage_type_name,
            ct.description as coverage_description
        FROM insurance_policies p
        LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        LEFT JOIN insurance_coverage_types ct ON p.coverage_type_id = ct.id
        WHERE p.owner_id = ?
        ORDER BY p.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $result = $stmt->get_result();

    $policies = [];
    while ($row = $result->fetch_assoc()) {
        $policies[] = $row;
    }

    echo json_encode([
        'success' => true,
        'policies' => $policies,
        'total' => count($policies)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
