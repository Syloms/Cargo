<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// ✅ Use centralized config
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';

if (!isset($conn) || $conn === null) {
    http_response_code(500);
    echo json_encode([
        "is_verified" => false,
        "can_add_car" => false,
        "message" => "Database connection failed"
    ]);
    exit();
}

// Support both GET and POST
$user_id = isset($_GET['user_id']) ? trim($_GET['user_id']) :
            (isset($_POST['user_id']) ? trim($_POST['user_id']) : '');

if (empty($user_id)) {
    http_response_code(400);
    echo json_encode([
        "is_verified" => false,
        "can_add_car" => false,
        "message" => "User ID is required"
    ]);
    exit();
}

try {
    // Check for approved verification (using mysqli)
    $query = "SELECT status, verified_at
               FROM user_verifications
               WHERE user_id = ?
               AND status = 'approved'
              LIMIT 1";
        
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $result = $result->fetch_assoc();
        
    if ($result) {
        // User is VERIFIED - can add cars
        http_response_code(200);
        echo json_encode([
            "is_verified" => true,
            "can_add_car" => true,
            "message" => "Account verified",
            "verified_at" => $result['verified_at']
        ]);
    } else {
        // Check if user has ANY verification record
        $anyQuery = "SELECT status FROM user_verifications
                      WHERE user_id = ?
                      ORDER BY created_at DESC
                      LIMIT 1";
        $anyStmt = $conn->prepare($anyQuery);
        $anyStmt->bind_param('i', $user_id);
        $anyStmt->execute();
        $anyResultSet = $anyStmt->get_result();
        $anyResult = $anyResultSet->fetch_assoc();
                
        if ($anyResult) {
            $status = $anyResult['status'];
                        
            if ($status == 'pending') {
                http_response_code(200);
                echo json_encode([
                    "is_verified" => false,
                    "can_add_car" => false,
                    "message" => "Your verification is pending approval"
                ]);
            } else if ($status == 'rejected') {
                http_response_code(200);
                echo json_encode([
                    "is_verified" => false,
                    "can_add_car" => false,
                    "message" => "Your verification was rejected. Please submit again."
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    "is_verified" => false,
                    "can_add_car" => false,
                    "message" => "Unknown verification status"
                ]);
            }
        } else {
            // No verification record at all
            http_response_code(200);
            echo json_encode([
                "is_verified" => false,
                "can_add_car" => false,
                "message" => "Please complete your identity verification to add cars"
            ]);
        }
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        "is_verified" => false,
        "can_add_car" => false,
        "message" => "Database error occurred: " . (DEBUG_MODE ? $e->getMessage() : "")
    ]);
}

// Connection is managed by db.php, no need to close here
?>