<?php
/**
 * Get Insurance Policies for Vehicle Owner
 * Returns all insurance policies for bookings where the user is the vehicle owner
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$ownerId = isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0;

if ($ownerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid owner_id is required']);
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

// Optional filters
$status = $_GET['status'] ?? 'all'; // all, active, expired, claimed, cancelled
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = ($page - 1) * $limit;

try {
    // Build query to get all policies where user is the vehicle owner
    $query = "
        SELECT 
            ip.*,
            prov.provider_name,
            prov.contact_email as provider_email,
            prov.contact_phone as provider_phone,
            b.id as booking_id,
            b.pickup_date,
            b.return_date,
            b.total_amount as booking_amount,
            b.status as booking_status,
            b.vehicle_type,
            u.fullname AS renter_name,
            u.email as renter_email,
            u.phone as renter_contact,
            CASE 
                WHEN ip.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model, ' (', c.car_year, ')')
                WHEN ip.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model, ' (', m.motorcycle_year, ')')
                ELSE 'Unknown Vehicle'
            END AS vehicle_name,
            CASE 
                WHEN ip.vehicle_type = 'car' THEN c.id
                WHEN ip.vehicle_type = 'motorcycle' THEN m.id
                ELSE NULL
            END AS vehicle_id,
            DATEDIFF(ip.policy_end, NOW()) AS days_remaining,
            CASE 
                WHEN NOW() > ip.policy_end THEN 1
                ELSE 0
            END AS is_expired
        FROM insurance_policies ip
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        JOIN bookings b ON ip.booking_id = b.id
        JOIN users u ON ip.user_id = u.id
        LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
        WHERE ip.owner_id = ?
    ";
    
    $params = [$ownerId];
    $types = 'i';
    
    // Filter by status
    if ($status === 'active') {
        $query .= " AND ip.status = 'active'";
    } elseif ($status === 'expired') {
        $query .= " AND ip.status = 'expired'";
    } elseif ($status === 'claimed') {
        $query .= " AND ip.status = 'claimed'";
    } elseif ($status === 'cancelled') {
        $query .= " AND ip.status = 'cancelled'";
    }
    // 'all' means no additional status filter
    
    // Search filter
    if (!empty($search)) {
        $query .= " AND (
            ip.policy_number LIKE ? OR
            u.fullname LIKE ? OR
            u.email LIKE ? OR
            b.id LIKE ? OR
            c.brand LIKE ? OR
            c.model LIKE ? OR
            m.brand LIKE ? OR
            m.model LIKE ?
        )";
        $searchTerm = "%$search%";
        for ($i = 0; $i < 8; $i++) {
            $params[] = $searchTerm;
            $types .= 's';
        }
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM insurance_policies ip 
        JOIN bookings b ON ip.booking_id = b.id
        JOIN users u ON ip.user_id = u.id
        LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
        WHERE ip.owner_id = ?";
    
    $countParams = [$ownerId];
    $countTypes = 'i';
    
    if ($status === 'active') {
        $countSql .= " AND ip.status = 'active'";
    } elseif ($status === 'expired') {
        $countSql .= " AND ip.status = 'expired'";
    } elseif ($status === 'claimed') {
        $countSql .= " AND ip.status = 'claimed'";
    } elseif ($status === 'cancelled') {
        $countSql .= " AND ip.status = 'cancelled'";
    }
    
    if (!empty($search)) {
        $countSql .= " AND (ip.policy_number LIKE ? OR u.fullname LIKE ? OR u.email LIKE ? OR b.id LIKE ? OR c.brand LIKE ? OR c.model LIKE ? OR m.brand LIKE ? OR m.model LIKE ?)";
        $searchTerm = "%$search%";
        for ($i = 0; $i < 8; $i++) {
            $countParams[] = $searchTerm;
            $countTypes .= 's';
        }
    }
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($countTypes, ...$countParams);
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    
    // Add ordering and pagination
    $query .= " ORDER BY ip.policy_start DESC, ip.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Execute main query
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $policies = [];
    while ($row = $result->fetch_assoc()) {
        $policies[] = [
            'policy_id' => intval($row['id']),
            'policy_number' => $row['policy_number'],
            'booking_id' => intval($row['booking_id']),
            'vehicle_id' => $row['vehicle_id'] ? intval($row['vehicle_id']) : null,
            'vehicle_type' => $row['vehicle_type'],
            'vehicle_name' => $row['vehicle_name'],
            'provider' => [
                'name' => $row['provider_name'],
                'email' => $row['provider_email'],
                'phone' => $row['provider_phone']
            ],
            'coverage' => [
                'type' => $row['coverage_type'],
                'limit' => floatval($row['coverage_limit']),
                'deductible' => floatval($row['deductible']),
                'collision' => floatval($row['collision_coverage']),
                'liability' => floatval($row['liability_coverage']),
                'theft' => floatval($row['theft_coverage']),
                'personal_injury' => floatval($row['personal_injury_coverage']),
                'roadside_assistance' => (bool)$row['roadside_assistance']
            ],
            'premium_amount' => floatval($row['premium_amount']),
            'policy_start' => $row['policy_start'],
            'policy_end' => $row['policy_end'],
            'status' => $row['status'],
            'is_expired' => (bool)$row['is_expired'],
            'days_remaining' => max(0, intval($row['days_remaining'])),
            'renter' => [
                'name' => $row['renter_name'],
                'email' => $row['renter_email'],
                'contact' => $row['renter_contact']
            ],
            'booking' => [
                'status' => $row['booking_status'],
                'amount' => floatval($row['booking_amount']),
                'pickup_date' => $row['pickup_date'],
                'return_date' => $row['return_date']
            ],
            'terms_accepted' => (bool)$row['terms_accepted'],
            'issued_at' => $row['issued_at'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_policies,
            SUM(CASE WHEN status = 'active' AND NOW() <= policy_end THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'expired' OR NOW() > policy_end THEN 1 ELSE 0 END) as expired_count,
            SUM(CASE WHEN status = 'claimed' THEN 1 ELSE 0 END) as claimed_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            SUM(premium_amount) as total_premiums
        FROM insurance_policies
        WHERE owner_id = ?
    ";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param("i", $ownerId);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => $policies,
        'statistics' => [
            'total_policies' => intval($stats['total_policies']),
            'active_count' => intval($stats['active_count']),
            'expired_count' => intval($stats['expired_count']),
            'claimed_count' => intval($stats['claimed_count']),
            'cancelled_count' => intval($stats['cancelled_count']),
            'total_premiums' => floatval($stats['total_premiums'])
        ],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'PHP Error: ' . $e->getMessage(), 
        'file' => $e->getFile(), 
        'line' => $e->getLine()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
