<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    $user_id = $_SESSION['user_id'];

    switch ($action) {
        case 'send_message':
            if (empty($_POST['group_id']) || (empty($_POST['message']) && empty($_FILES['attachment']['name']))) {
                throw new Exception('Message or attachment required');
            }

            $group_id = intval($_POST['group_id']);
            $message = trim($_POST['message'] ?? '');
            $attachment = null;

            // Verify group membership
            $stmt = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ?");
            $stmt->bind_param("ii", $user_id, $group_id);
            $stmt->execute();
            if (!$stmt->get_result()->num_rows) {
                throw new Exception('You are not a member of this group');
            }
            $stmt->close();

            // Handle file upload
            if (!empty($_FILES['attachment']['name'])) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $originalName = basename($_FILES['attachment']['name']);
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                if (!in_array(strtolower($ext), $allowed)) {
                    throw new Exception('Invalid file type');
                }

                $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $originalName);
                $targetPath = $uploadDir . $filename;

                if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                    throw new Exception('File upload failed');
                }

                $attachment = $filename;
            }

            // Save message
            $stmt = $conn->prepare("INSERT INTO group_chats (group_id, user_id, message, attachment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $group_id, $user_id, $message, $attachment);
            $stmt->execute();
            $message_id = $stmt->insert_id;
            $stmt->close();

            // Update last_message_at
            $conn->query("UPDATE groups SET last_message_at = NOW() WHERE id = $group_id");

            echo json_encode(['success' => true, 'message_id' => $message_id]);
            break;

        case 'get_messages':
            if (empty($_GET['group_id'])) {
                throw new Exception('Group ID is required');
            }

            $group_id = intval($_GET['group_id']);
            $last_id = intval($_GET['last_id'] ?? 0);

            // Check membership
            $stmt = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ?");
            $stmt->bind_param("ii", $user_id, $group_id);
            $stmt->execute();
            if (!$stmt->get_result()->num_rows) {
                throw new Exception('You are not a member of this group');
            }
            $stmt->close();

            // Fetch messages
            $stmt = $conn->prepare("
                SELECT gc.*, u.name AS user_name, u.profile_pic,
                    (SELECT GROUP_CONCAT(CONCAT(reaction, ':', reactor_id) SEPARATOR ',')
                     FROM message_reactions WHERE message_id = gc.id) AS reactions
                FROM group_chats gc
                JOIN users u ON gc.user_id = u.id
                WHERE gc.group_id = ? AND gc.id > ?
                ORDER BY gc.sent_at ASC
                LIMIT 50
            ");
            $stmt->bind_param("ii", $group_id, $last_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }

            echo json_encode($messages);
            break;

        case 'toggle_reaction':
            if (empty($_POST['message_id']) || empty($_POST['emoji'])) {
                throw new Exception('Invalid reaction data');
            }

            $message_id = intval($_POST['message_id']);
            $emoji = trim($_POST['emoji']);

            // Check if already reacted
            $stmt = $conn->prepare("SELECT id FROM message_reactions WHERE message_id = ? AND reactor_id = ? AND reaction = ?");
            $stmt->bind_param("iis", $message_id, $user_id, $emoji);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                // Remove reaction
                $stmt = $conn->prepare("DELETE FROM message_reactions WHERE id = ?");
                $stmt->bind_param("i", $existing['id']);
                $stmt->execute();
                $stmt->close();
            } else {
                // Add reaction
                $stmt = $conn->prepare("INSERT INTO message_reactions (message_id, reactor_id, reaction) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $message_id, $user_id, $emoji);
                $stmt->execute();
                $stmt->close();
            }

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
