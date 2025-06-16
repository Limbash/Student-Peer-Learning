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
$user_id = $_SESSION['user_id'];

if ($group_id) {
    $stmt = $conn->prepare("INSERT IGNORE INTO group_members (user_id, group_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $group_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: group_view.php?id=$group_id");
exit;
