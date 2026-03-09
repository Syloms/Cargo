<?php
require_once 'include/db.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>Test CONCAT with COALESCE</title></head>
<body>
<h1>Testing CONCAT Query</h1>

<?php
// Test the exact query being used
$query = "
SELECT 
    ip.policy_number,
    ip.vehicle_type,
    ip.vehicle_id,
    b.car_id,
    b.vehicle_type as booking_vehicle_type,
    c.brand as c_brand,
    c.model as c_model,
    m.brand as m_brand,
    m.model as m_model,
    bc.brand as bc_brand,
    bc.model as bc_model,
    bm.brand as bm_brand,
    bm.model as bm_model,
    COALESCE(c.brand, bc.brand) as coalesce_c_bc_brand,
    COALESCE(c.model, bc.model) as coalesce_c_bc_model,
    COALESCE(m.brand, bm.brand) as coalesce_m_bm_brand,
    COALESCE(m.model, bm.model) as coalesce_m_bm_model,
    CASE 
        WHEN ip.vehicle_type = 'car' THEN CONCAT(COALESCE(c.brand, bc.brand), ' ', COALESCE(c.model, bc.model))
        WHEN ip.vehicle_type = 'motorcycle' THEN CONCAT(COALESCE(m.brand, bm.brand), ' ', COALESCE(m.model, bm.model))
        ELSE NULL
    END as test_vehicle_name,
    COALESCE(
        CASE 
            WHEN ip.vehicle_type = 'car' THEN CONCAT(COALESCE(c.brand, bc.brand), ' ', COALESCE(c.model, bc.model))
            WHEN ip.vehicle_type = 'motorcycle' THEN CONCAT(COALESCE(m.brand, bm.brand), ' ', COALESCE(m.model, bm.model))
            ELSE NULL
        END,
        'Unknown Vehicle'
    ) AS vehicle_name
FROM insurance_policies ip
JOIN bookings b ON ip.booking_id = b.id
LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
LEFT JOIN cars bc ON b.car_id = bc.id AND b.vehicle_type = 'car'
LEFT JOIN motorcycles bm ON b.car_id = bm.id AND b.vehicle_type = 'motorcycle'
WHERE ip.policy_number = 'POL-20260207-000051'
";

$result = $conn->query($query);

if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<h2>Results:</h2>";
        echo "<table border='1' cellpadding='5'>";
        foreach ($row as $key => $val) {
            $highlight = '';
            if (strpos($key, 'brand') !== false || strpos($key, 'model') !== false || strpos($key, 'vehicle') !== false) {
                $highlight = 'background:yellow;';
            }
            echo "<tr style='$highlight'><td><strong>$key</strong></td><td>" . ($val ?? '<span style="color:red;">NULL</span>') . "</td></tr>";
        }
        echo "</table>";
        
        echo "<h2>Analysis:</h2>";
        echo "<p><strong>Policy vehicle_type:</strong> {$row['vehicle_type']}</p>";
        echo "<p><strong>Policy vehicle_id:</strong> {$row['vehicle_id']}</p>";
        echo "<p><strong>Booking car_id:</strong> {$row['car_id']}</p>";
        echo "<p><strong>Booking vehicle_type:</strong> {$row['booking_vehicle_type']}</p>";
        echo "<hr>";
        echo "<p><strong>c.brand (from ip.vehicle_id):</strong> " . ($row['c_brand'] ?? 'NULL') . "</p>";
        echo "<p><strong>m.brand (from ip.vehicle_id):</strong> " . ($row['m_brand'] ?? 'NULL') . "</p>";
        echo "<p><strong>bc.brand (from b.car_id):</strong> " . ($row['bc_brand'] ?? 'NULL') . "</p>";
        echo "<p><strong>bm.brand (from b.car_id):</strong> " . ($row['bm_brand'] ?? 'NULL') . "</p>";
        echo "<hr>";
        echo "<h3 style='color:blue;'>Final vehicle_name: " . $row['vehicle_name'] . "</h3>";
        
    } else {
        echo "<p style='color:red;'>No results found!</p>";
    }
} else {
    echo "<p style='color:red;'>Query Error: " . $conn->error . "</p>";
}

$conn->close();
?>
</body>
</html>
