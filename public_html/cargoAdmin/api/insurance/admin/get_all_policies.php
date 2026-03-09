<?php
/**
 * Admin: Get All Insurance Policies
 * For admin panel to manage policies
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once __DIR__ . '/../../../include/db.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit;
}

// Auto-expire policies before fetching
$expireStmt = $conn->prepare("
    UPDATE insurance_policies 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND policy_end < NOW()
");
$expireStmt->execute();
$expireStmt->close();

// TODO: Add admin authentication check
// require_once __DIR__ . '/../../../include/auth_check.php';

$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = ($page - 1) * $limit;

try {
    // Build query
    $query = "
        SELECT 
            ip.*,
            prov.provider_name,
            b.id as booking_id,
            b.pickup_date,
            b.return_date,
            b.total_amount as booking_amount,
            b.status as booking_status,
            u.fullname AS renter_name,
            u.email as renter_email,
            u.phone as renter_contact,
            o.fullname AS owner_name,
            COALESCE(
                CASE 
                    WHEN ip.vehicle_type = 'car' OR (ip.vehicle_type = '' AND b.vehicle_type = 'car') THEN CONCAT(COALESCE(c.brand, bc.brand), ' ', COALESCE(c.model, bc.model))
                    WHEN ip.vehicle_type = 'motorcycle' OR (ip.vehicle_type = '' AND b.vehicle_type = 'motorcycle') THEN CONCAT(COALESCE(m.brand, bm.brand), ' ', COALESCE(m.model, bm.model))
                    ELSE NULL
                END,
                'Unknown Vehicle'
            ) AS vehicle_name,
            DATEDIFF(ip.policy_end, NOW()) AS days_remaining,
            CASE 
                WHEN NOW() > ip.policy_end THEN 1
                ELSE 0
            END AS is_expired
        FROM insurance_policies ip
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        JOIN bookings b ON ip.booking_id = b.id
        JOIN users u ON ip.user_id = u.id
        JOIN users o ON ip.owner_id = o.id
        LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
        LEFT JOIN cars bc ON b.car_id = bc.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles bm ON b.car_id = bm.id AND b.vehicle_type = 'motorcycle'
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    // Filter by status
    if ($status !== 'all') {
        $query .= " AND ip.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Search filter
    if (!empty($search)) {
        $query .= " AND (
            ip.policy_number LIKE ? OR
            u.fullname LIKE ? OR
            u.email LIKE ? OR
            b.id LIKE ?
        )";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }
    
    // Get total count - simplified without subquery
    $countQuery = str_replace('SELECT ip.*,', 'SELECT COUNT(*) as total,', $query);
    $countQuery = preg_replace('/ ORDER BY.*$/', '', $countQuery); // Remove ORDER BY if present
    
    // Build simpler count query
    $countSql = "SELECT COUNT(*) as total FROM insurance_policies ip 
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        JOIN bookings b ON ip.booking_id = b.id
        JOIN users u ON ip.user_id = u.id
        WHERE 1=1";
    
    $countParams = [];
    $countTypes = '';
    
    if ($status !== 'all') {
        $countSql .= " AND ip.status = ?";
        $countParams[] = $status;
        $countTypes .= 's';
    }
    
    if (!empty($search)) {
        $countSql .= " AND (ip.policy_number LIKE ? OR u.fullname LIKE ? OR u.email LIKE ? OR b.id LIKE ?)";
        $searchTerm = "%$search%";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countTypes .= 'ssss';
    }
    
    $countStmt = $conn->prepare($countSql);
    if (!empty($countParams)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    
    // Add ordering and pagination
    $query .= " ORDER BY ip.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Execute main query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $policies = [];
    while ($row = $result->fetch_assoc()) {
        // Debug: Log vehicle data retrieval
        $vehicleName = $row['vehicle_name'];
        if (empty($vehicleName) || $vehicleName === 'Unknown Vehicle') {
            error_log("Vehicle data missing for policy {$row['policy_number']}: Type={$row['vehicle_type']}, ID={$row['vehicle_id']}");
        }
        
        $policies[] = [
            'policy_id' => $row['id'],
            'policy_number' => $row['policy_number'],
            'booking_id' => $row['booking_id'],
            'provider' => $row['provider_name'],
            'coverage_type' => $row['coverage_type'],
            'premium_amount' => floatval($row['premium_amount']),
            'coverage_limit' => floatval($row['coverage_limit']),
            'deductible' => floatval($row['deductible']),
            'policy_start' => $row['policy_start'],
            'policy_end' => $row['policy_end'],
            'status' => $row['status'],
            'is_expired' => (bool)$row['is_expired'],
            'days_remaining' => intval($row['days_remaining']),
            'renter' => [
                'name' => $row['renter_name'],
                'email' => $row['renter_email'],
                'contact' => $row['renter_contact']
            ],
            'owner_name' => $row['owner_name'],
            'vehicle' => [
                'type' => $row['vehicle_type'] ?? 'Unknown',
                'name' => $row['vehicle_name'] ?? 'Unknown Vehicle',
                // Debug info
                '_debug_vehicle_id' => $row['vehicle_id'] ?? null
            ],
            'booking' => [
                'status' => $row['booking_status'],
                'amount' => floatval($row['booking_amount']),
                'pickup_date' => $row['pickup_date'],
                'return_date' => $row['return_date']
            ],
            'issued_at' => $row['issued_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $policies,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
