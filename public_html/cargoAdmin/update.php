<?php

// --- ERROR LOGGING (Recommended in development only) ---
// Load configuration (handles error reporting based on environment)
require_once __DIR__ . '/include/config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Handle preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;

include "include/db.php";

// ---------- READ INPUT SAFE ----------
$user_id  = isset($_POST["user_id"]) ? intval($_POST["user_id"]) : 0;
$fullname = trim($_POST["fullname"] ?? "");
$phone    = trim($_POST["phone"] ?? "");
$address  = trim($_POST["address"] ?? "");

if ($user_id <= 0 || empty($fullname)) {
    response(false, "Missing required fields", $_POST);
}

// ---------- GET EXISTING DATA ----------
$stmt = $conn->prepare("SELECT profile_image, email FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData) response(false, "User not found");

$existingImage = $userData['profile_image'] ?? "";
$profile_image = $existingImage;

// ---------- UPLOADS DIRECTORY ----------
$uploadDir = "uploads/profile_images/";

if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_writable($uploadDir)) chmod($uploadDir, 0777);

// ---------- HANDLE IMAGE UPLOAD ----------
if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {

    $tmp = $_FILES['profile_image']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($ext, $allowed)) {
        response(false, "Invalid image format: jpg, jpeg, png, gif allowed.");
    }

    $newFileName = "user_" . $user_id . "_" . time() . "." . $ext;

    if (move_uploaded_file($tmp, $uploadDir . $newFileName)) {

        // Delete previous image
        if (!empty($existingImage) && file_exists($uploadDir . $existingImage)) {
            @unlink($uploadDir . $existingImage);
        }

        $profile_image = $newFileName;

    } else response(false, "Failed to upload image.");
}

// ---------- UPDATE DATABASE ----------
$stmt = $conn->prepare("UPDATE users SET fullname=?, phone=?, address=?, profile_image=? WHERE id=?");
$stmt->bind_param("ssssi", $fullname, $phone, $address, $profile_image, $user_id);

if (!$stmt->execute()) {
    response(false, "Database error: " . $stmt->error);
}

$stmt->close();

// ---------- BUILD FINAL USER RESPONSE ----------
$user = [
    "id" => $user_id,
    "fullname" => $fullname,
    "email" => $userData["email"],
    "phone" => $phone,
    "address" => $address,
    "profile_image" => !empty($profile_image)
        ? BASE_URL . "/$uploadDir$profile_image"
        : ""
];

// ---------- SEND SUCCESS ----------
response(true, "Profile updated successfully", $user);


// ---------- REUSABLE RESPONSE FUNCTION ----------
function response($success, $message, $user = null) {
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "user"    => $user
    ]);

    exit;
}

$conn->close();
?>
