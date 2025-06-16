<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include 'includes/db.php';

// Fetch user info
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $phone);
$stmt->fetch();
$stmt->close();

// Handle form submission
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name']);
    $new_phone = trim($_POST['phone']);

    if (empty($new_name)) {
        $error = 'Name is required.';
    } else {
        $update = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $update->bind_param("ssi", $new_name, $new_phone, $user_id);
        if ($update->execute()) {
            $success = 'Profile updated successfully.';
            $_SESSION['user_name'] = $new_name;
            $name = $new_name;
            $phone = $new_phone;
        } else {
            $error = 'Failed to update profile.';
        }
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile - Student Peer-to-Peer Learning Network</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h2>Your Profile</h2>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="mt-4">
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email (cannot change)</label>
        <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" readonly>
      </div>
      <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Update Profile</button>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
