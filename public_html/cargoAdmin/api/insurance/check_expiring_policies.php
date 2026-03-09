<?php
/**
 * Check for Expiring Insurance Policies
 * Cron job to send notifications for policies expiring soon
 * Run this daily via cron: 0 9 * * * php /path/to/check_expiring_policies.php
 */

require_once __DIR__ . '/../../include/db.php';

// Find policies expiring in 3 days
$query = "
    SELECT 
        ip.id,
        ip.policy_number,
        ip.policy_end,
        ip.premium_amount,
        ip.coverage_type,
        ip.coverage_limit,
        DATEDIFF(ip.policy_end, NOW()) as days_remaining,
        u.id as owner_id,
        u.fullname as owner_name,
        u.email as owner_email,
        CASE 
            WHEN ip.vehicle_type = 'car' THEN CONCAT(c.brand, ' ', c.model, ' (', c.car_year, ')')
            WHEN ip.vehicle_type = 'motorcycle' THEN CONCAT(m.brand, ' ', m.model, ' (', m.motorcycle_year, ')')
        END as vehicle_name
    FROM insurance_policies ip
    JOIN users u ON ip.owner_id = u.id
    LEFT JOIN cars c ON ip.vehicle_id = c.id AND ip.vehicle_type = 'car'
    LEFT JOIN motorcycles m ON ip.vehicle_id = m.id AND ip.vehicle_type = 'motorcycle'
    WHERE ip.status = 'active'
    AND DATEDIFF(ip.policy_end, NOW()) <= 3
    AND DATEDIFF(ip.policy_end, NOW()) > 0
    AND ip.expiry_notification_sent = 0
";

$result = $conn->query($query);

$notificationsSent = 0;
$errors = 0;

if ($result && $result->num_rows > 0) {
    while ($policy = $result->fetch_assoc()) {
        // Prepare notification data
        $notificationData = [
            'type' => 'policy_expiring',
            'policy_data' => [
                'policy_number' => $policy['policy_number'],
                'owner_name' => $policy['owner_name'],
                'owner_email' => $policy['owner_email'],
                'vehicle_name' => $policy['vehicle_name'],
                'policy_end' => $policy['policy_end'],
                'days_remaining' => $policy['days_remaining']
            ]
        ];
        
        // Send email notification
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://cargoph.online/cargoAdmin/api/insurance/send_policy_notification.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            // Mark notification as sent
            $updateQuery = "UPDATE insurance_policies SET expiry_notification_sent = 1 WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("i", $policy['id']);
            $stmt->execute();
            
            $notificationsSent++;
            echo "✓ Sent expiry notification for policy {$policy['policy_number']}\n";
        } else {
            $errors++;
            echo "✗ Failed to send notification for policy {$policy['policy_number']}\n";
        }
    }
}

echo "\n";
echo "Summary:\n";
echo "- Notifications sent: $notificationsSent\n";
echo "- Errors: $errors\n";

$conn->close();
?>
