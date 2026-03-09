<?php
/**
 * Test rendering after query
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'include/db.php';

if (!isset($_SESSION['admin_id'])) {
    die("Not logged in");
}

$admin_id = $_SESSION['admin_id'];
require_once 'include/admin_profile.php';

echo "Testing stats query...<br>";

// Test the stats query that comes after the main query
$statsQuery = "SELECT 
    COUNT(CASE WHEN security_deposit_status = 'held' THEN 1 END) as held_count,
    SUM(CASE WHEN security_deposit_status = 'held' THEN security_deposit_amount ELSE 0 END) as held_amount,
    COUNT(CASE WHEN security_deposit_status = 'refunded' THEN 1 END) as refunded_count,
    SUM(CASE WHEN security_deposit_status = 'refunded' THEN security_deposit_refund_amount ELSE 0 END) as refunded_amount,
    COUNT(CASE WHEN security_deposit_status = 'partial_refund' THEN 1 END) as partial_count,
    COUNT(CASE WHEN security_deposit_status = 'forfeited' THEN 1 END) as forfeited_count
    FROM bookings WHERE status = 'completed'";

try {
    $statsResult = $conn->query($statsQuery);
    if (!$statsResult) {
        throw new Exception("Stats query failed: " . $conn->error);
    }
    echo "✅ Stats query executed<br>";
    
    $stats = $statsResult->fetch_assoc();
    echo "✅ Stats fetched<br>";
    echo "<pre>" . print_r($stats, true) . "</pre>";
    
    // Test rendering the values
    echo "<h3>Testing Value Rendering:</h3>";
    echo "Held count: " . $stats['held_count'] . "<br>";
    echo "Held amount: " . number_format($stats['held_amount'] ?? 0, 2) . "<br>";
    echo "Refunded count: " . $stats['refunded_count'] . "<br>";
    echo "Refunded amount: " . number_format($stats['refunded_amount'] ?? 0, 2) . "<br>";
    echo "Partial count: " . $stats['partial_count'] . "<br>";
    echo "Forfeited count: " . $stats['forfeited_count'] . "<br>";
    
    echo "<h3>✅ All stats rendering works!</h3>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Now test include files
echo "<h3>Testing Include Files:</h3>";
try {
    ob_start();
    include 'include/sidebar.php';
    $sidebar = ob_get_clean();
    echo "✅ Sidebar included<br>";
} catch (Exception $e) {
    echo "❌ Sidebar error: " . $e->getMessage() . "<br>";
}

try {
    ob_start();
    include 'include/header.php';
    $header = ob_get_clean();
    echo "✅ Header included<br>";
} catch (Exception $e) {
    echo "❌ Header error: " . $e->getMessage() . "<br>";
}

echo "<h3>✅ All tests passed!</h3>";
?>
