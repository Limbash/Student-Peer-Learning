<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// DB connection
$conn = new mysqli("localhost", "root", "", "peer_learning_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_pic);
$stmt->fetch();
$stmt->close();

// Set default profile picture if none exists
$_SESSION['profile_pic'] = $profile_pic ?: 'images/default.png';

// Get dashboard statistics
$stats = [
    'discussions' => $conn->query("SELECT COUNT(*) AS total FROM groups")->fetch_assoc()['total'],
    'joined_groups' => $conn->query("SELECT COUNT(*) AS total FROM group_members WHERE user_id = $user_id")->fetch_assoc()['total'],
    'resources' => $conn->query("SELECT COUNT(*) AS total FROM resources r JOIN group_members gm ON r.group_id = gm.group_id WHERE gm.user_id = $user_id")->fetch_assoc()['total']
];

// Get recent activities
$activities = $conn->query("
    SELECT ga.*, g.name AS group_name 
    FROM group_activities ga
    JOIN groups g ON ga.group_id = g.id
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = $user_id
    ORDER BY ga.created_at DESC
    LIMIT 5
");

// Get upcoming events
$events = $conn->query("
    SELECT e.*, g.name AS group_name 
    FROM events e
    JOIN groups g ON e.group_id = g.id
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = $user_id
    AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
    LIMIT 3
");

// Get recent groups
$recent_groups = $conn->query("
    SELECT g.id, g.name, g.category 
    FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = $user_id
    ORDER BY gm.last_accessed DESC
    LIMIT 3
");

$conn->close();
?>

<?php include 'partials/navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Welcome Back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Student') ?>!</h3>
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dashboardActions" data-bs-toggle="dropdown">
                <i class="bi bi-gear"></i> Quick Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="create_group.php"><i class="bi bi-plus-circle me-2"></i> Create Group</a></li>
                <li><a class="dropdown-item" href="discussions.php"><i class="bi bi-search me-2"></i> Find Groups</a></li>
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Edit Profile</a></li>
            </ul>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <!-- Joined Groups Card -->
        <div class="col-md-4 mb-4">
            <div class="card dashboard-card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-primary bg-gradient text-white mx-auto mb-3">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                    <h5 class="card-title">Joined Groups</h5>
                    <p class="card-text">You have joined <strong><?= $stats['joined_groups'] ?></strong> learning groups.</p>
                    <a href="joined_groups.php" class="btn btn-outline-primary btn-sm stretched-link">View Groups</a>
                </div>
            </div>
        </div>

        <!-- Discussions Card -->
        <div class="col-md-4 mb-4">
            <div class="card dashboard-card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-info bg-gradient text-white mx-auto mb-3">
                        <i class="bi bi-chat-square-text-fill fs-4"></i>
                    </div>
                    <h5 class="card-title">Discussions</h5>
                    <p class="card-text">There are <strong><?= $stats['discussions'] ?></strong> discussion groups.</p>
                    <a href="discussions.php" class="btn btn-outline-info btn-sm stretched-link">Browse Discussions</a>
                </div>
            </div>
        </div>

        <!-- Resources Card -->
        <div class="col-md-4 mb-4">
            <div class="card dashboard-card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-success bg-gradient text-white mx-auto mb-3">
                        <i class="bi bi-file-earmark-text-fill fs-4"></i>
                    </div>
                    <h5 class="card-title">Resources</h5>
                    <p class="card-text">You have <strong><?= $stats['resources'] ?></strong> shared resources.</p>
                    <a href="resources.php" class="btn btn-outline-success btn-sm stretched-link">View Resources</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity and Groups/Events -->
    <div class="row">
        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-clock-history me-2"></i> Recent Activity</h5>
                    <a href="activity.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if ($activities->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while($activity = $activities->fetch_assoc()): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="activity-item">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <?php 
                                                $icon = [
                                                    'join' => 'bi-person-plus text-success',
                                                    'leave' => 'bi-person-dash text-danger',
                                                    'post' => 'bi-chat-text text-primary',
                                                    'resource' => 'bi-file-earmark text-info'
                                                ][$activity['activity_type']];
                                                ?>
                                                <i class="bi <?= $icon ?> fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <p class="mb-1">
                                                    <?= htmlspecialchars($activity['activity_details']) ?>
                                                    <small class="text-muted">in <?= htmlspecialchars($activity['group_name']) ?></small>
                                                </p>
                                                <small class="text-muted">
                                                    <?= date('M j, g:i a', strtotime($activity['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            No recent activity yet. Join a group to get started!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Groups and Events -->
        <div class="col-lg-6">
            <!-- Recent Groups -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-collection me-2"></i> Your Recent Groups</h5>
                </div>
                <div class="card-body">
                    <?php if ($recent_groups->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while($group = $recent_groups->fetch_assoc()): ?>
                                <a href="group_view.php?id=<?= $group['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($group['name']) ?></strong>
                                            <span class="badge bg-secondary ms-2"><?= ucfirst($group['category']) ?></span>
                                        </div>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-3">
                            You haven't joined any groups yet. <a href="discussions.php" class="alert-link">Browse groups</a> to get started!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-calendar-event me-2"></i> Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <?php if ($events->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while($event = $events->fetch_assoc()): ?>
                                <a href="event.php?id=<?= $event['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($event['title']) ?></strong>
                                        <span class="badge bg-primary">
                                            <?= date('M j', strtotime($event['event_date'])) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($event['group_name']) ?></small>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            No upcoming events. Check back later!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>