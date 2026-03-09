<?php
$startTime = microtime(true);

header("Content-Type: application/json");
include "../include/db.php";
require_once __DIR__ . "/security/suspension_guard.php";

// ----------------------------
// INPUT
// ----------------------------
$user_id  = $_POST["user_id"] ?? "";
$fullname = $_POST["fullname"] ?? "";
$phone    = $_POST["phone"] ?? "";
$address = $_POST["address"] ?? "";
$gcash_number = $_POST["gcash_number"] ?? "";
$gcash_name = $_POST["gcash_name"] ?? "";

error_log("📥 Update profile request for user: $user_id");

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "Missing user_id"]);
    exit;
}

$checkTime = microtime(true);
// Block suspended users
require_not_suspended($conn, intval($user_id));
error_log("⏱️ Suspension check took: " . round((microtime(true) - $checkTime) * 1000, 2) . "ms");

$profile_image_name = null;

// ----------------------------
// FILE UPLOAD
// ----------------------------
$uploadTime = microtime(true);
if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] === 0) {
    error_log("📤 Processing image upload...");

    // Absolute, reliable path - use profile_images subdirectory
    $uploadDir = __DIR__ . "/../uploads/profile_images/";

    // Create folder if missing
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Permission check
    if (!is_writable($uploadDir)) {
        echo json_encode(["success" => false, "message" => "Uploads folder not writable"]);
        exit;
    }

    // Validate file type
    $ext = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "webp", "gif"];

    if (!in_array($ext, $allowed)) {
        echo json_encode(["success" => false, "message" => "Invalid image type. Allowed: jpg, jpeg, png, webp, gif"]);
        exit;
    }

    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if ($_FILES["profile_image"]["size"] > $maxSize) {
        echo json_encode(["success" => false, "message" => "Image too large. Maximum size is 5MB"]);
        exit;
    }

    // Unique filename (no caching issues, no overwrite)
    $newName = "user_" . $user_id . "_" . round(microtime(true) * 1000) . "." . $ext;
    $uploadPath = $uploadDir . $newName;

    if (!move_uploaded_file($_FILES["profile_image"]["tmp_name"], $uploadPath)) {
        error_log("Failed to move uploaded file to: " . $uploadPath);
        echo json_encode(["success" => false, "message" => "File upload failed. Check server permissions."]);
        exit;
    }

    $profile_image_name = $newName;
    error_log("✅ Profile image uploaded successfully: " . $newName);
    error_log("⏱️ Image upload took: " . round((microtime(true) - $uploadTime) * 1000, 2) . "ms");
} else if (isset($_FILES["profile_image"])) {
    error_log("⚠️ No image uploaded or error occurred: " . $_FILES["profile_image"]["error"]);
}

// ----------------------------
// DATABASE UPDATE
// ----------------------------
$dbTime = microtime(true);
error_log("💾 Updating database...");

if ($profile_image_name) {
    $stmt = $conn->prepare("
        UPDATE users
        SET fullname = ?, phone = ?, address = ?, profile_image = ?, gcash_number = ?, gcash_name = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssssi", $fullname, $phone, $address, $profile_image_name, $gcash_number, $gcash_name, $user_id);
} else {
    $stmt = $conn->prepare("
        UPDATE users
        SET fullname = ?, phone = ?, address = ?, gcash_number = ?, gcash_name = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $fullname, $phone, $address, $gcash_number, $gcash_name, $user_id);
}

if (!$stmt->execute()) {
    error_log("❌ Database update failed: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Database update failed"]);
    exit;
}

error_log("⏱️ Database update took: " . round((microtime(true) - $dbTime) * 1000, 2) . "ms");

// ----------------------------
// RETURN UPDATED USER
// ----------------------------
$getUser = $conn->prepare("
    SELECT fullname, phone, address, profile_image, gcash_number, gcash_name
    FROM users WHERE id = ?
");
$getUser->bind_param("i", $user_id);
$getUser->execute();
$user = $getUser->get_result()->fetch_assoc();

// Fix profile_image URL - don't prepend if it's already a full URL
if (!empty($user['profile_image'])) {
    // Check if it's already a full URL (Google, Facebook, etc.)
    if (filter_var($user['profile_image'], FILTER_VALIDATE_URL)) {
        // Already a full URL, leave it as-is
        // Do nothing
    } else {
        // It's a local filename, prepend the uploads path
        // Check if config is already loaded
        if (!defined('UPLOADS_URL')) {
            require_once __DIR__ . '/../include/config.php';
        }
        $user['profile_image'] = UPLOADS_URL . '/profile_images/' . $user['profile_image'];
    }
}

$totalTime = round((microtime(true) - $startTime) * 1000, 2);
error_log("✅ Profile update complete! Total time: {$totalTime}ms");

echo json_encode([
    "success" => true,
    "user" => $user,
    "debug" => [
        "execution_time_ms" => $totalTime
    ]
]);

$conn->close();
?>
