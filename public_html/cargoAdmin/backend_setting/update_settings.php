<?php
session_start();
include "include/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['admin_id'];
    
    $emailNotif = isset($_POST['email_notifications']) ? 1 : 0;
    $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
    
    $sql = "UPDATE admin SET email_notifications = ?, maintenance_mode = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $emailNotif, $maintenanceMode, $adminId);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Settings saved successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to save settings.";
    }
    
    header("Location: settings.php");
    exit();
}
?>