<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'send_message':
            if (!isset($_SESSION['user_id']) || empty($_POST['group_id']) || empty($_POST['message'])) {
                throw new Exception('Invalid request');
            }

            $group_id = intval($_POST['group_id']);
            $user_id = $_SESSION['user_id'];
            $message = trim($_POST['message']);

            // Verify user is member of group
            $stmt = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ?");
            $stmt->bind_param("ii", $user_id, $group_id);
            $stmt->execute();
            if (!$stmt->get_result()->num_rows) {
                throw new Exception('Not a group member');
            }
            $stmt->close();

            // Insert message
            $stmt = $conn->prepare("INSERT INTO group_chats (group_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $group_id, $user_id, $message);
            $stmt->execute();
            $message_id = $stmt->insert_id;
            $stmt->close();

            // Update group's last message time
            $conn->query("UPDATE groups SET last_message_at = NOW() WHERE id = $group_id");

            echo json_encode(['success' => true, 'message_id' => $message_id]);
            break;

        case 'get_messages':
            if (!isset($_SESSION['user_id']) || empty($_GET['group_id'])) {
                throw new Exception('Invalid request');
            }

            $group_id = intval($_GET['group_id']);
            $last_id = intval($_GET['last_id'] ?? 0);

            // Verify user is member of group
            $stmt = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $group_id);
            $stmt->execute();
            if (!$stmt->get_result()->num_rows) {
                throw new Exception('Not a group member');
            }
            $stmt->close();

            // Get new messages
            $query = "SELECT gc.*, u.name AS user_name, u.profile_pic 
                      FROM group_chats gc
                      JOIN users u ON gc.user_id = u.id
                      WHERE gc.group_id = $group_id";
            
            if ($last_id > 0) {
                $query .= " AND gc.id > $last_id";
            }
            
            $query .= " ORDER BY gc.sent_at ASC";
            $messages = $conn->query($query);

            $result = [];
            while ($message = $messages->fetch_assoc()) {
                $result[] = $message;
            }

            echo json_encode($result);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>