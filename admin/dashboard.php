<?php
require 'admin_check.php';
require '../includes/db.php';

// Fetch stats
$total_users = $conn->query("SELECT COUNT(*) AS count FROM users")->fetch_assoc()['count'];
$total_groups = $conn->query("SELECT COUNT(*) AS count FROM groups")->fetch_assoc()['count'];
$total_events = $conn->query("SELECT COUNT(*) AS count FROM events")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 220px;
            background-color: #343a40;
            color: white;
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar a {
            color: #ddd;
            display: block;
            padding: 12px 20px;
            text-decoration: none;
        }
        .sidebar a:hover {
            background-color: #495057;
            color: white;
        }
        .content {
            flex: 1;
            padding: 30px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h4 class="text-center mb-4">Admin Panel</h4>
    <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="manage_users.php"><i class="bi bi-people"></i> Manage Users</a>
    <a href="manage_groups.php"><i class="bi bi-collection"></i> Manage Groups</a>
    <a href="manage_events.php"><i class="bi bi-calendar-event"></i> Manage Events</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="content">
    <h2 class="mb-4">Welcome, Admin</h2>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">Total Users</h5>
                    <p class="card-text display-6"><?= $total_users ?></p>
                    <i class="bi bi-person-lines-fill text-primary fs-3"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">Total Groups</h5>
                    <p class="card-text display-6"><?= $total_groups ?></p>
                    <i class="bi bi-chat-left-text text-success fs-3"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">Total Events</h5>
                    <p class="card-text display-6"><?= $total_events ?></p>
                    <i class="bi bi-calendar-event text-danger fs-3"></i>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
