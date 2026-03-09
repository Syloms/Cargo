<?php
/**
 * User Registration API
 * Handles new user registration with proper error handling
 */

// Load configuration (handles error reporting based on environment)
require_once 'include/config.php';
require_once 'include/db.php';

// Set CORS headers
setCorsHeaders();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    
    // Validate required fields
    $requiredFields = ['fullname', 'email', 'password', 'phone', 'municipality', 'role'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        jsonError('Missing required fields: ' . implode(', ', $missingFields));
    }
    
    // Sanitize and validate input
    $fullname = trim($data["fullname"]);
    $email = trim(strtolower($data["email"]));
    $password = trim($data["password"]);
    $phone = trim($data["phone"]);
    $municipality = trim($data["municipality"]);
    $role = trim($data["role"]);
    
    // Validate phone number format (Philippine format: 09XXXXXXXXX)
    $phoneClean = preg_replace('/[\s\-]/', '', $phone);
    if (!preg_match('/^09\d{9}$/', $phoneClean)) {
        jsonError('Invalid phone number. Must be in format: 09XXXXXXXXX');
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email format');
    }
    
    // Validate role
    $allowedRoles = ['renter', 'owner', 'both'];
    if (!in_array($role, $allowedRoles)) {
        jsonError('Invalid role. Must be: renter, owner, or both');
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        jsonError('Password must be at least 6 characters long');
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        jsonError('Email already exists. Please use another email or login.');
    }
    $stmt->close();
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, phone, role, municipality, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ssssss", $fullname, $email, $password, $phoneClean, $role, $municipality);
    
    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        $stmt->close();
        
        // Log successful registration
        debug_log("New user registered", [
            'user_id' => $userId,
            'email' => $email,
            'role' => $role
        ]);
        
        jsonSuccess('User registered successfully', [
            'user_id' => $userId,
            'fullname' => $fullname,
            'email' => $email,
            'role' => $role
        ]);
        
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Registration failed: ' . $error);
    }
    
} catch (Exception $e) {
    // Log error for debugging
    debug_log("Registration error", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Return user-friendly error
    if (DEBUG_MODE) {
        jsonError('Server error: ' . $e->getMessage(), 500);
    } else {
        jsonError('Registration failed. Please try again later.', 500);
    }
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
