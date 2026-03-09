<?php
/**
 * Fix Missing vehicle_type in insurance_policies Table
 * This script automatically detects and fills in missing vehicle_type values
 */

require_once 'include/db.php';

echo "<h1>Fix Missing vehicle_type in Insurance Policies</h1>";

// Get all policies with empty vehicle_type
$stmt = $conn->prepare("SELECT id, policy_number, vehicle_id, vehicle_type FROM insurance_policies WHERE vehicle_type = '' OR vehicle_type IS NULL");
$stmt->execute();
$policies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<p>Found " . count($policies) . " policies with missing vehicle_type</p>";

$fixed = 0;
$errors = 0;

foreach ($policies as $policy) {
    echo "<hr>";
    echo "<strong>Policy {$policy['id']} ({$policy['policy_number']})</strong><br>";
    echo "Vehicle ID: {$policy['vehicle_id']}<br>";
    
    // Check if it's a car
    $stmt = $conn->prepare("SELECT id FROM cars WHERE id = ?");
    $stmt->bind_param('i', $policy['vehicle_id']);
    $stmt->execute();
    $isCar = $stmt->get_result()->fetch_assoc();
    
    if ($isCar) {
        // Update to 'car'
        $updateStmt = $conn->prepare("UPDATE insurance_policies SET vehicle_type = 'car' WHERE id = ?");
        $updateStmt->bind_param('i', $policy['id']);
        if ($updateStmt->execute()) {
            echo "<span style='color: green;'>✓ Updated to vehicle_type = 'car'</span><br>";
            $fixed++;
        } else {
            echo "<span style='color: red;'>✗ Error updating</span><br>";
            $errors++;
        }
        continue;
    }
    
    // Check if it's a motorcycle
    $stmt = $conn->prepare("SELECT id FROM motorcycles WHERE id = ?");
    $stmt->bind_param('i', $policy['vehicle_id']);
    $stmt->execute();
    $isMotorcycle = $stmt->get_result()->fetch_assoc();
    
    if ($isMotorcycle) {
        // Update to 'motorcycle'
        $updateStmt = $conn->prepare("UPDATE insurance_policies SET vehicle_type = 'motorcycle' WHERE id = ?");
        $updateStmt->bind_param('i', $policy['id']);
        if ($updateStmt->execute()) {
            echo "<span style='color: green;'>✓ Updated to vehicle_type = 'motorcycle'</span><br>";
            $fixed++;
        } else {
            echo "<span style='color: red;'>✗ Error updating</span><br>";
            $errors++;
        }
        continue;
    }
    
    // Vehicle not found in either table
    echo "<span style='color: orange;'>⚠ Vehicle ID {$policy['vehicle_id']} not found in cars or motorcycles table</span><br>";
    $errors++;
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p style='color: green;'><strong>Fixed: $fixed policies</strong></p>";
echo "<p style='color: red;'><strong>Errors: $errors policies</strong></p>";

if ($fixed > 0) {
    echo "<p style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    echo "✅ <strong>Success!</strong> The missing vehicle_type fields have been filled in.<br>";
    echo "You can now send emails for all policies with complete vehicle information.";
    echo "</p>";
}

echo "<p><a href='insurance.php'>← Back to Insurance Page</a></p>";
