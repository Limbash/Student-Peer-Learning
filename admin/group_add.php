<?php
require 'admin_check.php';
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        $stmt = $conn->prepare("INSERT INTO groups (name, created_at) VALUES (?, NOW())");
        $stmt->bind_param("s", $name);
        $stmt->execute();
    }
}

header("Location: manage_groups.php");
exit;
