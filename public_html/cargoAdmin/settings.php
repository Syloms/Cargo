<?php
session_start();
include "include/db.php";

$adminId = $_SESSION['admin_id'] ?? 1;

/* =========================
   FETCH ADMIN
========================= */
$stmt = $conn->prepare("SELECT * FROM admin WHERE id=?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

/* =========================
   UPDATE PROFILE + IMAGE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);

    $imagePath = $admin['profile_image'];

    if (!empty($_FILES['profile_picture']['name'])) {

        $uploadDir = "uploads/admin/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png'];

        if (!in_array($ext, $allowed)) {
            $_SESSION['error_message'] = "Only JPG and PNG images are allowed.";
            header("Location: settings.php");
            exit;
        }

        $fileName = "admin_" . $adminId . "_" . time() . "." . $ext;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            $imagePath = $targetPath;
        }
    }

    $stmt = $conn->prepare("
        UPDATE admin 
        SET fullname=?, email=?, phone=?, profile_image=?
        WHERE id=?
    ");
    $stmt->bind_param("ssssi", $fullname, $email, $phone, $imagePath, $adminId);

    $_SESSION['success_message'] = $stmt->execute()
        ? "Profile updated successfully."
        : "Profile update failed.";

    header("Location: settings.php");
    exit;
}

/* =========================
   CHANGE PASSWORD
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {

    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($current !== $admin['password']) {
    $_SESSION['error_message'] = "Current password is incorrect.";
    header("Location: settings.php");
    exit;
}

    if ($new !== $confirm) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: settings.php"); exit;
    }

    if (strlen($new) < 8) {
        $_SESSION['error_message'] = "Password must be at least 8 characters.";
        header("Location: settings.php"); exit;
    }

    $new_pass =$new;

    $stmt = $conn->prepare("UPDATE admin SET password=? WHERE id=?");
    $stmt->bind_param("si", $new_pass, $adminId);
    $stmt->execute();

    $_SESSION['success_message'] = "Password changed successfully.";
    header("Location: settings.php");
    exit;
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings | CarGo Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: #f8f9fa;
      min-height: 100vh;
    }

    .dashboard-wrapper {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar Styles */
    .sidebar {
      width: 260px;
      background: white;
      padding: 30px 20px;
      box-shadow: 2px 0 10px rgba(0,0,0,0.05);
      position: fixed;
      height: 100vh;
      overflow-y: auto;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      margin-left: 260px;
      padding: 30px 40px;
    }

    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 35px;
    }

    .page-title {
      font-size: 28px;
      font-weight: 800;
      color: #1a1a1a;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .user-profile {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .notification-btn {
      width: 45px;
      height: 45px;
      background: white;
      border: none;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: #666;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .notification-btn:hover {
      background: #f5f5f5;
    }

    .user-avatar {
      width: 45px;
      height: 45px;
      border-radius: 12px;
      overflow: hidden;
      border: 2px solid #1a1a1a;
    }

    .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* Alert Messages */
    .alert-custom {
      padding: 14px 18px;
      border-radius: 12px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      font-weight: 500;
    }

    .alert-success {
      background: #e8f8f0;
      color: #009944;
      border: 2px solid #009944;
    }

    .alert-danger {
      background: #ffe8e8;
      color: #cc0000;
      border: 2px solid #cc0000;
    }

    /* Settings Grid */
    .settings-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
      gap: 25px;
      margin-bottom: 25px;
    }

    .settings-card {
      background: white;
      border-radius: 18px;
      padding: 30px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      transition: all 0.3s ease;
    }

    .settings-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }

    .card-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 2px solid #f0f0f0;
    }

    .card-icon {
      width: 45px;
      height: 45px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
    }

    .icon-profile { background: #e3f2fd; color: #1976d2; }
    .icon-password { background: #fff8e1; color: #f57c00; }
    .icon-system { background: #e8f8f0; color: #009944; }

    .card-title {
      font-size: 18px;
      font-weight: 700;
      color: #1a1a1a;
      margin: 0;
    }

    /* Form Styles */
    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: #666;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-control {
      border: 2px solid #f0f0f0;
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      outline: none;
      border-color: #1a1a1a;
      box-shadow: 0 0 0 3px rgba(26, 26, 26, 0.1);
    }

    /* Password Toggle */
    .password-wrapper {
      position: relative;
    }

    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #999;
      cursor: pointer;
      font-size: 18px;
      transition: color 0.3s ease;
    }

    .password-toggle:hover {
      color: #1a1a1a;
    }

    /* Profile Upload */
    .profile-upload {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 25px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 12px;
    }

    .profile-preview {
      width: 80px;
      height: 80px;
      border-radius: 16px;
      overflow: hidden;
      border: 3px solid #1a1a1a;
    }

    .profile-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .upload-info {
      flex: 1;
    }

    .upload-title {
      font-size: 14px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 4px;
    }

    .upload-subtitle {
      font-size: 12px;
      color: #999;
      margin-bottom: 10px;
    }

    .file-input-wrapper {
      position: relative;
      display: inline-block;
    }

    .file-input-wrapper input[type=file] {
      position: absolute;
      left: -9999px;
    }

    .file-input-label {
      padding: 8px 18px;
      background: #1a1a1a;
      color: white;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-block;
    }

    .file-input-label:hover {
      background: #000000;
    }

    /* Buttons */
    .btn-custom {
      padding: 12px 28px;
      border: none;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-primary-custom {
      background: #1a1a1a;
      color: white;
    }

    .btn-primary-custom:hover {
      background: #000000;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }

    .btn-warning-custom {
      background: #fff8e1;
      color: #f57c00;
    }

    .btn-warning-custom:hover {
      background: #f57c00;
      color: white;
    }

    .btn-success-custom {
      background: #e8f8f0;
      color: #009944;
    }

    .btn-success-custom:hover {
      background: #009944;
      color: white;
    }

    /* Switch Toggle */
    .switch-container {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      background: #f8f9fa;
      border-radius: 12px;
      margin-bottom: 15px;
      transition: all 0.3s ease;
    }

    .switch-container:hover {
      background: #f0f0f0;
    }

    .switch-info {
      flex: 1;
    }

    .switch-label {
      font-size: 14px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 4px;
    }

    .switch-description {
      font-size: 12px;
      color: #999;
    }

    .switch {
      position: relative;
      display: inline-block;
      width: 50px;
      height: 26px;
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 34px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 20px;
      width: 20px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }

    input:checked + .slider {
      background-color: #009944;
    }

    input:checked + .slider:before {
      transform: translateX(24px);
    }

    @media (max-width: 1200px) {
      .sidebar {
        width: 80px;
      }

      .main-content {
        margin-left: 80px;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        display: none;
      }

      .main-content {
        margin-left: 0;
        padding: 20px;
      }

      .settings-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<div class="dashboard-wrapper">
  <?php include('include/sidebar.php'); ?>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
      <h1 class="page-title">
        <i class="bi bi-gear-fill"></i>
        Settings
      </h1>
      <div class="user-profile">
        <button class="notification-btn">
          <i class="bi bi-bell"></i>
        </button>
        <div class="user-avatar">
          <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin['fullname'] ?? 'Admin') ?>&background=1a1a1a&color=fff" alt="Admin">
        </div>
      </div>
    </div>

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success_message'])): ?>
    <div class="alert-custom alert-success">
      <i class="bi bi-check-circle-fill"></i>
      <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error_message'])): ?>
    <div class="alert-custom alert-danger">
      <i class="bi bi-x-circle-fill"></i>
      <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <!-- Settings Grid -->
    <div class="settings-grid">
      
      <!-- Profile Settings -->
      <div class="settings-card">
        <div class="card-header">
          <div class="card-icon icon-profile">
            <i class="bi bi-person-circle"></i>
          </div>
          <h5 class="card-title">Profile Information</h5>
        </div>

        <form method="POST" enctype="multipart/form-data">
          <!-- Profile Picture Upload -->
           <input type="hidden" name="update_profile">
          <div class="profile-upload">
            <div class="profile-preview">
  <img 
    src="<?= !empty($admin['profile_image']) 
        ? htmlspecialchars($admin['profile_image']) 
        : 'https://ui-avatars.com/api/?name=' . urlencode($admin['fullname']) ?>" 
    id="profilePreview" 
    alt="Profile">
</div>

            <div class="upload-info">
              <div class="upload-title">Profile Picture</div>
              <div class="upload-subtitle">JPG or PNG. Max size 2MB</div>
              <div class="file-input-wrapper">
                <input type="file" id="profilePicture" name="profile_picture" accept="image/*" onchange="previewImage(this)">
                <label for="profilePicture" class="file-input-label">
                  <i class="bi bi-upload"></i> Upload Photo
                </label>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label for="adminName" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="adminName" name="fullname" 
                   value="<?= htmlspecialchars($admin['fullname'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label for="adminEmail" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="adminEmail" name="email" 
                   value="<?= htmlspecialchars($admin['email'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label for="adminPhone" class="form-label">Phone Number</label>
            <input type="tel" class="form-control" id="adminPhone" name="phone" 
                   value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" placeholder="+63 912 345 6789">
          </div>

          <button type="submit" class="btn-custom btn-primary-custom">
            <i class="bi bi-check-lg"></i> Update Profile
          </button>
        </form>
      </div>

      <!-- Change Password -->
      <div class="settings-card">
        <div class="card-header">
          <div class="card-icon icon-password">
            <i class="bi bi-key-fill"></i>
          </div>
          <h5 class="card-title">Change Password</h5>
        </div>

        <form method="POST">
  <input type="hidden" name="change_password">

  <div class="mb-3">
    <label for="currentPassword" class="form-label">Current Password</label>
    <input type="password" class="form-control" name="current_password" required>
  </div>

  <div class="mb-3">
    <label for="newPassword" class="form-label">New Password</label>
    <input type="password" class="form-control" name="new_password" required>
  </div>

  <div class="mb-3">
    <label for="confirmPassword" class="form-label">Confirm New Password</label>
    <input type="password" class="form-control" name="confirm_password" required>
  </div>

  <button type="submit" class="btn-custom btn-warning-custom">
    Change Password
  </button>
</form>

      </div>

    </div>

    <!-- System Settings -->
    <div class="settings-card">
      <div class="card-header">
        <div class="card-icon icon-system">
          <i class="bi bi-sliders"></i>
        </div>
        <h5 class="card-title">System Preferences</h5>
      </div>

      <form method="POST" action="update_settings.php">
        <div class="switch-container">
          <div class="switch-info">
            <div class="switch-label">Email Notifications</div>
            <div class="switch-description">Receive email alerts for new bookings and activities</div>
          </div>
          <label class="switch">
            <input type="checkbox" name="email_notifications" <?= ($admin['email_notifications'] ?? 1) ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>

        <div class="switch-container">
          <div class="switch-info">
            <div class="switch-label">Maintenance Mode</div>
            <div class="switch-description">Put the website in maintenance mode (only admins can access)</div>
          </div>
          <label class="switch">
            <input type="checkbox" name="maintenance_mode" <?= ($admin['maintenance_mode'] ?? 0) ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>

        <button type="submit" class="btn-custom btn-success-custom">
          <i class="bi bi-save"></i> Save Settings
        </button>
      </form>
    </div>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Password Toggle





</body>
</html>