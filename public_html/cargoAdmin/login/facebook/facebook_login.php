<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include 'include/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$facebook_id = $data['facebook_id'] ?? '';
$email = $data['email'] ?? '';
$name = $data['name'] ?? '';
$profile_picture = $data['profile_picture'] ?? '';

if (empty($facebook_id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Facebook ID is required"
    ]);
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR facebook_id = ?");
$stmt->bind_param("ss", $email, $facebook_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists - login
    $user = $result->fetch_assoc();
    
    // Update Facebook ID and profile picture if not set
    if (empty($user['facebook_id']) || $user['facebook_id'] !== $facebook_id) {
        $update = $conn->prepare("UPDATE users SET facebook_id = ?, profile_image = ? WHERE id = ?");
        $update->bind_param("ssi", $facebook_id, $profile_picture, $user['id']);
        $update->execute();
        $update->close();
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "id" => $user['id'],
        "fullname" => $user['fullname'],
        "email" => $user['email'],
        "role" => $user['role'],
        "phone" => $user['phone'] ?? "",
        "address" => $user['address'] ?? "",
        "profile_image" => !empty($profile_picture) ? $profile_picture : ($user['profile_image'] ?? "")
    ]);
} else {
    // New user - register
    $role = "Renter";
    $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $municipality = "";
    
    $insert_stmt = $conn->prepare("INSERT INTO users (fullname, email, facebook_id, role, profile_image, password, municipality) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("sssssss", $name, $email, $facebook_id, $role, $profile_picture, $dummy_password, $municipality);
    
    if ($insert_stmt->execute()) {
        // Get the last inserted ID using mysqli's insert_id property
        $user_id = $insert_stmt->insert_id;
        
        echo json_encode([
            "status" => "success",
            "message" => "Account created successfully",
            "id" => $user_id,
            "fullname" => $name,
            "email" => $email,
            "role" => $role,
            "phone" => "",
            "address" => "",
            "profile_image" => $profile_picture
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Registration failed: " . $insert_stmt->error
        ]);
    }
    
    $insert_stmt->close();
}

$stmt->close();
$conn->close();
?>