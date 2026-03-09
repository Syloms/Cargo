<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Dashboard Test</title></head><body>";
echo "<h1>Testing Dashboard Components</h1>";

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
    
    echo "<p>4. Testing getRecentCars()...</p>";
    $query = getRecentCars($conn, 5);
    echo "<p style='color: green;'>✓ getRecentCars() executed</p>";
    
    echo "<p>5. Testing getTopPerformingCars()...</p>";
    $topCars = getTopPerformingCars($conn, 5);
    echo "<p style='color: green;'>✓ getTopPerformingCars() executed</p>";
    
    echo "<p>6. Testing getRecentBookings()...</p>";
    $recentBookings = getRecentBookings($conn, 10);
    echo "<p style='color: green;'>✓ getRecentBookings() executed</p>";
    
    echo "<p>7. Testing getRevenueByPeriod()...</p>";
    $revenue = getRevenueByPeriod($conn);
    echo "<p style='color: green;'>✓ getRevenueByPeriod() executed</p>";
    
    echo "<p>8. Testing getAverageBookingValue()...</p>";
    $avgBookingValue = getAverageBookingValue($conn);
    echo "<p style='color: green;'>✓ getAverageBookingValue() executed</p>";
    
    echo "<p>9. Testing getCarUtilizationRate()...</p>";
    $utilizationRate = getCarUtilizationRate($conn);
    echo "<p style='color: green;'>✓ getCarUtilizationRate() executed</p>";
    
    echo "<p>10. Testing getCancellationRate()...</p>";
    $cancellationRate = getCancellationRate($conn);
    echo "<p style='color: green;'>✓ getCancellationRate() executed</p>";
    
    echo "<h2 style='color: green;'>✅ All tests passed!</h2>";
    echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Fatal Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>
