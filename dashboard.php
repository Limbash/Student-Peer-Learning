<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Student Peer-to-Peer Learning Network</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./styles/dashboard.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

</head>
<body>
  <div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-dark text-white" id="sidebar-wrapper">
      <div class="sidebar-heading text-center py-4 fs-4 fw-bold">Dashboard</div>
        <div class="list-group list-group-flush">
          <a href="#" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="bi bi-chat-dots me-2"></i> Discussions
          </a>
          <a href="#" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="bi bi-people me-2"></i> Joined Groups
          </a>
          <a href="#" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="bi bi-folder2-open me-2"></i> Resources
          </a>
        </div>
    </div>

    <!-- Page Content -->
    <div id="page-content-wrapper" class="w-100">
     <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
      <div class="container-fluid">
        <button class="btn btn-dark" id="menu-toggle">☰</button>

        <div class="dropdown ms-auto">
          <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="https://via.placeholder.com/40" alt="User" width="40" height="40" class="rounded-circle">
          </a>
            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
              <li>
                <a class="dropdown-item" href="profile.php">
                  <i class="bi bi-person me-2"></i> Profile
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="logout.php">
                  <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
              </li>
            </ul>
        </div>
      </div>
    </nav>

       <h3 class="ms-3 mb-0">Welcome Back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Student'); ?>!</h3>
      <div class="container-fluid mt-4">
        
        <div class="row">
          <!-- Joined Groups -->
          <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">Joined Groups</h5>
                <p class="card-text">You have joined <strong>3</strong> learning groups.</p>
                <a href="#" class="btn btn-outline-primary btn-sm">View Groups</a>
              </div>
            </div>
          </div>

          <!-- Discussions -->
          <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">Discussions</h5>
                <p class="card-text">There are <strong>5</strong> new discussion threads.</p>
                <a href="#" class="btn btn-outline-primary btn-sm">View Discussions</a>
              </div>
            </div>
          </div>

          <!-- Resources -->
          <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">Resources</h5>
                <p class="card-text">Check out <strong>new resources</strong> shared by peers.</p>
                <a href="#" class="btn btn-outline-primary btn-sm">View Resources</a>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Menu toggle for sidebar
    document.getElementById('menu-toggle').addEventListener('click', () => {
      document.getElementById('wrapper').classList.toggle('toggled');
    });
  </script>
</body>
</html>
