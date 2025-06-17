<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine base URL dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
$base_url = rtrim($base_url, '/') . '/'; // Ensure trailing slash
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Peer Learning Network' ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>css/main.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= $base_url ?>index.php">
                <i class="bi bi-people-fill me-2"></i>Peer Learning Network
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'dashboard.php' ? 'active' : '' ?>" 
                           href="<?= $base_url ?>dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'discussions.php' ? 'active' : '' ?>" 
                           href="<?= $base_url ?>discussions.php">
                            <i class="bi bi-chat-left-text me-1"></i> Discussions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'joined_groups.php' ? 'active' : '' ?>" 
                           href="<?= $base_url ?>joined_groups.php">
                            <i class="bi bi-people me-1"></i> My Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'resources.php' ? 'active' : '' ?>" 
                           href="<?= $base_url ?>resources.php">
                            <i class="bi bi-folder me-1"></i> Resources
                        </a>
                    </li>
                </ul>

                <!-- Search Form -->
                <form class="d-flex ms-3 me-3" method="GET" action="<?= $base_url ?>search.php">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Search..." aria-label="Search">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>

                <!-- User Dropdown -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" 
                           id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= $base_url . htmlspecialchars($_SESSION['profile_pic'] ?? 'images/default.png') ?>" 
                                 alt="Profile" width="32" height="32" class="rounded-circle me-2">
                            <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li>
                                <a class="dropdown-item" href="<?= $base_url ?>profile.php">
                                    <i class="bi bi-person me-2"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= $base_url ?>settings.php">
                                    <i class="bi bi-gear me-2"></i> Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= $base_url ?>logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex">
                        <a href="<?= $base_url ?>login.php" class="btn btn-outline-light me-2">Login</a>
                        <a href="<?= $base_url ?>register.php" class="btn btn-light">Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container py-4">