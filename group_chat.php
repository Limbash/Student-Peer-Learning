<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$group_id = intval($_GET['group_id'] ?? 0);
if (!$group_id) {
    header('Location: discussions.php');
    exit;
}

include 'includes/db.php';

// Verify user is member of group
$stmt = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $group_id);
$stmt->execute();
if (!$stmt->get_result()->num_rows) {
    header("Location: group_view.php?id=$group_id");
    exit;
}
$stmt->close();

// Get group info
$group = $conn->query("SELECT name FROM groups WHERE id = $group_id")->fetch_assoc();

// Get chat messages (last 50)
$messages = $conn->query("
    SELECT gc.*, u.name AS user_name, u.profile_pic
    FROM group_chats gc
    JOIN users u ON gc.user_id = u.id
    WHERE gc.group_id = $group_id
    ORDER BY gc.sent_at DESC
    LIMIT 50
");

$conn->close();
?>

<?php include 'partials/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Group Chat: <?= htmlspecialchars($group['name']) ?></h2>
        <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Group
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Chat Messages Container -->
            <div id="chat-messages" class="p-3" style="height: 500px; overflow-y: auto;">
                <?php if ($messages->num_rows > 0): ?>
                    <?php while($message = $messages->fetch_assoc()): ?>
                        <div class="mb-3 d-flex <?= $message['user_id'] == $_SESSION['user_id'] ? 'justify-content-end' : 'justify-content-start' ?>">
                            <div class="d-flex <?= $message['user_id'] == $_SESSION['user_id'] ? 'flex-row-reverse' : '' ?>">
                                <img src="<?= htmlspecialchars($message['profile_pic'] ?? 'images/default.png') ?>" 
                                     class="rounded-circle me-2" width="40" height="40" alt="<?= htmlspecialchars($message['user_name']) ?>">
                                <div>
                                    <div class="bg-<?= $message['user_id'] == $_SESSION['user_id'] ? 'primary' : 'light' ?> text-<?= $message['user_id'] == $_SESSION['user_id'] ? 'white' : 'dark' ?> p-3 rounded-3">
                                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                                    </div>
                                    <small class="text-muted d-block <?= $message['user_id'] == $_SESSION['user_id'] ? 'text-end' : '' ?>">
                                        <?= htmlspecialchars($message['user_name']) ?> • 
                                        <?= date('M j, g:i a', strtotime($message['sent_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-chat-square-text fs-1"></i>
                        <p class="mt-3">No messages yet. Be the first to start the conversation!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Message Input Form -->
            <div class="border-top p-3 bg-light">
                <form id="chat-form" class="d-flex">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">
                    <input type="text" name="message" class="form-control me-2" placeholder="Type your message..." required>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Send
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<!-- Chat JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('chat-form');
    const chatMessages = document.getElementById('chat-messages');
    
    // Scroll to bottom of chat
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    // Handle message submission
    chatForm.addEventListener('submit', function(e) {
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
                // Message will appear via polling
            }
        });
    });
    
    // Poll for new messages every 3 seconds
    setInterval(function() {
        fetch(`chat_ajax.php?action=get_messages&group_id=<?= $group_id ?>&last_id=${getLastMessageId()}`)
        .then(response => response.json())
        .then(messages => {
            if (messages.length > 0) {
                messages.forEach(message => {
                    const isCurrentUser = message.user_id == <?= $_SESSION['user_id'] ?>;
                    const messageHtml = `
                        <div class="mb-3 d-flex ${isCurrentUser ? 'justify-content-end' : 'justify-content-start'}">
                            <div class="d-flex ${isCurrentUser ? 'flex-row-reverse' : ''}">
                                <img src="${message.profile_pic || 'images/default.png'}" 
                                     class="rounded-circle me-2" width="40" height="40" alt="${message.user_name}">
                                <div>
                                    <div class="bg-${isCurrentUser ? 'primary' : 'light'} text-${isCurrentUser ? 'white' : 'dark'} p-3 rounded-3">
                                        ${escapeHtml(message.message).replace(/\n/g, '<br>')}
                                    </div>
                                    <small class="text-muted d-block ${isCurrentUser ? 'text-end' : ''}">
                                        ${message.user_name} • 
                                        ${formatDate(message.sent_at)}
                                    </small>
                                </div>
                            </div>
                        </div>
                    `;
                    chatMessages.insertAdjacentHTML('beforeend', messageHtml);
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    }, 3000);
    
    function getLastMessageId() {
        const lastMessage = chatMessages.lastElementChild;
        return lastMessage ? lastMessage.dataset.messageId || '0' : '0';
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }
});
</script>