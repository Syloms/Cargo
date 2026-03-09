<?php
/**
 * Get Insurance Policy Details
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once __DIR__ . '/../../include/db.php';
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

$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$policyId = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0;
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (($bookingId <= 0 && $policyId <= 0) || $userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
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

try {
    $query = "
        SELECT 
            ip.*,
            prov.provider_name,
            prov.contact_email as provider_email,
            prov.contact_phone as provider_phone,
            b.pickup_date,
            b.return_date,
            b.total_amount as booking_amount,
            b.vehicle_type,
            u.fullname AS renter_name,
            u.email as renter_email,
            u.phone as renter_contact
        FROM insurance_policies ip
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        JOIN bookings b ON ip.booking_id = b.id
        JOIN users u ON ip.user_id = u.id
        WHERE (ip.booking_id = ? OR ip.id = ?)
        AND ip.user_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $bookingId, $policyId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Policy not found']);
        exit;
    }
    
    $policy = $result->fetch_assoc();
    
    // Calculate days remaining
    $policyEnd = new DateTime($policy['policy_end']);
    $now = new DateTime();
    $daysRemaining = $now->diff($policyEnd)->days;
    
    // Check if expired
    $isExpired = ($now > $policyEnd);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'policy_id' => $policy['id'],
            'policy_number' => $policy['policy_number'],
            'booking_id' => $policy['booking_id'],
            'provider' => [
                'name' => $policy['provider_name'],
                'email' => $policy['provider_email'],
                'phone' => $policy['provider_phone']
            ],
            'coverage' => [
                'type' => $policy['coverage_type'],
                'limit' => floatval($policy['coverage_limit']),
                'deductible' => floatval($policy['deductible']),
                'collision' => floatval($policy['collision_coverage']),
                'liability' => floatval($policy['liability_coverage']),
                'theft' => floatval($policy['theft_coverage']),
                'personal_injury' => floatval($policy['personal_injury_coverage']),
                'roadside_assistance' => (bool)$policy['roadside_assistance']
            ],
            'premium_amount' => floatval($policy['premium_amount']),
            'policy_start' => $policy['policy_start'],
            'policy_end' => $policy['policy_end'],
            'status' => $policy['status'],
            'is_expired' => $isExpired,
            'days_remaining' => $isExpired ? 0 : $daysRemaining,
            'renter' => [
                'name' => $policy['renter_name'],
                'email' => $policy['renter_email'],
                'contact' => $policy['renter_contact']
            ],
            'vehicle_type' => $policy['vehicle_type'],
            'terms_accepted' => (bool)$policy['terms_accepted'],
            'issued_at' => $policy['issued_at']
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
