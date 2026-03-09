<?php
/**
 * User Login API
 * Handles user authentication
 */

// Load configuration
require_once 'include/config.php';
require_once 'include/db.php';

// Set CORS headers
setCorsHeaders();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    // Read and validate JSON input
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($data["email"]) || !isset($data["password"])) {
        jsonError("Missing email or password");
    }
    
    // Trim and validate inputs
    $email = trim(strtolower($data["email"]));
    $password = trim($data["password"]);
    
    if (empty($email) || empty($password)) {
        jsonError("Email and password are required");
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError("Invalid email format");
    }
    
    // Fetch user from database with verification status
    $stmt = $conn->prepare("
        SELECT 
            u.id, u.fullname, u.email, u.phone, u.address, u.role, u.profile_image, 
            u.password, u.is_suspended, u.suspended_at, u.suspension_reason,
            CASE WHEN uv.status = 'approved' THEN 1 ELSE 0 END AS is_verified
        FROM users u
        LEFT JOIN user_verifications uv ON u.id = uv.user_id
        WHERE u.email = ? 
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        jsonError("Invalid email or password");
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();



    // Block suspended users
    if (!empty($row['is_suspended']) && intval($row['is_suspended']) === 1) {
        jsonError("Account suspended. Please contact support.");
    }

    // Compare password
    // Backwards compatible: support legacy plaintext + new hashed passwords
    $storedPassword = $row["password"];
    $isValid = false;

    // If stored password looks like a hash, verify it
    $hashInfo = password_get_info($storedPassword);
    if (!empty($hashInfo['algo'])) {
        $isValid = password_verify($password, $storedPassword);
    } else {
        // Legacy plaintext comparison
        $isValid = hash_equals($storedPassword, $password);

        // Optional: auto-upgrade legacy password to hash on successful login
        if ($isValid) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $u = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($u) {
                $u->bind_param('si', $newHash, $row['id']);
                $u->execute();
                $u->close();
                $storedPassword = $newHash;
            }
        }
    }

    if (!$isValid) {
        jsonError("Invalid email or password");
    }
    
    // CREATE TOKEN
    $token = base64_encode($row['id'] . '|' . time());
    $update = $conn->prepare("UPDATE users SET api_token = ?, last_login = NOW() WHERE id = ?");
    if (!$update) {
        throw new Exception('Token update failed: ' . $conn->error);
    }
    
    $update->bind_param("si", $token, $row['id']);
    $update->execute();
    $update->close();
    
    // Build profile image URL using config
    $profileImageUrl = "";
    if (!empty($row["profile_image"])) {
        // Check if it's already a full URL (Google, Facebook, etc.)
        if (filter_var($row["profile_image"], FILTER_VALIDATE_URL)) {
            // It's already a full URL, use it as-is
            $profileImageUrl = $row["profile_image"];
        } else {
            // It's a local file path, prepend the uploads URL
            $profileImageUrl = UPLOADS_URL . "/profile_images/" . $row["profile_image"];
        }
    }
    
    // Log successful login
    debug_log("User logged in", [
        'user_id' => $row['id'],
        'email' => $email,
        'role' => $row['role']
    ]);
    
    jsonSuccess("Login successful", [
        "id" => $row["id"],
        "fullname" => $row["fullname"],
        "email" => $row["email"],
        "phone" => $row["phone"] ?? "",
        "address" => $row["address"] ?? "",
        "role" => $row["role"],
        "token" => $token,
        "profile_image" => $profileImageUrl,
        "is_verified" => intval($row["is_verified"] ?? 0)
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    debug_log("Login error", [
        'error' => $e->getMessage(),
        'email' => $email ?? 'unknown'
    ]);
    
    // Return user-friendly error
    if (DEBUG_MODE) {
        jsonError('Server error: ' . $e->getMessage(), 500);
    } else {
        jsonError('Login failed. Please try again later.', 500);
    }
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
