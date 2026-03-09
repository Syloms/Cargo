<?php
/**
 * Debug Security Deposits Page
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Debugging Security Deposits Page</h2>";

// Step 1: Check session
session_start();
echo "<p>✅ Session started</p>";

// Step 2: Check database connection
require_once 'include/db.php';
echo "<p>✅ Database connection included</p>";

// Step 3: Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    echo "<p>❌ Not logged in as admin. Redirecting would happen here.</p>";
    echo "<p>Session data: <pre>" . print_r($_SESSION, true) . "</pre></p>";
} else {
    echo "<p>✅ Admin authenticated: ID = " . $_SESSION['admin_id'] . "</p>";
}

$admin_id = $_SESSION['admin_id'] ?? 1;

// Step 4: Check admin profile
try {
    require_once 'include/admin_profile.php';
    echo "<p>✅ Admin profile loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Admin profile error: " . $e->getMessage() . "</p>";
}

// Step 5: Test pagination variables
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
echo "<p>✅ Pagination: Page $page, Offset $offset</p>";

// Step 6: Test filters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
echo "<p>✅ Filters: Status='$statusFilter', Search='$searchQuery'</p>";

// Step 7: Build WHERE clause
$where = ["b.status = 'completed'"];
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $where[] = "b.security_deposit_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($searchQuery)) {
    $where[] = "(u.fullname LIKE ? OR b.id LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

$whereClause = implode(' AND ', $where);
echo "<p>✅ WHERE clause: $whereClause</p>";
echo "<p>✅ Params: " . implode(', ', $params) . "</p>";
echo "<p>✅ Types: $types</p>";

// Step 8: Test count query
echo "<h3>Testing Count Query:</h3>";
$countSql = "SELECT COUNT(*) as total FROM bookings b 
             LEFT JOIN users u ON b.user_id = u.id 
             WHERE $whereClause";
echo "<pre>$countSql</pre>";

try {
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    echo "<p>✅ Count statement prepared</p>";
    
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
        echo "<p>✅ Parameters bound</p>";
    }
    
    $countStmt->execute();
    echo "<p>✅ Count query executed</p>";
    
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    echo "<p>✅ Total records: $totalRecords</p>";
    
    $totalPages = ceil($totalRecords / $perPage);
    echo "<p>✅ Total pages: $totalPages</p>";
} catch (Exception $e) {
    echo "<p>❌ Count query error: " . $e->getMessage() . "</p>";
    echo "<p>SQL Error: " . $conn->error . "</p>";
}

// Step 9: Test main query
echo "<h3>Testing Main Query:</h3>";
$sql = "SELECT 
            b.id,
            b.user_id,
            b.vehicle_type,
            b.car_id as vehicle_id,
            b.total_amount,
            b.security_deposit_amount,
            b.security_deposit_status,
            b.security_deposit_held_at,
            b.security_deposit_refunded_at,
            b.security_deposit_refund_amount,
            b.security_deposit_deductions,
            b.security_deposit_deduction_reason,
            b.security_deposit_refund_reference,
            b.pickup_date,
            b.return_date,
            b.created_at,
            u.fullname as renter_name,
            u.email as renter_email,
            CASE 
                WHEN b.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model)
                WHEN b.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model)
            END as vehicle_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
        WHERE $whereClause
        ORDER BY b.security_deposit_held_at DESC
        LIMIT ? OFFSET ?";

echo "<pre>" . htmlspecialchars($sql) . "</pre>";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    echo "<p>✅ Main statement prepared</p>";
    
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';
    
    echo "<p>Final params: " . implode(', ', $params) . "</p>";
    echo "<p>Final types: $types</p>";
    
    $stmt->bind_param($types, ...$params);
    echo "<p>✅ Parameters bound</p>";
    
    $stmt->execute();
    echo "<p>✅ Main query executed</p>";
    
    $result = $stmt->get_result();
    echo "<p>✅ Results fetched: " . $result->num_rows . " rows</p>";
    
    // Show first row
    if ($result->num_rows > 0) {
        $firstRow = $result->fetch_assoc();
        echo "<h4>First Row Sample:</h4>";
        echo "<pre>" . print_r($firstRow, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p>❌ Main query error: " . $e->getMessage() . "</p>";
    echo "<p>SQL Error: " . $conn->error . "</p>";
}

echo "<h3>✅ All checks complete!</h3>";
?>
