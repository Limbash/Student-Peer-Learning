<?php
session_start();
require_once 'includes/notifications.php';
$notification = new Notification($conn);
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

$allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
$max_size = 10 * 1024 * 1024; // 10MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = intval($_POST['group_id']);
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    // Verify user is member of group
    $stmt = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ?");
    $stmt->bind_param("ii", $user_id, $group_id);
    $stmt->execute();
    $is_member = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    if (!$is_member) {
        $_SESSION['error'] = "You must be a member of the group to upload resources.";
        header("Location: group_view.php?id=$group_id");
        exit;
    }
    
    // Handle file upload
    if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resource_file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            $_SESSION['error'] = "File type not allowed. Only PDF, DOC, PPT, XLS, JPG, PNG files are accepted.";
            header("Location: group_view.php?id=$group_id");
            exit;
        }
        
        if ($file['size'] > $max_size) {
            $_SESSION['error'] = "File is too large. Maximum size is 10MB.";
            header("Location: group_view.php?id=$group_id");
            exit;
        }
        
        // Create uploads directory if not exists
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . preg_replace('/[^a-z0-9\.]/i', '_', $file['name']);
        $target_path = 'uploads/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Save to database
            $stmt = $conn->prepare("INSERT INTO resources (group_id, uploaded_by, title, filename, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $group_id, $user_id, $title, $filename, $description);
            
            if ($stmt->execute()) {
                // Record activity
                $activity_stmt = $conn->prepare("INSERT INTO group_activities (group_id, user_id, activity_type, activity_details) VALUES (?, ?, 'resource', ?)");
                $activity_details = "Uploaded: " . $title;
                $activity_stmt->bind_param("iis", $group_id, $user_id, $activity_details);
                $activity_stmt->execute();
                $activity_stmt->close();

                // Get group name for notification
                $group_name = $conn->query("SELECT name FROM groups WHERE id = $group_id")->fetch_assoc()['name'];
                $notification_title = "New Resource in $group_name";
                $notification_message = "A new resource '$title' has been uploaded to the group '$group_name'.";
                $notification->notifyGroupMembers(
                    $group_id, 
                    $user_id, 
                    'new_resource', 
                    $notification_title, 
                    $notification_message
                );
                
                $_SESSION['success'] = "Resource uploaded successfully!";
                
                // Update last_accessed for the group member
                $conn->query("UPDATE group_members SET last_accessed = NOW() WHERE user_id = $user_id AND group_id = $group_id");
                
                // Redirect to resources page to show the new upload
                header("Location: resources.php");
                exit;
            } else {
                $_SESSION['error'] = "Error saving resource to database.";
                unlink($target_path); // Delete the uploaded file
                header("Location: group_view.php?id=$group_id");
                exit;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Error uploading file.";
            header("Location: group_view.php?id=$group_id");
            exit;
        }
    } else {
        $_SESSION['error'] = "No file uploaded or upload error occurred.";
        header("Location: group_view.php?id=$group_id");
        exit;
    }
}

// If not a POST request, redirect to discussions
header("Location: discussions.php");
exit;
?>