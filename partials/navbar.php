<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Peer-to-Peer Learning Network</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/main.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">Peer Learning Network</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../discussions.php"><i class="bi bi-chat-left-text me-1"></i> Discussions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../group_view.php"><i class="bi bi-people me-1"></i> Groups</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../resources.php"><i class="bi bi-folder me-1"></i> Resources</a>
                    </li>
                </ul>
                
                <form class="d-flex ms-3 me-3" method="GET" action="../search.php">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Search..." aria-label="Search">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown">
                            <img src="<?= htmlspecialchars($_SESSION['profile_pic'] ?? '../images/default.png') ?>" 
                                 alt="Profile" width="32" height="32" class="rounded-circle me-2">
                            <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex">
                        <a href="../login.php" class="btn btn-outline-light me-2">Login</a>
                        <a href="../register.php" class="btn btn-light">Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <main class="container py-4">