<?php
/**
 * Fix empty vehicle_type in insurance_policies table
 */

require_once 'include/db.php';

echo "<h1>Fixing Empty vehicle_type in Insurance Policies</h1>";

// Find policies with empty vehicle_type
$query = "SELECT id, policy_number, vehicle_type, booking_id FROM insurance_policies WHERE vehicle_type = '' OR vehicle_type IS NULL";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<p>Found <strong>{$result->num_rows}</strong> policies with empty vehicle_type</p>";
    
    echo "<h2>Fixing records...</h2>";
    
    while ($row = $result->fetch_assoc()) {
        $policyId = $row['id'];
        $bookingId = $row['booking_id'];
        
        // Get vehicle_type from booking
        $bookingQuery = "SELECT vehicle_type FROM bookings WHERE id = ?";
        $stmt = $conn->prepare($bookingQuery);
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        
        if ($booking && !empty($booking['vehicle_type'])) {
            $vehicleType = $booking['vehicle_type'];
            
            // Update policy
            $updateQuery = "UPDATE insurance_policies SET vehicle_type = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('si', $vehicleType, $policyId);
            
            if ($updateStmt->execute()) {
                echo "<p style='color:green;'>✓ Fixed policy {$row['policy_number']}: Set vehicle_type to '$vehicleType'</p>";
            } else {
                echo "<p style='color:red;'>✗ Failed to update policy {$row['policy_number']}</p>";
            }
        } else {
            echo "<p style='color:orange;'>⚠ Could not determine vehicle_type for policy {$row['policy_number']}</p>";
        }
    }
    
    echo "<h2>Done!</h2>";
    echo "<p><a href='insurance.php'>Go back to Insurance Management</a></p>";
    
} else {
    echo "<p style='color:green;'>No policies with empty vehicle_type found!</p>";
}

$conn->close();
?>
