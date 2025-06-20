<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$group_id = intval($_POST['group_id'] ?? 0);

// Verify user is the creator
$stmt = $conn->prepare("SELECT 1 FROM groups WHERE id = ? AND created_by = ?");
$stmt->bind_param("ii", $group_id, $_SESSION['user_id']);
$stmt->execute();

if (!$stmt->get_result()->num_rows) {
    $_SESSION['error'] = "You don't have permission to delete this group";
    header("Location: group_view.php?id=$group_id");
    exit;
}

// Delete group (cascading deletes should handle members, messages, etc.)
$stmt = $conn->prepare("DELETE FROM groups WHERE id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();

$_SESSION['success'] = "Group deleted successfully";
header('Location: discussions.php');
exit;