<?php
session_start();
include "include/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['admin_id'];
    $currentPass = $_POST['current_password'];
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];
    
    // Get current password from database
    $query = $conn->query("SELECT password FROM admin WHERE id = $adminId");
    $admin = $query->fetch_assoc();
    
    // Verify current password
    if (password_verify($currentPass, $admin['password'])) {
        // Check if passwords match and meet requirements
        if ($newPass === $confirmPass && strlen($newPass) >= 8) {
            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
            $conn->query("UPDATE admin SET password = '$hashedPass' WHERE id = $adminId");
            $_SESSION['success_message'] = "Password changed successfully!";
        } else {
            $_SESSION['error_message'] = "Passwords do not match or too short (minimum 8 characters).";
        }
    } else {
        $_SESSION['error_message'] = "Current password is incorrect.";
    }
    
    header("Location: settings.php");
    exit();
}
?>