<?php
/**
 * Debug endpoint to check policy and vehicle data
 */

header('Content-Type: application/json');
require_once '../../include/db.php';

$policyId = $_GET['policy_id'] ?? null;

if (!$policyId) {
    echo json_encode(['error' => 'Policy ID required']);
    exit;
}

// First, get basic policy info
$stmt = $conn->prepare("SELECT * FROM insurance_policies WHERE id = ?");
$stmt->bind_param('i', $policyId);
$stmt->execute();
$policy = $stmt->get_result()->fetch_assoc();

if (!$policy) {
    echo json_encode(['error' => 'Policy not found']);
    exit;
}

// Get vehicle data separately based on type
$vehicle = null;
if ($policy['vehicle_type'] === 'car') {
    $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->bind_param('i', $policy['vehicle_id']);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
} else if ($policy['vehicle_type'] === 'motorcycle') {
    $stmt = $conn->prepare("SELECT * FROM motorcycles WHERE id = ?");
    $stmt->bind_param('i', $policy['vehicle_id']);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
}

// Get owner
$stmt = $conn->prepare("SELECT id, fullname, email, phone FROM users WHERE id = ?");
$stmt->bind_param('i', $policy['owner_id']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();

// Get renter
$stmt = $conn->prepare("SELECT id, fullname, email, phone FROM users WHERE id = ?");
$stmt->bind_param('i', $policy['user_id']);
$stmt->execute();
$renter = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'policy' => $policy,
    'vehicle' => $vehicle,
    'owner' => $owner,
    'renter' => $renter,
    'debug' => [
        'vehicle_type' => $policy['vehicle_type'],
        'vehicle_id' => $policy['vehicle_id'],
        'vehicle_found' => $vehicle ? 'YES' : 'NO',
        'vehicle_table' => $policy['vehicle_type'] === 'car' ? 'cars' : 'motorcycles'
    ]
], JSON_PRETTY_PRINT);
