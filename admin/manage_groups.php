<?php
require 'admin_check.php';
require '../includes/db.php';

// Search and pagination
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM groups WHERE name LIKE ?");
$like = "%$search%";
$count_stmt->bind_param("s", $like);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['count'];
$pages = ceil($total / $limit);

// Fetch groups
$stmt = $conn->prepare("SELECT * FROM groups WHERE name LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("sii", $like, $limit, $offset);
$stmt->execute();
$groups = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Groups</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body { display: flex; min-height: 100vh; }
        .sidebar { width: 220px; background: #343a40; color: white; padding-top: 20px; }
        .sidebar a { color: #ddd; display: block; padding: 12px 20px; text-decoration: none; }
        .sidebar a:hover { background-color: #495057; }
        .content { flex: 1; padding: 30px; background: #f8f9fa; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h4 class="text-center mb-4">Admin Panel</h4>
    <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="manage_users.php"><i class="bi bi-people"></i> Manage Users</a>
    <a href="manage_groups.php" class="fw-bold text-white"><i class="bi bi-collection"></i> Manage Groups</a>
    <a href="manage_events.php"><i class="bi bi-calendar-event"></i> Manage Events</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Content -->
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Manage Groups</h3>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addGroupModal">
            <i class="bi bi-plus-circle"></i> Add Group
        </button>
    </div>

    <form method="get" class="mb-3 d-flex" style="max-width: 400px;">
        <input type="text" name="search" class="form-control me-2" placeholder="Search groups..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-outline-secondary">Search</button>
    </form>

    <table class="table table-bordered bg-white">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = $offset + 1; while ($row = $groups->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                    <td>
                        <a href="group_view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteGroupModal<?= $row['id'] ?>">
                            Delete
                        </button>
                    </td>
                </tr>

                <!-- Delete Group Modal -->
                <div class="modal fade" id="deleteGroupModal<?= $row['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="group_delete.php" method="POST">
                                <input type="hidden" name="group_id" value="<?= $row['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Delete Group</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    Are you sure you want to delete the group <strong><?= htmlspecialchars($row['name']) ?></strong>?
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php endwhile; ?>
        </tbody>
    </table>

    <nav>
        <ul class="pagination">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="group_add.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Group Name</label>
                        <input type="text" name="group_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Group</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
