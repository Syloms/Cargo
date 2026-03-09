<?php
require_once 'include/db.php';

// Check car ID 35
$stmt = $conn->prepare("SELECT id, brand, model, car_year, plate_number, status FROM cars WHERE id = 35");
$stmt->execute();
$car = $stmt->get_result()->fetch_assoc();

echo "<h2>Car ID 35:</h2>";
echo "<pre>";
print_r($car);
echo "</pre>";

// Check policy 59
$stmt = $conn->prepare("SELECT * FROM insurance_policies WHERE id = 59");
$stmt->execute();
$policy = $stmt->get_result()->fetch_assoc();

echo "<h2>Policy 59 (Full Data):</h2>";
echo "<pre>";
print_r($policy);
echo "</pre>";

if (!$policy) {
    echo "<h2 style='color: red;'>❌ Policy 59 DOES NOT EXIST in database!</h2>";
} else {
    echo "<h2>Specific Fields:</h2>";
    echo "vehicle_type: [" . ($policy['vehicle_type'] ?? 'NULL') . "]<br>";
    echo "vehicle_id: [" . ($policy['vehicle_id'] ?? 'NULL') . "]<br>";
    echo "vehicle_type is empty: " . (empty($policy['vehicle_type']) ? 'YES' : 'NO') . "<br>";
    echo "vehicle_type length: " . strlen($policy['vehicle_type'] ?? '') . "<br>";
}

// Check what policies exist
$stmt = $conn->prepare("SELECT id, policy_number, vehicle_type, vehicle_id FROM insurance_policies ORDER BY id DESC LIMIT 10");
$stmt->execute();
$policies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h2>Recent Policies (Last 10):</h2>";
echo "<pre>";
print_r($policies);
echo "</pre>";
