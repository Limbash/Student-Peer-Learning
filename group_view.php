<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli("localhost", "root", "", "peer_learning_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$group_id = intval($_GET['id'] ?? 0);
if (!$group_id) {
    die("Invalid group ID.");
}

// Fetch group details
$stmt = $conn->prepare("SELECT g.*, u.name AS creator_name FROM groups g JOIN users u ON g.created_by = u.id WHERE g.id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    die("Group not found.");
}

// Fetch members
$members = $conn->query("SELECT u.name FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = $group_id");

// Fetch resources
$resources = $conn->query("SELECT * FROM resources WHERE group_id = $group_id ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($group['name']) ?> - Group Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-4">
    <h2><?= htmlspecialchars($group['name']) ?></h2>
    <p class="text-muted"><?= ucfirst(htmlspecialchars($group['category'])) ?> group</p>
    <p><?= nl2br(htmlspecialchars($group['description'])) ?></p>
    <p><small>Created by <?= htmlspecialchars($group['creator_name']) ?> on <?= $group['created_at'] ?></small></p>

    <a href="join_group.php?id=<?= $group_id ?>" class="btn btn-success mb-3">Join Group</a>

    <h4>Members</h4>
    <ul class="list-group mb-4">
      <?php while ($m = $members->fetch_assoc()): ?>
        <li class="list-group-item"><?= htmlspecialchars($m['name']) ?></li>
      <?php endwhile; ?>
    </ul>

    <h4>Resources</h4>
    <?php if ($resources->num_rows > 0): ?>
      <ul class="list-group">
        <?php while ($r = $resources->fetch_assoc()): ?>
          <li class="list-group-item">
            <strong><?= htmlspecialchars($r['title']) ?></strong>
            <p><?= nl2br(htmlspecialchars($r['description'])) ?></p>
            <a href="uploads/<?= htmlspecialchars($r['filename']) ?>" target="_blank">Download</a>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <p class="text-muted">No resources shared yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>
