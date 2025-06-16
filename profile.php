<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include 'includes/db.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email, phone, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $phone, $profile_pic);
$stmt->fetch();
$stmt->close();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $new_name = trim($_POST['name']);
        $new_phone = trim($_POST['phone']);

        if (empty($new_name)) {
            $error = 'Name is required.';
        } elseif (!preg_match('/^\+?\d{7,15}$/', $new_phone) && !empty($new_phone)) {
            $error = 'Phone must be valid (7-15 digits, optional +).';
        } else {
            // Handle profile picture
            if (!empty($_FILES['profile_pic']['name'])) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $file_name = basename($_FILES["profile_pic"]["name"]);
                $target_file = $target_dir . time() . "_" . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                        $profile_pic = $target_file;
                    } else {
                        $error = 'Error uploading image.';
                    }
                } else {
                    $error = 'Only JPG, JPEG, PNG, GIF files allowed.';
                }
            }

            if (!$error) {
                $update = $conn->prepare("UPDATE users SET name = ?, phone = ?, profile_pic = ? WHERE id = ?");
                $update->bind_param("sssi", $new_name, $new_phone, $profile_pic, $user_id);
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
    }

    if (isset($_POST['change_password'])) {
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (strlen($new_pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'Passwords do not match.';
        } else {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed_pass, $user_id);
            if ($update->execute()) {
                $success = 'Password changed successfully.';
            } else {
                $error = 'Failed to change password.';
            }
            $update->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile - Student Peer-to-Peer Learning Network</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .profile-pic {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: 50%;
    }
  </style>
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

    <div class="mb-3">
      <img src="<?= htmlspecialchars($profile_pic ?: 'default.png') ?>" class="profile-pic" alt="Profile Picture">
    </div>

    <form method="POST" enctype="multipart/form-data" class="mb-5">
      <input type="hidden" name="update_profile" value="1">
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
        <small class="text-muted">Format: +1234567890 or 1234567890</small>
      </div>
      <div class="mb-3">
        <label class="form-label">Profile Picture</label>
        <input type="file" name="profile_pic" class="form-control">
      </div>
      <button type="submit" class="btn btn-primary">Update Profile</button>
    </form>

    <h4>Change Password</h4>
    <form method="POST">
      <input type="hidden" name="change_password" value="1">
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-warning">Change Password</button>
    </form>

    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
