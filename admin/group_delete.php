<?php
require 'admin_check.php';
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    $id = intval($_POST['group_id']);
    $stmt = $conn->prepare("DELETE FROM groups WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: manage_groups.php");
exit;
