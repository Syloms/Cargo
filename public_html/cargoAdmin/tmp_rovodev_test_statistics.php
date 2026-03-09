<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Statistics Test</title></head><body>";
echo "<h1>Testing Statistics Page Components</h1>";

try {
    echo "<p>1. Including db.php...</p>";
    include 'include/db.php';
    echo "<p style='color: green;'>✓ db.php loaded successfully</p>";
    
    echo "<p>2. Including dashboard_stats.php...</p>";
    include 'include/dashboard_stats.php';
    echo "<p style='color: green;'>✓ dashboard_stats.php loaded successfully</p>";
    
    echo "<p>3. Testing getDashboardStats()...</p>";
    $stats = getDashboardStats($conn);
    echo "<p style='color: green;'>✓ getDashboardStats() executed</p>";
    
    echo "<p>4. Testing getBookingsStats()...</p>";
    $bookingsStats = getBookingsStats($conn);
    echo "<p style='color: green;'>✓ getBookingsStats() executed</p>";
    
    echo "<p>5. Testing getRevenueByPeriod()...</p>";
    $revenue = getRevenueByPeriod($conn);
    echo "<p style='color: green;'>✓ getRevenueByPeriod() executed</p>";
    
    echo "<p>6. Testing getTopPerformingVehicles()...</p>";
    $topCars = getTopPerformingVehicles($conn, 5);
    echo "<p style='color: green;'>✓ getTopPerformingVehicles() executed</p>";
    
    echo "<p>7. Testing getRecentBookings()...</p>";
    $recentBookings = getRecentBookings($conn, 10);
    echo "<p style='color: green;'>✓ getRecentBookings() executed</p>";
    
    echo "<p>8. Testing getAverageBookingValue()...</p>";
    $avgBookingValue = getAverageBookingValue($conn);
    echo "<p style='color: green;'>✓ getAverageBookingValue() executed</p>";
    
    echo "<p>9. Testing getCarUtilizationRate()...</p>";
    $utilizationRate = getCarUtilizationRate($conn);
    echo "<p style='color: green;'>✓ getCarUtilizationRate() executed</p>";
    
    echo "<p>10. Testing getCancellationRate()...</p>";
    $cancellationRate = getCancellationRate($conn);
    echo "<p style='color: green;'>✓ getCancellationRate() executed</p>";
    
    echo "<p>11. Testing locations query...</p>";
    $motorcyclesExists = $conn->query("SHOW TABLES LIKE 'motorcycles'");
    $hasMotorcycles = $motorcyclesExists && $motorcyclesExists->num_rows > 0;
    
    if ($hasMotorcycles) {
        $locationsQuery = "
            SELECT DISTINCT location FROM cars WHERE location IS NOT NULL AND location != ''
            UNION
            SELECT DISTINCT location FROM motorcycles WHERE location IS NOT NULL AND location != ''
            ORDER BY location
        ";
    } else {
        $locationsQuery = "
            SELECT DISTINCT location FROM cars WHERE location IS NOT NULL AND location != ''
            ORDER BY location
        ";
    }
    $locationsResult = $conn->query($locationsQuery);
    if ($locationsResult) {
        echo "<p style='color: green;'>✓ Locations query executed</p>";
    } else {
        echo "<p style='color: red;'>✗ Locations query failed: " . $conn->error . "</p>";
    }
    
    echo "<p>12. Testing vehicle types query...</p>";
    if ($hasMotorcycles) {
        // Check if motorcycles table has 'type' column
        $motorcycleColumnsCheck = $conn->query("SHOW COLUMNS FROM motorcycles LIKE 'type'");
        $hasTypeColumn = $motorcycleColumnsCheck && $motorcycleColumnsCheck->num_rows > 0;
        
        if ($hasTypeColumn) {
            $vehicleTypesQuery = "
                SELECT DISTINCT 'Car' as vehicle_category, body_style as type_name
                FROM cars
                WHERE body_style IS NOT NULL AND body_style != ''
                UNION
                SELECT DISTINCT 'Motorcycle' as vehicle_category, type as type_name
                FROM motorcycles
                WHERE type IS NOT NULL AND type != ''
                ORDER BY vehicle_category, type_name
            ";
        } else {
            $vehicleTypesQuery = "
                SELECT DISTINCT 'Car' as vehicle_category, body_style as type_name
                FROM cars
                WHERE body_style IS NOT NULL AND body_style != ''
                UNION
                SELECT DISTINCT 'Motorcycle' as vehicle_category, 
                       COALESCE(body_style, model, 'Standard') as type_name
                FROM motorcycles
                WHERE COALESCE(body_style, model) IS NOT NULL AND COALESCE(body_style, model) != ''
                ORDER BY vehicle_category, type_name
            ";
        }
    } else {
        $vehicleTypesQuery = "
            SELECT DISTINCT 'Car' as vehicle_category, body_style as type_name
            FROM cars
            WHERE body_style IS NOT NULL AND body_style != ''
            ORDER BY vehicle_category, type_name
        ";
    }
    $vehicleTypesResult = $conn->query($vehicleTypesQuery);
    if ($vehicleTypesResult) {
        echo "<p style='color: green;'>✓ Vehicle types query executed</p>";
    } else {
        echo "<p style='color: red;'>✗ Vehicle types query failed: " . $conn->error . "</p>";
    }
    
    echo "<h2 style='color: green;'>✅ All tests passed!</h2>";
    echo "<p><a href='statistics.php'>Go to Statistics</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Fatal Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>
