<?php
session_start();
include "include/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['admin_id'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    $profilePicture = null;
    
    // Handle profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $targetDir = "uploads/profiles/";
        
        // Create directory if not exists
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $fileName = time() . "_" . basename($_FILES['profile_picture']['name']);
        $targetFile = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFile)) {
            $profilePicture = $targetFile;
        }
    }
    
    // Update query
    if ($profilePicture) {
        $sql = "UPDATE admin SET fullname = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $fullname, $email, $phone, $profilePicture, $adminId);
    } else {
        $sql = "UPDATE admin SET fullname = ?, email = ?, phone = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $fullname, $email, $phone, $adminId);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Profile updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update profile.";
    }
    
    header("Location: settings.php");
    exit();
}
?>