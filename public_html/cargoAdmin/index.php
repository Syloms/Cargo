<?php
include "include/db.php";
session_start();

if($_SERVER['REQUEST_METHOD']==="POST"){

    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = "SELECT * FROM admin WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){
        $row = $result->fetch_assoc();

        if($password == $row['password']){
          $_SESSION['admin_id']    = $row['id'];
            $_SESSION['admin_user'] = $row['username'];
            $_SESSION['admin_email'] = $row['email'];

            header('Location: dashboard.php');
        }else{
          $_SESSION['wrongPass'] = "Invalid Password";
        }
    }else{
      $_SESSION['wrongEmail'] = "Invalid Email";
    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CarGo Admin - Peer-to-Peer Car Rental Platform</title>
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
      background: #d4e5d4;
      height: 100vh;
      overflow: hidden;
    }

    .login-wrapper {
      height: 100vh;
      display: flex;
    }

    /* Left Hero Section */
    .hero-section {
      flex: 1;
      background: #d4e5d4;
      padding: 60px;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow: hidden;
    }

    .header {
      margin-bottom: 40px;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 4px;
    }

    .logo-image {
      width: 50px;
      height: auto;
    }

    .logo {
      font-size: 32px;
      font-weight: 800;
      letter-spacing: 4px;
      color: #1a1a1a;
    }

    .logo-subtitle {
      font-size: 11px;
      color: #2e7d32;
      font-weight: 600;
      letter-spacing: 2px;
      text-transform: uppercase;
    }

    .hero-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      position: relative;
    }

    .hero-title {
      font-size: 72px;
      font-weight: 800;
      color: #1a1a1a;
      line-height: 1;
      margin-bottom: 30px;
    }

    .hero-description {
      font-size: 16px;
      color: #424242;
      margin-bottom: 60px;
      font-weight: 400;
      max-width: 550px;
      line-height: 1.6;
    }

    .car-showcase {
      position: relative;
      width: 100%;
      max-width: 800px;
      margin: 0 auto;
      margin-top: 40px;
    }

    .car-image {
      width: 100%;
      height: auto;
      display: block;
      object-fit: contain;
      filter: drop-shadow(0 30px 50px rgba(0,0,0,0.2));
    }

    /* Right Login Section */
    .login-section {
      flex: 0 0 500px;
      background: white;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px;
      box-shadow: -5px 0 30px rgba(0,0,0,0.05);
    }

    .login-container {
      width: 100%;
      max-width: 400px;
    }

    .admin-badge {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: #f5f5f5;
      padding: 10px 20px;
      border-radius: 50px;
      margin-bottom: 30px;
    }

    .admin-badge i {
      font-size: 18px;
      color: #2e7d32;
    }

    .admin-badge span {
      font-size: 13px;
      font-weight: 600;
      color: #1a1a1a;
      letter-spacing: 1px;
      text-transform: uppercase;
    }

    .login-title {
      font-size: 48px;
      font-weight: 800;
      color: #1a1a1a;
      margin-bottom: 10px;
      line-height: 1.1;
    }

    .login-subtitle {
      font-size: 14px;
      color: #666;
      margin-bottom: 40px;
      font-weight: 400;
      line-height: 1.5;
    }

    .form-group {
      margin-bottom: 24px;
    }

    .form-label {
      font-size: 13px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 10px;
      display: block;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .form-control {
      width: 100%;
      padding: 15px 18px;
      border: 2px solid #e0e0e0;
      border-radius: 12px;
      font-size: 15px;
      transition: all 0.3s ease;
      background: #fafafa;
    }

    .form-control:focus {
      outline: none;
      border-color: #2e7d32;
      background: white;
      box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
    }

    .btn-login {
      width: 100%;
      padding: 16px;
      background: #1a1a1a;
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
    }

    .btn-login:hover {
      background: #2e7d32;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(46, 125, 50, 0.3);
    }

    .alert {
      border: none;
      border-radius: 12px;
      padding: 14px 18px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      font-weight: 500;
    }

    .alert-danger {
      background: #ffebee;
      color: #c62828;
    }

    .alert i {
      font-size: 18px;
    }

    .footer {
      margin-top: 40px;
      text-align: center;
      color: #999;
      font-size: 12px;
    }

    @media (max-width: 1200px) {
      .hero-section {
        display: none;
      }
      
      .login-section {
        flex: 1;
      }
    }

    @media (max-width: 768px) {
      .login-section {
        padding: 40px 30px;
      }

      .login-title {
        font-size: 36px;
      }
    }
  </style>
</head>
<body>

  <div class="login-wrapper">
    <!-- Left Hero Section -->
    <div class="hero-section">
      <div class="header">
        <div class="logo-container">
          <img src="cargo.png" alt="CarGo Logo" class="logo-image">
          <div class="logo">CARGO</div>
        </div>
        <div class="logo-subtitle">Peer-to-Peer Car Rental</div>
      </div>

      <div class="hero-content">
        <h1 class="hero-title">Rent Car In your Area</h1>
        <p class="hero-description">
          Connect car owners with renters. Manage your peer-to-peer platform with comprehensive coverage and the highest quality service.
        </p>

        <div class="car-showcase">
          <img src="car.png" alt="Luxury Car" class="car-image">
        </div>
      </div>
    </div>

    <!-- Right Login Section -->
    <div class="login-section">
      <div class="login-container">
        <?php if(isset($_SESSION['wrongPass'])) : ?>
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <span><?= $_SESSION['wrongPass']; unset($_SESSION['wrongPass']) ?></span>
          </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['wrongEmail'])) : ?>
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <span><?= $_SESSION['wrongEmail']; unset($_SESSION['wrongEmail']) ?></span>
          </div>
        <?php endif; ?>

        <div class="admin-badge">
          <i class="bi bi-shield-check"></i>
          <span>Admin Portal</span>
        </div>

        <h1 class="login-title">Welcome<br>Back</h1>
        <p class="login-subtitle">Sign in to manage your peer-to-peer car rental platform</p>

        <form action="" method="POST">
          <div class="form-group">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="admin@cargo.com" required>
          </div>

          <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
          </div>

          <button type="submit" class="btn-login">
            Sign In
          </button>
        </form>

        <div class="footer">
          © 2025 CarGo Peer-to-Peer Platform. All rights reserved.
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>