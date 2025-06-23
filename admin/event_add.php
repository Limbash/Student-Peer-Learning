<?php
require 'admin_check.php';
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $date = $_POST['event_date'] ?? '';

    if ($title && $date) {
        $stmt = $conn->prepare("INSERT INTO events (title, event_date) VALUES (?, ?)");
        $stmt->bind_param("ss", $title, $date);
        $stmt->execute();
    }
}

header("Location: manage_events.php");
exit;
