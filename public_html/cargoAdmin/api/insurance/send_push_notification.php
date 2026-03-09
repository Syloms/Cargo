<?php
/**
 * Send Push Notifications for Insurance Events
 * Uses Firebase Cloud Messaging (FCM)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../include/db.php';

// Firebase Server Key (Get from Firebase Console)
$serverKey = 'YOUR_FIREBASE_SERVER_KEY'; // Replace with your actual key

function sendFCMNotification($fcmToken, $title, $body, $data = []) {
    global $serverKey;
    
    $notification = [
        'title' => $title,
        'body' => $body,
        'sound' => 'default',
        'badge' => '1',
        'icon' => 'insurance_icon',
        'color' => '#FF9800'
    ];
    
    $payload = [
        'to' => $fcmToken,
        'notification' => $notification,
        'data' => $data,
        'priority' => 'high'
    ];
    
    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode == 200,
        'response' => json_decode($result, true)
    ];
}

function getUserFCMToken($userId) {
    global $conn;
    
    $query = "SELECT fcm_token FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['fcm_token'];
    }
    
    return null;
}

// API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? 0;
    $type = $input['type'] ?? '';
    $policyData = $input['policy_data'] ?? [];
    
    $fcmToken = getUserFCMToken($userId);
    
    if (!$fcmToken) {
        echo json_encode(['success' => false, 'message' => 'No FCM token found for user']);
        exit;
    }
    
    $title = '';
    $body = '';
    $data = [
        'type' => 'insurance_notification',
        'policy_id' => $policyData['policy_id'] ?? null
    ];
    
    switch ($type) {
        case 'policy_created':
            $title = '🛡️ Insurance Policy Created';
            $body = "Policy {$policyData['policy_number']} for {$policyData['vehicle_name']} is now active.";
            break;
            
        case 'policy_expiring':
            $title = '⚠️ Policy Expiring Soon';
            $body = "Your insurance for {$policyData['vehicle_name']} expires in {$policyData['days_remaining']} days.";
            break;
            
        case 'claim_filed':
            $title = '🚨 Insurance Claim Filed';
            $body = "A claim has been filed for policy {$policyData['policy_number']}.";
            break;
            
        case 'claim_approved':
            $title = '✅ Claim Approved';
            $body = "Your insurance claim for {$policyData['policy_number']} has been approved.";
            break;
            
        case 'claim_rejected':
            $title = '❌ Claim Rejected';
            $body = "Your insurance claim for {$policyData['policy_number']} was rejected.";
            break;
    }
    
    $result = sendFCMNotification($fcmToken, $title, $body, $data);
    
    // Log notification
    if ($result['success']) {
        $logQuery = "INSERT INTO notification_logs (user_id, type, title, body, sent_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($logQuery);
        $stmt->bind_param("isss", $userId, $type, $title, $body);
        $stmt->execute();
    }
    
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
