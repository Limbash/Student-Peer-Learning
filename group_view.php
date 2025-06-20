<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

$group_id = intval($_GET['id'] ?? 0);
if (!$group_id) {
    die("Invalid group ID.");
}

// Check if user is a member and their role
$user_id = $_SESSION['user_id'];
$is_member = false;
$is_admin = false;
$is_creator = false;

$member_stmt = $conn->prepare("SELECT is_admin, is_creator FROM group_members WHERE user_id = ? AND group_id = ?");
$member_stmt->bind_param("ii", $user_id, $group_id);
$member_stmt->execute();
$member_stmt->bind_result($is_admin, $is_creator);
$is_member = $member_stmt->fetch();
$member_stmt->close();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['join_group'])) {
        $stmt = $conn->prepare("INSERT INTO group_members (user_id, group_id, is_admin, is_creator) VALUES (?, ?, FALSE, FALSE)");
        $stmt->bind_param("ii", $user_id, $group_id);
        
        if ($stmt->execute()) {
            // Record activity
            $activity_stmt = $conn->prepare("INSERT INTO group_activities (group_id, user_id, activity_type) VALUES (?, ?, 'join')");
            $activity_stmt->bind_param("ii", $group_id, $user_id);
            $activity_stmt->execute();
            $activity_stmt->close();
            
            $_SESSION['success'] = "You have joined the group successfully!";
            $is_member = true;
        } else {
            $_SESSION['error'] = "Error joining the group.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['leave_group'])) {
        $stmt = $conn->prepare("DELETE FROM group_members WHERE user_id = ? AND group_id = ?");
        $stmt->bind_param("ii", $user_id, $group_id);
        
        if ($stmt->execute()) {
            // Record activity
            $activity_stmt = $conn->prepare("INSERT INTO group_activities (group_id, user_id, activity_type) VALUES (?, ?, 'leave')");
            $activity_stmt->bind_param("ii", $group_id, $user_id);
            $activity_stmt->execute();
            $activity_stmt->close();
            
            $_SESSION['success'] = "You have left the group.";
            $is_member = false;
        } else {
            $_SESSION['error'] = "Error leaving the group.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['delete_group'])) {
        // Verify user is admin/creator before deleting
        if ($is_admin || $is_creator) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete group activities
                $stmt = $conn->prepare("DELETE FROM group_activities WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();
                
                // Delete group members
                $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();
                
                // Delete group resources
                $stmt = $conn->prepare("DELETE FROM resources WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();
                
                // Delete group chats
                $stmt = $conn->prepare("DELETE FROM group_chats WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();
                
                // Finally delete the group
                $stmt = $conn->prepare("DELETE FROM groups WHERE id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                $_SESSION['success'] = "Group deleted successfully.";
                header("Location: discussions.php");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error deleting group: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "You don't have permission to delete this group.";
        }
    }
    
    // Update last accessed time
    if ($is_member) {
        $conn->query("UPDATE group_members SET last_accessed = NOW() WHERE user_id = $user_id AND group_id = $group_id");
    }
    
    header("Location: group_view.php?id=$group_id");
    exit;
}

// Fetch group details
$stmt = $conn->prepare("SELECT g.*, u.name AS creator_name, u.profile_pic AS creator_pic 
                       FROM groups g 
                       JOIN users u ON g.created_by = u.id 
                       WHERE g.id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    die("Group not found.");
}

// Fetch members with their roles
$members = $conn->query("
    SELECT u.id, u.name, u.profile_pic, gm.is_admin, gm.is_creator, gm.joined_at
    FROM group_members gm
    JOIN users u ON gm.user_id = u.id
    WHERE gm.group_id = $group_id
    ORDER BY gm.is_creator DESC, gm.is_admin DESC, gm.joined_at ASC
");

// Fetch recent activities
$activities = $conn->query("
    SELECT ga.*, u.name AS user_name, u.profile_pic
    FROM group_activities ga
    JOIN users u ON ga.user_id = u.id
    WHERE ga.group_id = $group_id
    ORDER BY ga.created_at DESC
    LIMIT 10
");

// Fetch resources
$resources = $conn->query("
    SELECT r.*, u.name AS uploaded_by_name
    FROM resources r
    LEFT JOIN users u ON r.uploaded_by = u.id
    WHERE r.group_id = $group_id
    ORDER BY r.uploaded_at DESC
");

// Fetch last 5 chat messages
$chat_messages = $is_member ? $conn->query("
    SELECT gc.*, u.name AS user_name, u.profile_pic
    FROM group_chats gc
    JOIN users u ON gc.user_id = u.id
    WHERE gc.group_id = $group_id
    ORDER BY gc.sent_at DESC
    LIMIT 5
") : null;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group['name']) ?> - Peer Learning Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .group-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 2rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .member-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .admin-badge {
            background-color: #f6c23e;
            color: #000;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .creator-badge {
            background-color: #4e73df;
            color: #fff;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .activity-item {
            border-left: 3px solid #4e73df;
            padding-left: 10px;
            margin-bottom: 10px;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #4e73df;
        }
        .chat-container {
            position: fixed;
            bottom: 0;
            right: 20px;
            width: 350px;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .chat-header {
            background: #4e73df;
            color: white;
            padding: 10px 15px;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
        }
        .chat-body {
            background: white;
            height: 300px;
            overflow-y: auto;
            display: none;
        }
        .chat-message {
            margin-bottom: 10px;
        }
        .chat-input {
            border-top: 1px solid #eee;
            padding: 10px;
            background: #f8f9fa;
            display: none;
        }
        .chat-toggle {
            background: none;
            border: none;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'partials/navbar.php'; ?>
    
    <div class="container py-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="group-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2><?= htmlspecialchars($group['name']) ?></h2>
                        <p class="mb-0"><?= ucfirst(htmlspecialchars($group['category'])) ?> group</p>
                    </div>
                    
                    <?php if ($is_member): ?>
                        <div>
                            <a href="group_chat.php?group_id=<?= $group_id ?>" class="btn btn-light btn-sm me-2">
                                <i class="bi bi-chat-left-text me-1"></i> Open Chat
                            </a>
                            
                            <?php if ($is_admin || $is_creator): ?>
                                <!-- Delete Group button for admins/creators -->
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="delete_group" value="1">
                                    <button type="submit" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('WARNING: This will permanently delete the group and all its content! Are you sure?')">
                                        <i class="bi bi-trash me-1"></i> Delete Group
                                    </button>
                                </form>
                            <?php else: ?>
                                <!-- Leave Group button for regular members -->
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="leave_group" value="1">
                                    <button type="submit" class="btn btn-outline-light btn-sm" 
                                            onclick="return confirm('Are you sure you want to leave this group?')">
                                        <i class="bi bi-box-arrow-right me-1"></i> Leave Group
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Join Group button for non-members -->
                        <form method="POST">
                            <input type="hidden" name="join_group" value="1">
                            <button type="submit" class="btn btn-light btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> Join Group
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($group['description'])) ?></p>
                
                <div class="d-flex align-items-center text-muted">
                    <img src="<?= htmlspecialchars($group['creator_pic'] ?: 'images/default.png') ?>" 
                         class="rounded-circle me-2" width="30" height="30" alt="Creator">
                    <small>Created by <?= htmlspecialchars($group['creator_name']) ?> on <?= date('M j, Y', strtotime($group['created_at'])) ?></small>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="groupTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button">
                    <i class="bi bi-people me-1"></i> Members
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="resources-tab" data-bs-toggle="tab" data-bs-target="#resources" type="button">
                    <i class="bi bi-folder me-1"></i> Resources
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                    <i class="bi bi-clock-history me-1"></i> Recent Activity
                </button>
            </li>
        </ul>

        <div class="tab-content" id="groupTabsContent">
            <!-- Members Tab -->
            <div class="tab-pane fade show active" id="members" role="tabpanel">
                <div class="row">
                    <?php while ($member = $members->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body d-flex align-items-center">
                                    <img src="<?= htmlspecialchars($member['profile_pic'] ?: 'images/default.png') ?>" 
                                         class="member-avatar me-3" alt="<?= htmlspecialchars($member['name']) ?>">
                                    <div>
                                        <h6 class="mb-0">
                                            <?= htmlspecialchars($member['name']) ?>
                                            <?php if ($member['is_creator']): ?>
                                                <span class="creator-badge">CREATOR</span>
                                            <?php elseif ($member['is_admin']): ?>
                                                <span class="admin-badge">ADMIN</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">Joined <?= date('M j, Y', strtotime($member['joined_at'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Resources Tab -->
            <div class="tab-pane fade" id="resources" role="tabpanel">
                <?php if ($is_member): ?>
                    <div class="mb-4">
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadResourceModal">
                            <i class="bi bi-upload me-1"></i> Upload Resource
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($resources->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($resource = $resources->fetch_assoc()): ?>
                            <div class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($resource['title']) ?></h6>
                                    <small><?= date('M j, Y', strtotime($resource['uploaded_at'])) ?></small>
                                </div>
                                <p class="mb-1"><?= nl2br(htmlspecialchars($resource['description'])) ?></p>
                                <small class="text-muted">Uploaded by <?= htmlspecialchars($resource['uploaded_by_name'] ?: 'System') ?></small>
                                <div class="mt-2">
                                    <a href="uploads/<?= htmlspecialchars($resource['filename']) ?>" 
                                       class="btn btn-sm btn-outline-primary" download>
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                    <?php if ($is_admin || $is_creator): ?>
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash me-1"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No resources shared in this group yet.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activity Tab -->
            <div class="tab-pane fade" id="activity" role="tabpanel">
                <?php if ($activities->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <div class="list-group-item border-0">
                                <div class="d-flex">
                                    <img src="<?= htmlspecialchars($activity['profile_pic'] ?: 'images/default.png') ?>" 
                                         class="rounded-circle me-3" width="40" height="40" alt="User">
                                    <div>
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-0"><?= htmlspecialchars($activity['user_name']) ?></h6>
                                            <small class="text-muted"><?= date('M j, Y g:i a', strtotime($activity['created_at'])) ?></small>
                                        </div>
                                        <div class="activity-item">
                                            <?php switch($activity['activity_type']):
                                                case 'join': ?>
                                                    <span class="text-success"><i class="bi bi-person-plus"></i> Joined the group</span>
                                                    <?php break; ?>
                                                <?php case 'leave': ?>
                                                    <span class="text-danger"><i class="bi bi-person-dash"></i> Left the group</span>
                                                    <?php break; ?>
                                                <?php case 'post': ?>
                                                    <span class="text-primary"><i class="bi bi-chat-square-text"></i> Posted in discussions</span>
                                                    <?php break; ?>
                                                <?php case 'resource': ?>
                                                    <span class="text-info"><i class="bi bi-file-earmark"></i> Shared a resource</span>
                                                    <?php break; ?>
                                            <?php endswitch; ?>
                                            <?php if (!empty($activity['activity_details'])): ?>
                                                <p class="mb-0 mt-1"><?= htmlspecialchars($activity['activity_details']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No recent activity in this group.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Resource Upload Modal -->
    <div class="modal fade" id="uploadResourceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="upload_resource.php">
                    <div class="modal-body">
                        <input type="hidden" name="group_id" value="<?= $group_id ?>">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" name="resource_file" class="form-control" required>
                            <small class="text-muted">Max size: 10MB (PDF, DOC, PPT, XLS, JPG, PNG)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Group Chat Widget -->
    <?php if ($is_member): ?>
    <div class="chat-container">
        <div class="chat-header d-flex justify-content-between align-items-center">
            <button class="chat-toggle">
                <i class="bi bi-chat-left-text me-2"></i> Group Chat
            </button>
            <a href="group_chat.php?group_id=<?= $group_id ?>" class="text-white">
                <i class="bi bi-box-arrow-up-right"></i>
            </a>
        </div>
        <div class="chat-body p-3" id="chat-widget-body">
            <?php if ($chat_messages && $chat_messages->num_rows > 0): ?>
                <?php while($message = $chat_messages->fetch_assoc()): ?>
                    <div class="chat-message">
                        <div class="d-flex <?= $message['user_id'] == $_SESSION['user_id'] ? 'justify-content-end' : 'justify-content-start' ?>">
                            <div class="d-flex <?= $message['user_id'] == $_SESSION['user_id'] ? 'flex-row-reverse' : '' ?>">
                                <img src="<?= htmlspecialchars($message['profile_pic'] ?? 'images/default.png') ?>" 
                                     class="rounded-circle me-2" width="30" height="30" alt="<?= htmlspecialchars($message['user_name']) ?>">
                                <div>
                                    <div class="bg-<?= $message['user_id'] == $_SESSION['user_id'] ? 'primary' : 'light' ?> text-<?= $message['user_id'] == $_SESSION['user_id'] ? 'white' : 'dark' ?> p-2 rounded-3">
                                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                                    </div>
                                    <small class="text-muted d-block <?= $message['user_id'] == $_SESSION['user_id'] ? 'text-end' : '' ?>">
                                        <?= htmlspecialchars($message['user_name']) ?> • 
                                        <?= date('g:i a', strtotime($message['sent_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-3 text-muted">
                    <i class="bi bi-chat-square-text"></i>
                    <p class="mt-2">No messages yet</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="chat-input">
            <form id="chat-widget-form" class="d-flex">
                <input type="hidden" name="group_id" value="<?= $group_id ?>">
                <input type="text" name="message" class="form-control me-2" placeholder="Type a message..." required>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-send"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
    // Toggle chat widget
    document.querySelector('.chat-toggle').addEventListener('click', function() {
        const chatBody = document.querySelector('.chat-body');
        const chatInput = document.querySelector('.chat-input');
        
        if (chatBody.style.display === 'block') {
            chatBody.style.display = 'none';
            chatInput.style.display = 'none';
        } else {
            chatBody.style.display = 'block';
            chatInput.style.display = 'block';
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    });

    // Handle chat form submission
    document.getElementById('chat-widget-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'send_message');
        
        fetch('chat_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.reset();
                location.reload();
            }
        });
    });
    </script>
    <?php endif; ?>

    <?php include 'partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>