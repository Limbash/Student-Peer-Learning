<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Handle leaving a group
if (isset($_GET['leave_group']) && is_numeric($_GET['leave_group'])) {
    $group_id = intval($_GET['leave_group']);
    
    $stmt = $conn->prepare("DELETE FROM group_members WHERE user_id = ? AND group_id = ?");
    $stmt->bind_param("ii", $user_id, $group_id);
    
    if ($stmt->execute()) {
        // Record activity
        $activity_stmt = $conn->prepare("INSERT INTO group_activities (group_id, user_id, activity_type) VALUES (?, ?, 'leave')");
        $activity_stmt->bind_param("ii", $group_id, $user_id);
        $activity_stmt->execute();
        $activity_stmt->close();
        
        $_SESSION['success'] = "You have left the group successfully.";
    } else {
        $_SESSION['error'] = "Error leaving the group.";
    }
    $stmt->close();
    header("Location: joined_groups.php");
    exit;
}

// Fetch all groups the user has joined with additional details
$joined_groups = $conn->query("
    SELECT g.id, g.name, g.description, g.category, g.created_at, 
           u.name AS creator_name, gm.joined_at, gm.is_admin,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count,
           (SELECT COUNT(*) FROM group_activities WHERE group_id = g.id AND activity_type = 'post') AS post_count,
           (SELECT COUNT(*) FROM group_activities WHERE group_id = g.id AND activity_type = 'resource') AS resource_count
    FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    JOIN users u ON g.created_by = u.id
    WHERE gm.user_id = $user_id
    ORDER BY gm.last_accessed DESC
");

// Update last accessed time when visiting the page
$conn->query("UPDATE group_members SET last_accessed = NOW() WHERE user_id = $user_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Groups - Peer Learning Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .group-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .group-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 15px;
        }
        .group-stats {
            background-color: #f8f9fc;
            padding: 10px;
            border-bottom: 1px solid #e3e6f0;
        }
        .stat-item {
            text-align: center;
            padding: 5px;
        }
        .stat-number {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .admin-badge {
            background-color: #f6c23e;
            color: #000;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php include 'partials/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people-fill me-2"></i>My Joined Groups</h2>
            <a href="discussions.php" class="btn btn-outline-primary">
                <i class="bi bi-plus-circle me-1"></i> Find More Groups
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if ($joined_groups->num_rows > 0): ?>
            <div class="row">
                <?php while($group = $joined_groups->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="group-card card shadow-sm">
                            <div class="group-header">
                                <h5 class="mb-1">
                                    <?= htmlspecialchars($group['name']) ?>
                                    <?php if ($group['is_admin']): ?>
                                        <span class="admin-badge">ADMIN</span>
                                    <?php endif; ?>
                                </h5>
                                <small><?= ucfirst(htmlspecialchars($group['category'])) ?></small>
                            </div>
                            
                            <div class="group-stats">
                                <div class="row text-center">
                                    <div class="col-4 stat-item">
                                        <div class="stat-number"><?= $group['member_count'] ?></div>
                                        <div class="stat-label">Members</div>
                                    </div>
                                    <div class="col-4 stat-item">
                                        <div class="stat-number"><?= $group['post_count'] ?></div>
                                        <div class="stat-label">Posts</div>
                                    </div>
                                    <div class="col-4 stat-item">
                                        <div class="stat-number"><?= $group['resource_count'] ?></div>
                                        <div class="stat-label">Resources</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-text"><?= nl2br(htmlspecialchars(substr($group['description'], 0, 100))) ?><?= strlen($group['description']) > 100 ? '...' : '' ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Joined <?= date('M j, Y', strtotime($group['joined_at'])) ?>
                                    </small>
                                    
                                    <div class="btn-group">
                                        <a href="group_view.php?id=<?= $group['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> Open
                                        </a>
                                        <a href="joined_groups.php?leave_group=<?= $group['id'] ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to leave this group?')">
                                            <i class="bi bi-box-arrow-right me-1"></i> Leave
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-people" style="font-size: 4rem; color: #6c757d;"></i>
                </div>
                <h4>You haven't joined any groups yet</h4>
                <p class="text-muted">Join groups to collaborate with peers and access shared resources</p>
                <a href="discussions.php" class="btn btn-primary mt-3">
                    <i class="bi bi-search me-1"></i> Browse Groups
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>