<?php
/**
 * Get detailed policy information for modal view
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../include/db.php';

$policyId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$policyId) {
    echo json_encode(['success' => false, 'message' => 'Policy ID is required']);
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
            prov.contact_phone as provider_phone,
            prov.contact_email as provider_email,
            b.id as booking_id,
            b.pickup_date,
            b.return_date,
            b.total_amount as booking_amount,
            b.status as booking_status,
            u.id as renter_id,
            u.fullname AS renter_name,
            u.email as renter_email,
            u.phone as renter_contact,
            o.id as owner_id,
            o.fullname AS owner_name,
            o.email as owner_email,
            o.phone as owner_contact,
            COALESCE(
                CASE 
                    WHEN ip.vehicle_type = 'car' OR (ip.vehicle_type = '' AND b.vehicle_type = 'car') THEN c.brand
                    WHEN ip.vehicle_type = 'motorcycle' OR (ip.vehicle_type = '' AND b.vehicle_type = 'motorcycle') THEN m.brand
                END,
                CASE 
                    WHEN b.vehicle_type = 'car' THEN bc.brand
                    WHEN b.vehicle_type = 'motorcycle' THEN bm.brand
                END
            ) as vehicle_brand,
            COALESCE(
                CASE 
                    WHEN ip.vehicle_type = 'car' OR (ip.vehicle_type = '' AND b.vehicle_type = 'car') THEN c.model
                    WHEN ip.vehicle_type = 'motorcycle' OR (ip.vehicle_type = '' AND b.vehicle_type = 'motorcycle') THEN m.model
                END,
                CASE 
                    WHEN b.vehicle_type = 'car' THEN bc.model
                    WHEN b.vehicle_type = 'motorcycle' THEN bm.model
                END
            ) as vehicle_model,
            COALESCE(
                CASE 
                    WHEN ip.vehicle_type = 'car' OR (ip.vehicle_type = '' AND b.vehicle_type = 'car') THEN c.car_year
                    WHEN ip.vehicle_type = 'motorcycle' OR (ip.vehicle_type = '' AND b.vehicle_type = 'motorcycle') THEN m.motorcycle_year
                END,
                CASE 
                    WHEN b.vehicle_type = 'car' THEN bc.car_year
                    WHEN b.vehicle_type = 'motorcycle' THEN bm.motorcycle_year
                END
            ) as vehicle_year,
            COALESCE(
                CASE 
                    WHEN ip.vehicle_type = 'car' OR (ip.vehicle_type = '' AND b.vehicle_type = 'car') THEN c.plate_number
                    WHEN ip.vehicle_type = 'motorcycle' OR (ip.vehicle_type = '' AND b.vehicle_type = 'motorcycle') THEN m.plate_number
                END,
                CASE 
                    WHEN b.vehicle_type = 'car' THEN bc.plate_number
                    WHEN b.vehicle_type = 'motorcycle' THEN bm.plate_number
                END
            ) as vehicle_plate,
            DATEDIFF(ip.policy_end, NOW()) AS days_remaining,
            CASE 
                WHEN NOW() > ip.policy_end THEN 1
                ELSE 0
            END AS is_expired,
            (SELECT COUNT(*) FROM insurance_claims WHERE policy_id = ip.id) as total_claims,
            (SELECT COUNT(*) FROM insurance_claims WHERE policy_id = ip.id AND status = 'approved') as approved_claims,
            (SELECT SUM(approved_amount) FROM insurance_claims WHERE policy_id = ip.id AND status = 'approved') as total_claimed_amount
        FROM insurance_policies ip
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        JOIN bookings b ON ip.booking_id = b.id
        JOIN users u ON ip.user_id = u.id
        JOIN users o ON ip.owner_id = o.id
        LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
        LEFT JOIN cars bc ON b.car_id = bc.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles bm ON b.car_id = bm.id AND b.vehicle_type = 'motorcycle'
        WHERE ip.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $policyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Policy not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    
    $policy = [
        'id' => $row['id'],
        'policy_number' => $row['policy_number'],
        'coverage_type' => $row['coverage_type'],
        'premium_amount' => floatval($row['premium_amount']),
        'coverage_limit' => floatval($row['coverage_limit']),
        'deductible' => floatval($row['deductible']),
        'policy_start' => $row['policy_start'],
        'policy_end' => $row['policy_end'],
        'status' => $row['status'],
        'is_expired' => (bool)$row['is_expired'],
        'days_remaining' => intval($row['days_remaining']),
        'issued_at' => $row['issued_at'],
        'provider' => [
            'name' => $row['provider_name'],
            'phone' => $row['provider_phone'],
            'email' => $row['provider_email']
        ],
        'renter' => [
            'id' => $row['renter_id'],
            'name' => $row['renter_name'],
            'email' => $row['renter_email'],
            'contact' => $row['renter_contact']
        ],
        'owner' => [
            'id' => $row['owner_id'],
            'name' => $row['owner_name'],
            'email' => $row['owner_email'],
            'contact' => $row['owner_contact']
        ],
        'vehicle' => [
            'type' => $row['vehicle_type'],
            'brand' => $row['vehicle_brand'],
            'model' => $row['vehicle_model'],
            'year' => $row['vehicle_year'],
            'plate' => $row['vehicle_plate']
        ],
        'booking' => [
            'id' => $row['booking_id'],
            'status' => $row['booking_status'],
            'amount' => floatval($row['booking_amount']),
            'pickup_date' => $row['pickup_date'],
            'return_date' => $row['return_date']
        ],
        'claims_summary' => [
            'total_claims' => intval($row['total_claims']),
            'approved_claims' => intval($row['approved_claims']),
            'total_claimed_amount' => floatval($row['total_claimed_amount'] ?? 0)
        ]
    ];
    
    echo json_encode(['success' => true, 'data' => $policy]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
