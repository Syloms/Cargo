<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'include/db.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['email'])) {
        echo json_encode([
            "exists" => false, 
            "message" => "Email is required"
        ]);
        exit;
    }
    
    $email = $conn->real_escape_string($data['email']);
    
    $sql = "SELECT 
                u.id, 
                u.fullname, 
                u.email, 
                u.role, 
                u.municipality, 
                u.profile_image, 
                u.phone, 
                u.address,
                u.google_uid,
                u.auth_provider,
                u.fcm_token,
                u.gcash_number,
                u.gcash_name,
                u.is_suspended,
                u.suspended_at,
                u.suspension_reason,
                CASE WHEN uv.status = 'approved' THEN 1 ELSE 0 END AS is_verified
            FROM users u
            LEFT JOIN user_verifications uv ON u.id = uv.user_id
            WHERE u.email = '$email' 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Update last_login
        $updateSql = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
        $conn->query($updateSql);
        
        if (!empty($user['is_suspended']) && intval($user['is_suspended']) === 1) {
            echo json_encode([
                "exists" => true,
                "suspended" => true,
                "message" => "Account suspended. Please contact support.",
                "user" => $user
            ]);
            exit;
        }

        echo json_encode([
            "exists" => true,
            "user" => $user
        ]);
    } else {
        echo json_encode([
            "exists" => false,
            "message" => "User not found"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        "exists" => false,
        "error" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>