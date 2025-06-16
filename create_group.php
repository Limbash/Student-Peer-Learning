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

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $user_id = $_SESSION['user_id'];

    if (empty($name)) $errors[] = "Group name is required.";
    if (empty($description)) $errors[] = "Description is required.";
    if (!in_array($category, ['maths', 'english', 'chemistry'])) {
        $errors[] = "Invalid category selected.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO groups (name, description, category, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $description, $category, $user_id);
        if ($stmt->execute()) {
            $success = "Group created successfully.";
            header("Location: discussions.php");
            exit;
        } else {
            $errors[] = "Error creating group.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Discussion Group</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-4">
    <h2>Create Discussion Group</h2>
    
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Group Name</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Category</label>
        <select name="category" class="form-select" required>
          <option value="">Select category</option>
          <option value="maths" <?= (($_POST['category'] ?? '') == 'maths') ? 'selected' : '' ?>>Maths</option>
          <option value="english" <?= (($_POST['category'] ?? '') == 'english') ? 'selected' : '' ?>>English</option>
          <option value="chemistry" <?= (($_POST['category'] ?? '') == 'chemistry') ? 'selected' : '' ?>>Chemistry</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Create Group</button>
      <a href="discussions.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</body>
</html>
