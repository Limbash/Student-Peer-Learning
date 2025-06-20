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
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // 1. Create the group
            $stmt = $conn->prepare("INSERT INTO groups (name, description, category, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $description, $category, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating group.");
            }
            
            $group_id = $conn->insert_id;
            $stmt->close();
            
            // 2. Add creator as member with admin privileges
            $stmt = $conn->prepare("INSERT INTO group_members (user_id, group_id, is_admin, is_creator) VALUES (?, ?, TRUE, TRUE)");
            $stmt->bind_param("ii", $user_id, $group_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error adding creator to group.");
            }
            
            $conn->commit();
            $_SESSION['success'] = "Group created successfully!";
            header("Location: group_view.php?id=$group_id");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Discussion Group</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .form-container {
      max-width: 600px;
      margin: 0 auto;
      background: white;
      padding: 2rem;
      border-radius: 0.5rem;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
  </style>
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="form-container">
      <h2 class="mb-4">Create Discussion Group</h2>
      
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Group Name</label>
          <input type="text" name="name" class="form-control" required 
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4" required><?= 
              htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
        
        <div class="mb-4">
          <label class="form-label">Category</label>
          <select name="category" class="form-select" required>
            <option value="">Select category</option>
            <option value="maths" <?= (($_POST['category'] ?? '') == 'maths') ? 'selected' : '' ?>>Maths</option>
            <option value="english" <?= (($_POST['category'] ?? '') == 'english') ? 'selected' : '' ?>>English</option>
            <option value="chemistry" <?= (($_POST['category'] ?? '') == 'chemistry') ? 'selected' : '' ?>>Chemistry</option>
          </select>
        </div>
        
        <div class="d-flex justify-content-between">
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-plus-circle"></i> Create Group
          </button>
          <a href="discussions.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i> Cancel
          </a>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>