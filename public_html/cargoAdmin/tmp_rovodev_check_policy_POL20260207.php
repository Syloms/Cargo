<?php
require_once 'include/db.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>Check Policy POL-20260207-000051</title></head>
<body style="font-family: monospace;">
<h1>Checking Policy: POL-20260207-000051</h1>

<?php
$policy_num = 'POL-20260207-000051';

// Step 1: Get policy data
echo "<h2>Step 1: Insurance Policy Data</h2>";
$query = "SELECT * FROM insurance_policies WHERE policy_number = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $policy_num);
$stmt->execute();
$policy = $stmt->get_result()->fetch_assoc();

if ($policy) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    foreach ($policy as $key => $val) {
        $highlight = in_array($key, ['vehicle_type', 'vehicle_id', 'booking_id']) ? 'background:yellow;' : '';
        echo "<tr style='$highlight'><td><strong>$key</strong></td><td>" . ($val ?? '<span style="color:red;">NULL</span>') . "</td></tr>";
    }
    echo "</table>";
    
    $booking_id = $policy['booking_id'];
    $vehicle_type = $policy['vehicle_type'];
    $vehicle_id = $policy['vehicle_id'];
    
    // Step 2: Get booking data
    echo "<h2>Step 2: Booking Data (ID: $booking_id)</h2>";
    $query = "SELECT * FROM bookings WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if ($booking) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($booking as $key => $val) {
            $highlight = in_array($key, ['vehicle_type', 'car_id', 'motorcycle_id']) ? 'background:yellow;' : '';
            echo "<tr style='$highlight'><td><strong>$key</strong></td><td>" . ($val ?? '<span style="color:red;">NULL</span>') . "</td></tr>";
        }
        echo "</table>";
        
        $b_vehicle_type = $booking['vehicle_type'];
        $b_car_id = $booking['car_id'] ?? null;
        
        echo "<h2>Step 3: Looking for Vehicle</h2>";
        echo "<p><strong>From Policy:</strong> vehicle_type='$vehicle_type', vehicle_id='$vehicle_id'</p>";
        echo "<p><strong>From Booking:</strong> vehicle_type='$b_vehicle_type', car_id='$b_car_id'</p>";
        
        // Try to find in cars table using policy vehicle_id
        if ($vehicle_id && $vehicle_type == 'car') {
            echo "<h3>A. Searching CARS table with policy.vehicle_id = $vehicle_id</h3>";
            $query = "SELECT * FROM cars WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $vehicle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<p style='color:green;'>✓ FOUND in cars table!</p>";
                echo "<pre style='background:#d4edda;padding:10px;'>";
                print_r($result->fetch_assoc());
                echo "</pre>";
            } else {
                echo "<p style='color:red;'>✗ NOT FOUND in cars table with vehicle_id=$vehicle_id</p>";
            }
        }
        
        // Try to find in motorcycles table using policy vehicle_id
        if ($vehicle_id && $vehicle_type == 'motorcycle') {
            echo "<h3>B. Searching MOTORCYCLES table with policy.vehicle_id = $vehicle_id</h3>";
            $query = "SELECT * FROM motorcycles WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $vehicle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<p style='color:green;'>✓ FOUND in motorcycles table!</p>";
                echo "<pre style='background:#d4edda;padding:10px;'>";
                print_r($result->fetch_assoc());
                echo "</pre>";
            } else {
                echo "<p style='color:red;'>✗ NOT FOUND in motorcycles table with vehicle_id=$vehicle_id</p>";
            }
        }
        
        // Try to find using booking car_id
        if ($b_car_id) {
            echo "<h3>C. Searching using booking.car_id = $b_car_id</h3>";
            
            // Try cars table
            if ($b_vehicle_type == 'car') {
                echo "<h4>Searching CARS table</h4>";
                $query = "SELECT * FROM cars WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $b_car_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    echo "<p style='color:green;'>✓ FOUND in cars table!</p>";
                    echo "<pre style='background:#d4edda;padding:10px;'>";
                    print_r($result->fetch_assoc());
                    echo "</pre>";
                } else {
                    echo "<p style='color:red;'>✗ NOT FOUND in cars table</p>";
                }
            }
            
            // Try motorcycles table
            if ($b_vehicle_type == 'motorcycle') {
                echo "<h4>Searching MOTORCYCLES table</h4>";
                $query = "SELECT * FROM motorcycles WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $b_car_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    echo "<p style='color:green;'>✓ FOUND in motorcycles table!</p>";
                    $moto = $result->fetch_assoc();
                    echo "<pre style='background:#d4edda;padding:10px;'>";
                    print_r($moto);
                    echo "</pre>";
                    echo "<h3 style='color:green;'>VEHICLE NAME: {$moto['brand']} {$moto['model']}</h3>";
                } else {
                    echo "<p style='color:red;'>✗ NOT FOUND in motorcycles table with car_id=$b_car_id</p>";
                }
            }
        }
        
    } else {
        echo "<p style='color:red;'>BOOKING NOT FOUND!</p>";
    }
    
} else {
    echo "<p style='color:red;'>POLICY NOT FOUND!</p>";
}

$conn->close();
?>
</body>
</html>
