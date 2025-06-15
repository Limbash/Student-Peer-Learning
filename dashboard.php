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
</head>
<body>
  <div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-dark text-white" id="sidebar-wrapper">
      <div class="sidebar-heading text-center py-4 fs-4 fw-bold">Dashboard</div>
      <div class="list-group list-group-flush">
        <a href="#" class="list-group-item list-group-item-action bg-dark text-white">Profile</a>
        <a href="#" class="list-group-item list-group-item-action bg-dark text-white">Discussions</a>
        <a href="#" class="list-group-item list-group-item-action bg-dark text-white">Joined Groups</a>
        <a href="#" class="list-group-item list-group-item-action bg-dark text-white">Resources</a>
        <a href="logout.php" class="list-group-item list-group-item-action bg-dark text-white">Logout</a>
      </div>
    </div>

    <!-- Page Content -->
    <div id="page-content-wrapper" class="w-100">
      <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
        <div class="container-fluid">
          <button class="btn btn-dark" id="menu-toggle">☰</button>
          <h3 class="ms-3 mb-0">Welcome Back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Student'); ?>!</h3>
        </div>
      </nav>

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
