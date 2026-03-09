<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'include/db.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['email']) || !isset($data['fullname']) || !isset($data['role']) || !isset($data['municipality'])) {
        echo json_encode([
            "status" => "error",
            "message" => "Missing required fields"
        ]);
        exit;
    }
    
    $email = $conn->real_escape_string($data['email']);
    $fullname = $conn->real_escape_string($data['fullname']);
    $role = $conn->real_escape_string($data['role']);
    $municipality = $conn->real_escape_string($data['municipality']);
    $profile_image = isset($data['profile_image']) ? $conn->real_escape_string($data['profile_image']) : '';
    $phone = isset($data['phone']) ? $conn->real_escape_string($data['phone']) : '';
    $address = isset($data['address']) ? $conn->real_escape_string($data['address']) : '';
    $google_uid = isset($data['firebase_uid']) ? $conn->real_escape_string($data['firebase_uid']) : '';
    
    // Check if user exists
    $checkSql = "SELECT id FROM users WHERE email = '$email'";
    $checkResult = $conn->query($checkSql);
    
    if ($checkResult->num_rows > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "User already exists with this email"
        ]);
        exit;
    }
    
    // Generate random password
    $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (
                fullname, email, password, role, municipality, 
                profile_image, phone, address, google_uid, 
                auth_provider, created_at, last_login
            ) VALUES (
                '$fullname', '$email', '$randomPassword', '$role', '$municipality', 
                '$profile_image', '$phone', '$address', '$google_uid', 
                'google', NOW(), NOW()
            )";
    
    if ($conn->query($sql) === TRUE) {
        $userId = $conn->insert_id;
        
        $getUserSql = "SELECT u.id, u.fullname, u.email, u.role, u.municipality, 
                              u.profile_image, u.phone, u.address, u.google_uid, 
                              u.auth_provider, u.fcm_token,
                              CASE WHEN uv.status = 'approved' THEN 1 ELSE 0 END AS is_verified
                       FROM users u
                       LEFT JOIN user_verifications uv ON u.id = uv.user_id
                       WHERE u.id = $userId";
        
        $userResult = $conn->query($getUserSql);
        $user = $userResult->fetch_assoc();
        
        echo json_encode([
            "status" => "success",
            "message" => "User registered successfully",
            "user" => $user
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to register: " . $conn->error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>