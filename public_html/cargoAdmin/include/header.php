<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CarGo Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>


<body class="bg-light">


<nav class="navbar navbar-expand-lg navbar-dark bg-secondary p-4 shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
      🚗 <span class="ms-2 ">CarGo Admin</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse " id="adminNavbar">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link text-light" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link text-light" href="users.php"><i class="bi bi-people me-1"></i> Users</a></li>
        <li class="nav-item"><a class="nav-link text-light" href="verification.php"><i class="bi bi-check-circle me-1"></i> Verification</a></li>
        <li class="nav-item"><a class="nav-link text-light" href="get_cars_admin.php">Car Listings</a></li>

        <li class="nav-item"><a class="nav-link text-light" href="bookings.php"><i class="bi bi-calendar-check me-1"></i> Bookings</a></li>
        <li class="nav-item"><a class="nav-link text-light" href="reports.php"><i class="bi bi-flag me-1"></i> Reports</a></li>
        <li class="nav-item"><a class="nav-link text-light" href="settings.php"><i class="bi bi-gear me-1"></i> Settings</a></li>
        <li class="nav-item">
          <a href="logout.php" class="btn btn-outline-danger">Log out</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">


<script>
const links = document.querySelectorAll(".nav-link");
const currentPath = window.location.pathname.split("/").pop();

links.forEach(link => {
  const href = link.getAttribute("href");
  if(href === currentPath) {
    link.classList.add("active");
  }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
