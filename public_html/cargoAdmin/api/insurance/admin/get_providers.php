<?php
/**
 * Admin: Get All Insurance Providers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../../include/db.php';

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
$offset = ($page - 1) * $limit;

try {
    // Count total
    $countResult = $conn->query("SELECT COUNT(*) as total FROM insurance_providers");
    $totalCount = $countResult->fetch_assoc()['total'];
    
    $query = "
        SELECT 
            id,
            provider_name,
            contact_phone,
            contact_email,
            status,
            created_at,
            CASE WHEN status = 'active' THEN 1 ELSE 0 END as is_active
        FROM insurance_providers
        ORDER BY provider_name ASC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $providers = [];
    while ($row = $result->fetch_assoc()) {
        $providers[] = [
            'id' => $row['id'],
            'provider_name' => $row['provider_name'],
            'contact_phone' => $row['contact_phone'] ?? 'N/A',
            'contact_email' => $row['contact_email'] ?? 'N/A',
            'status' => $row['status'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $providers,
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
        'message' => $e->getMessage()
    ]);
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
