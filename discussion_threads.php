<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';
include 'includes/notifications.php';
$notification = new Notification($conn);

$group_id = intval($_GET['group_id'] ?? 0);
if (!$group_id) {
    header('Location: discussions.php');
    exit;
}

// Check if user is member
$user_id = $_SESSION['user_id'];
$is_member = $conn->query("SELECT 1 FROM group_members WHERE user_id = $user_id AND group_id = $group_id")->num_rows > 0;

if (!$is_member) {
    $_SESSION['error'] = "You must be a member of the group to view discussions.";
    header("Location: group_view.php?id=$group_id");
    exit;
}

// Get group info
$group = $conn->query("SELECT name FROM groups WHERE id = $group_id")->fetch_assoc();

// Handle new discussion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_discussion'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO discussions (group_id, user_id, title, content) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $group_id, $user_id, $title, $content);
        
        if ($stmt->execute()) {
            // Record activity
            $conn->query("INSERT INTO group_activities (group_id, user_id, activity_type, activity_details) 
                         VALUES ($group_id, $user_id, 'post', 'Started discussion: $title')");
            
            // Send notifications
            $notification_title = "New Discussion in {$group['name']}";
            $notification_message = "A new discussion '$title' has been started in the group '{$group['name']}'.";
            $notification->notifyGroupMembers(
                $group_id, 
                $user_id, 
                'new_discussion', 
                $notification_title, 
                $notification_message
            );
            
            $_SESSION['success'] = "Discussion started successfully!";
            header("Location: discussion_threads.php?group_id=$group_id");
            exit;
        } else {
            $_SESSION['error'] = "Error creating discussion.";
        }
        $stmt->close();
    }
}

// Handle new reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_reply'])) {
    $discussion_id = intval($_POST['discussion_id']);
    $content = trim($_POST['content']);
    
    if (empty($content)) {
        $_SESSION['error'] = "Reply content cannot be empty.";
    } else {
        $stmt = $conn->prepare("INSERT INTO discussion_replies (discussion_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $discussion_id, $user_id, $content);
        
        if ($stmt->execute()) {
            // Get discussion info for notification
            $discussion = $conn->query("
                SELECT d.title, d.user_id AS author_id, u.name AS author_name 
                FROM discussions d
                JOIN users u ON d.user_id = u.id
                WHERE d.id = $discussion_id
            ")->fetch_assoc();
            
            // Record activity
            $conn->query("INSERT INTO group_activities (group_id, user_id, activity_type, activity_details) 
                         VALUES ($group_id, $user_id, 'post', 'Replied to discussion: {$discussion['title']}')");
            
            // Send notification to discussion author (if different from replier)
            if ($discussion['author_id'] != $user_id) {
                $notification_title = "New Reply to Your Discussion";
                $notification_message = "{$_SESSION['user_name']} has replied to your discussion '{$discussion['title']}' in group '{$group['name']}'.";
                
                // In-app notification
                $notification->create(
                    $discussion['author_id'], 
                    $group_id, 
                    'new_reply', 
                    $notification_title, 
                    $notification_message
                );
                
                // Email notification if enabled
                $prefs = json_decode($conn->query("
                    SELECT notification_prefs FROM users WHERE id = {$discussion['author_id']}
                ")->fetch_assoc()['notification_prefs'], true);
                
                if ($prefs['email'] && $prefs['new_reply']) {
                    $notification->sendEmailNotification(
                        $discussion['author_id'], 
                        $notification_title, 
                        $notification_message
                    );
                }
            }
            
            $_SESSION['success'] = "Reply posted successfully!";
            header("Location: discussion_threads.php?group_id=$group_id&discussion_id=$discussion_id");
            exit;
        } else {
            $_SESSION['error'] = "Error posting reply.";
        }
        $stmt->close();
    }
}

// Get all discussions in group
$discussions = $conn->query("
    SELECT d.*, u.name AS author_name, u.profile_pic AS author_pic,
           (SELECT COUNT(*) FROM discussion_replies WHERE discussion_id = d.id) AS reply_count
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    WHERE d.group_id = $group_id
    ORDER BY d.updated_at DESC
");

// Get specific discussion if requested
$current_discussion = null;
$replies = [];
if (isset($_GET['discussion_id'])) {
    $discussion_id = intval($_GET['discussion_id']);
    $current_discussion = $conn->query("
        SELECT d.*, u.name AS author_name, u.profile_pic AS author_pic
        FROM discussions d
        JOIN users u ON d.user_id = u.id
        WHERE d.id = $discussion_id AND d.group_id = $group_id
    ")->fetch_assoc();
    
    if ($current_discussion) {
        $replies = $conn->query("
            SELECT dr.*, u.name AS author_name, u.profile_pic AS author_pic
            FROM discussion_replies dr
            JOIN users u ON dr.user_id = u.id
            WHERE dr.discussion_id = $discussion_id
            ORDER BY dr.created_at ASC
        ");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussions - <?= htmlspecialchars($group['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .discussion-card {
            transition: all 0.3s ease;
            border-left: 3px solid #4e73df;
        }
        .discussion-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .reply-card {
            border-left: 2px solid #ddd;
            margin-left: 20px;
        }
        .author-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
        }
        .markdown-content {
            line-height: 1.6;
        }
        .markdown-content img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <?php include 'partials/navbar.php'; ?>
    
    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="discussions.php">Groups</a></li>
                <li class="breadcrumb-item"><a href="group_view.php?id=<?= $group_id ?>"><?= htmlspecialchars($group['name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Discussions</li>
            </ol>
        </nav>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-chat-left-text me-2"></i>Group Discussions</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDiscussionModal">
                <i class="bi bi-plus-circle me-1"></i> New Discussion
            </button>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="row">
            <!-- Discussion List -->
            <div class="col-md-5 col-lg-4">
                <div class="list-group mb-4">
                    <?php if ($discussions->num_rows > 0): ?>
                        <?php while($discussion = $discussions->fetch_assoc()): ?>
                            <a href="discussion_threads.php?group_id=<?= $group_id ?>&discussion_id=<?= $discussion['id'] ?>" 
                               class="list-group-item list-group-item-action <?= isset($current_discussion) && $current_discussion['id'] == $discussion['id'] ? 'active' : '' ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($discussion['title']) ?></h6>
                                    <small><?= $discussion['reply_count'] ?> <i class="bi bi-chat"></i></small>
                                </div>
                                <small>Started by <?= htmlspecialchars($discussion['author_name']) ?></small>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No discussions started yet. Be the first to start one!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Discussion Content -->
            <div class="col-md-7 col-lg-8">
                <?php if ($current_discussion): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4><?= htmlspecialchars($current_discussion['title']) ?></h4>
                            <small class="text-muted">
                                <?= date('M j, Y g:i a', strtotime($current_discussion['created_at'])) ?>
                            </small>
                        </div>
                        <div class="card-body">
                            <div class="d-flex mb-3">
                                <img src="<?= htmlspecialchars($current_discussion['author_pic'] ?: 'images/default.png') ?>" 
                                     class="author-avatar me-3" alt="<?= htmlspecialchars($current_discussion['author_name']) ?>">
                                <div>
                                    <h6 class="mb-0"><?= htmlspecialchars($current_discussion['author_name']) ?></h6>
                                    <small class="text-muted">Group Member</small>
                                </div>
                            </div>
                            <div class="markdown-content">
                                <?= nl2br(htmlspecialchars($current_discussion['content'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3">Replies (<?= $replies->num_rows ?>)</h5>
                    
                    <?php if ($replies->num_rows > 0): ?>
                        <?php while($reply = $replies->fetch_assoc()): ?>
                            <div class="card mb-3 reply-card">
                                <div class="card-body">
                                    <div class="d-flex mb-3">
                                        <img src="<?= htmlspecialchars($reply['author_pic'] ?: 'images/default.png') ?>" 
                                             class="author-avatar me-3" alt="<?= htmlspecialchars($reply['author_name']) ?>">
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($reply['author_name']) ?></h6>
                                            <small class="text-muted">
                                                <?= date('M j, Y g:i a', strtotime($reply['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="markdown-content">
                                        <?= nl2br(htmlspecialchars($reply['content'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No replies yet. Be the first to reply!
                        </div>
                    <?php endif; ?>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5>Post a Reply</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="new_reply" value="1">
                                <input type="hidden" name="discussion_id" value="<?= $current_discussion['id'] ?>">
                                <div class="mb-3">
                                    <textarea name="content" class="form-control" rows="4" placeholder="Write your reply..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Post Reply</button>
                            </form>
                        </div>
                    </div>
                <?php elseif ($discussions->num_rows > 0): ?>
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-chat-square-text" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Select a discussion to view</h4>
                        <p>Choose a discussion from the list to view its contents and participate</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- New Discussion Modal -->
    <div class="modal fade" id="newDiscussionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Start New Discussion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="new_discussion" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="form-control" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Start Discussion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>