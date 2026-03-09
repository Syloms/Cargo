<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
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
    $firebase_uid = isset($data['firebase_uid']) ? $conn->real_escape_string($data['firebase_uid']) : '';
    $facebook_id = isset($data['facebook_id']) ? $conn->real_escape_string($data['facebook_id']) : '';
    $phone = isset($data['phone']) ? $conn->real_escape_string($data['phone']) : '';
    $address = isset($data['address']) ? $conn->real_escape_string($data['address']) : '';

    // Check if exists
    $checkSql = "SELECT id FROM users WHERE email = '$email'";
    $checkResult = $conn->query($checkSql);

    if ($checkResult->num_rows > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "User already exists"
        ]);
        exit;
    }

    $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (
        fullname, email, password, role, municipality, profile_image, 
        facebook_id, auth_provider, phone, address, created_at, last_login
    ) VALUES (
        '$fullname', '$email', '$randomPassword', '$role', '$municipality', 
        '$profile_image', '$facebook_id', 'facebook', '$phone', '$address', 
        NOW(), NOW()
    )";

    if ($conn->query($sql) === TRUE) {
        $user_id = $conn->insert_id;
        
        $getUserSql = "SELECT id, fullname, email, role, municipality, 
                              profile_image, phone, address, facebook_id, 
                              auth_provider, fcm_token
                       FROM users WHERE id = $user_id";
        
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
            "message" => "Registration failed: " . $conn->error
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
