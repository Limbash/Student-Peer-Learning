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

// Fetch groups
$sql = "SELECT g.id, g.name, g.description, g.category, u.name AS creator_name
        FROM groups g
        JOIN users u ON g.created_by = u.id
        ORDER BY g.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Discussions - Student Peer-to-Peer Learning Network</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Discussion Groups</h2>
      <a href="create_group.php" class="btn btn-primary">Create Discussion Group</a>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
      <div class="list-group">
        <?php while($row = $result->fetch_assoc()): ?>
          <a href="group_view.php?id=<?= $row['id'] ?>" class="list-group-item list-group-item-action">
            <div class="d-flex w-100 justify-content-between">
              <h5 class="mb-1"><?= htmlspecialchars($row['name']) ?></h5>
              <small class="text-muted"><?= ucfirst(htmlspecialchars($row['category'])) ?></small>
            </div>
            <p class="mb-1"><?= nl2br(htmlspecialchars($row['description'])) ?></p>
            <small class="text-muted">Created by <?= htmlspecialchars($row['creator_name']) ?></small>
          </a>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-info">No discussion groups created yet.</div>
    <?php endif; ?>

  </div>
</body>
</html>
