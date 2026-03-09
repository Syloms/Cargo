<?php
/**
 * Get Available Insurance Coverage Types
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../include/db.php';

try {
    $query = "
        SELECT 
            id,
            coverage_name,
            coverage_code,
            description,
            base_premium_rate,
            min_coverage_amount,
            max_coverage_amount,
            is_mandatory
        FROM insurance_coverage_types
        WHERE is_active = 1
        ORDER BY base_premium_rate ASC
    ";
    
    $result = $conn->query($query);
    $coverageTypes = [];
    
    while ($row = $result->fetch_assoc()) {
        // Define coverage details for each type
        $details = [];
        switch ($row['coverage_code']) {
            case 'BASIC':
                $details = [
                    'collision' => 50000,
                    'liability' => 50000,
                    'theft' => 0,
                    'injury' => 0,
                    'roadside' => false,
                    'deductible' => 5000
                ];
                break;
            case 'STANDARD':
                $details = [
                    'collision' => 150000,
                    'liability' => 100000,
                    'theft' => 50000,
                    'injury' => 0,
                    'roadside' => false,
                    'deductible' => 3000
                ];
                break;
            case 'PREMIUM':
                $details = [
                    'collision' => 250000,
                    'liability' => 150000,
                    'theft' => 75000,
                    'injury' => 25000,
                    'roadside' => false,
                    'deductible' => 2000
                ];
                break;
            case 'COMPREHENSIVE':
                $details = [
                    'collision' => 500000,
                    'liability' => 300000,
                    'theft' => 150000,
                    'injury' => 50000,
                    'roadside' => true,
                    'deductible' => 1000
                ];
                break;
        }
        
        $coverageTypes[] = [
            'id' => intval($row['id']),
            'name' => $row['coverage_name'],
            'code' => strtolower($row['coverage_code']),
            'description' => $row['description'],
            'premium_rate' => floatval($row['base_premium_rate']) * 100, // Convert to percentage
            'min_coverage' => floatval($row['min_coverage_amount']),
            'max_coverage' => floatval($row['max_coverage_amount']),
            'is_mandatory' => (bool)$row['is_mandatory'],
            'features' => [
                'collision_damage' => floatval($details['collision']),
                'third_party_liability' => floatval($details['liability']),
                'theft_protection' => floatval($details['theft']),
                'personal_injury' => floatval($details['injury']),
                'roadside_assistance' => (bool)$details['roadside'],
                'deductible' => floatval($details['deductible'])
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $coverageTypes
    ], JSON_NUMERIC_CHECK);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
