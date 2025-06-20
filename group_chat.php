<?php
// Only one session_start() at the very beginning
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

// Fixed: Removed last_message_at from query since it's not in your schema
$stmt = $conn->prepare("SELECT name FROM groups WHERE id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    header("Location: discussions.php");
    exit;
}

// Get chat messages
$stmt = $conn->prepare("
    SELECT gc.*, u.name AS user_name, u.profile_pic,
           (SELECT GROUP_CONCAT(CONCAT(reaction, ':', reactor_id) SEPARATOR ',') 
            FROM message_reactions WHERE message_id = gc.id) AS reactions
    FROM group_chats gc
    JOIN users u ON gc.user_id = u.id
    WHERE gc.group_id = ?
    ORDER BY gc.sent_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$messages = $stmt->get_result();
$stmt->close();

$conn->close();
?>

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
$group = $conn->query("SELECT name, last_message_at FROM groups WHERE id = $group_id")->fetch_assoc();

// Get chat messages (last 50) with reactions
$messages = $conn->query("
    SELECT gc.*, u.name AS user_name, u.profile_pic,
           (SELECT GROUP_CONCAT(CONCAT(reaction, ':', reactor_id) SEPARATOR ',') 
            FROM message_reactions WHERE message_id = gc.id) AS reactions
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
                        <div class="mb-3 d-flex <?= $message['user_id'] == $_SESSION['user_id'] ? 'justify-content-end' : 'justify-content-start' ?>" data-message-id="<?= $message['id'] ?>">
                            <div class="d-flex <?= $message['user_id'] == $_SESSION['user_id'] ? 'flex-row-reverse' : '' ?>">
                                <img src="<?= htmlspecialchars($message['profile_pic'] ?? 'images/default.png') ?>" 
                                     class="rounded-circle me-2" width="40" height="40" alt="<?= htmlspecialchars($message['user_name']) ?>">
                                <div>
                                    <div class="bg-<?= $message['user_id'] == $_SESSION['user_id'] ? 'primary' : 'light' ?> text-<?= $message['user_id'] == $_SESSION['user_id'] ? 'white' : 'dark' ?> p-3 rounded-3 position-relative">
                                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                                        <!-- File Preview (if attachment exists) -->
                                        <?php if (!empty($message['attachment'])): ?>
                                            <div class="mt-2">
                                                <a href="uploads/<?= htmlspecialchars($message['attachment']) ?>" target="_blank" class="d-block">
                                                    <i class="bi bi-file-earmark"></i> <?= htmlspecialchars($message['attachment']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <!-- Reactions -->
                                        <?php if (!empty($message['reactions'])): ?>
                                            <div class="reactions mt-2">
                                                <?php foreach (explode(',', $message['reactions']) as $reaction): ?>
                                                    <?php list($emoji, $reactor_id) = explode(':', $reaction); ?>
                                                    <span class="badge bg-light text-dark me-1 reaction-emoji" data-emoji="<?= $emoji ?>" data-message-id="<?= $message['id'] ?>">
                                                        <?= $emoji ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
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
                <form id="chat-form" class="d-flex" enctype="multipart/form-data">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="text" name="message" class="form-control me-2" placeholder="Type your message...">
                    <label class="btn btn-outline-secondary me-2">
                        <i class="bi bi-paperclip"></i>
                        <input type="file" name="attachment" id="attachment" class="d-none" accept=".pdf,.jpg,.jpeg,.png">
                    </label>
                    <button type="submit" class="btn btn-primary" id="send-button">
                        <i class="bi bi-send"></i> Send
                    </button>
                </form>
                <div id="file-preview" class="mt-2 d-none">
                    <span class="badge bg-info">
                        <span id="file-name"></span>
                        <button class="btn-close btn-close-white ms-1" id="remove-file"></button>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<!-- Emoji Picker (for reactions) -->
<div id="emoji-picker" class="position-fixed bg-white p-3 shadow rounded d-none">
    <div class="emoji-list">
        <?php foreach (['👍', '❤️', '😂', '😮', '😢'] as $emoji): ?>
            <span class="emoji-option fs-4 me-2" data-emoji="<?= $emoji ?>"><?= $emoji ?></span>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('chat-form');
    const chatMessages = document.getElementById('chat-messages');
    const attachmentInput = document.getElementById('attachment');
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const removeFileBtn = document.getElementById('remove-file');
    const sendButton = document.getElementById('send-button');
    const emojiPicker = document.getElementById('emoji-picker');

    // Scroll to bottom initially
    chatMessages.scrollTop = chatMessages.scrollHeight;

    // Handle file selection
    attachmentInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            fileName.textContent = e.target.files[0].name;
            filePreview.classList.remove('d-none');
        }
    });

    // Remove file
    removeFileBtn.addEventListener('click', function() {
        attachmentInput.value = '';
        filePreview.classList.add('d-none');
    });

    // Handle message submission
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        sendButton.disabled = true;
        sendButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Sending...';

        const formData = new FormData(this);
        formData.append('action', 'send_message');

        fetch('chat_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                chatForm.reset();
                filePreview.classList.add('d-none');
            }
        })
        .finally(() => {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="bi bi-send"></i> Send';
        });
    });

    // Poll for new messages every 2 seconds
    setInterval(function() {
        fetch(`chat_ajax.php?action=get_messages&group_id=<?= $group_id ?>&last_id=${getLastMessageId()}`)
        .then(response => response.json())
        .then(messages => {
            if (messages.length > 0) {
                messages.forEach(message => {
                    // Check if message already exists
                    if (!document.querySelector(`[data-message-id="${message.id}"]`)) {
                        const isCurrentUser = message.user_id == <?= $_SESSION['user_id'] ?>;
                        const messageHtml = buildMessageHtml(message, isCurrentUser);
                        chatMessages.insertAdjacentHTML('beforeend', messageHtml);
                    }
                });

                // Auto-scroll only if near bottom
                const isNearBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
                if (isNearBottom) chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    }, 2000);

    // Reaction click handler (delegated)
    chatMessages.addEventListener('click', function(e) {
        if (e.target.classList.contains('reaction-emoji')) {
            const messageId = e.target.dataset.messageId;
            const emoji = e.target.dataset.emoji;
            toggleReaction(messageId, emoji);
        }
    });

    // Helper functions
    function getLastMessageId() {
        const messages = chatMessages.querySelectorAll('[data-message-id]');
        return messages.length ? messages[messages.length - 1].dataset.messageId : '0';
    }

    function buildMessageHtml(message, isCurrentUser) {
        let fileHtml = '';
        if (message.attachment) {
            fileHtml = `
                <div class="mt-2">
                    <a href="uploads/${escapeHtml(message.attachment)}" target="_blank" class="d-block">
                        <i class="bi bi-file-earmark"></i> ${escapeHtml(message.attachment)}
                    </a>
                </div>
            `;
        }

        let reactionsHtml = '';
        if (message.reactions) {
            reactionsHtml = `
                <div class="reactions mt-2">
                    ${message.reactions.split(',').map(reaction => {
                        const [emoji, reactor_id] = reaction.split(':');
                        return `
                            <span class="badge bg-light text-dark me-1 reaction-emoji" 
                                  data-emoji="${emoji}" 
                                  data-message-id="${message.id}">
                                ${emoji}
                            </span>
                        `;
                    }).join('')}
                </div>
            `;
        }

        return `
            <div class="mb-3 d-flex ${isCurrentUser ? 'justify-content-end' : 'justify-content-start'}" data-message-id="${message.id}">
                <div class="d-flex ${isCurrentUser ? 'flex-row-reverse' : ''}">
                    <img src="${escapeHtml(message.profile_pic || 'images/default.png')}" 
                         class="rounded-circle me-2" width="40" height="40" alt="${escapeHtml(message.user_name)}">
                    <div>
                        <div class="bg-${isCurrentUser ? 'primary' : 'light'} text-${isCurrentUser ? 'white' : 'dark'} p-3 rounded-3 position-relative">
                            ${escapeHtml(message.message).replace(/\n/g, '<br>')}
                            ${fileHtml}
                            ${reactionsHtml}
                        </div>
                        <small class="text-muted d-block ${isCurrentUser ? 'text-end' : ''}">
                            ${escapeHtml(message.user_name)} • 
                            ${formatDate(message.sent_at)}
                        </small>
                    </div>
                </div>
            </div>
        `;
    }

    function toggleReaction(messageId, emoji) {
        fetch('chat_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_reaction&message_id=${messageId}&emoji=${encodeURIComponent(emoji)}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh messages
                const lastId = getLastMessageId();
                fetch(`chat_ajax.php?action=get_messages&group_id=<?= $group_id ?>&last_id=${lastId}`)
                .then(response => response.json())
                .then(messages => {
                    const updatedMessage = messages.find(m => m.id == messageId);
                    if (updatedMessage) {
                        const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
                        if (messageEl) {
                            const isCurrentUser = updatedMessage.user_id == <?= $_SESSION['user_id'] ?>;
                            messageEl.outerHTML = buildMessageHtml(updatedMessage, isCurrentUser);
                        }
                    }
                });
            }
        });
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