<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
$stmt = $conn->prepare("SELECT name FROM groups WHERE id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
$group = $result->fetch_assoc();
$stmt->close();

if (!$group) {
    header("Location: discussions.php");
    exit;
}

// Get last 50 messages with reactions
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

<?php include 'partials/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Group Chat: <?= isset($group['name']) ? htmlspecialchars($group['name']) : 'Unknown Group' ?></h2>
        <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Group
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div id="chat-messages" class="p-3" style="height: 500px; overflow-y: auto;">
                <?php if ($messages->num_rows > 0): ?>
                    <?php while($message = $messages->fetch_assoc()): ?>
                        <div class="mb-3 d-flex <?= $message['user_id'] == $_SESSION['user_id'] ? 'justify-content-end' : 'justify-content-start' ?>" data-message-id="<?= $message['id'] ?>">
                            <div class="d-flex <?= $message['user_id'] == $_SESSION['user_id'] ? 'flex-row-reverse' : '' ?>">
                                <img src="<?= htmlspecialchars($message['profile_pic'] ?? 'images/default.png') ?>" class="rounded-circle me-2" width="40" height="40" alt="<?= htmlspecialchars($message['user_name']) ?>">
                                <div>
                                    <div class="bg-<?= $message['user_id'] == $_SESSION['user_id'] ? 'primary' : 'light' ?> text-<?= $message['user_id'] == $_SESSION['user_id'] ? 'white' : 'dark' ?> p-3 rounded-3 position-relative">
                                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                                        <?php if (!empty($message['attachment'])): ?>
                                            <div class="mt-2">
                                                <a href="uploads/<?= htmlspecialchars($message['attachment']) ?>" target="_blank">
                                                    <i class="bi bi-file-earmark"></i> <?= htmlspecialchars($message['attachment']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
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
                                        <?= htmlspecialchars($message['user_name']) ?> • <?= date('M j, g:i a', strtotime($message['sent_at'])) ?>
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

            <!-- Message Form -->
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

<!-- Emoji Picker -->
<div id="emoji-picker" class="position-fixed bg-white p-3 shadow rounded d-none">
    <div class="emoji-list">
        <?php foreach (['👍', '❤️', '😂', '😮', '😢'] as $emoji): ?>
            <span class="emoji-option fs-4 me-2" data-emoji="<?= $emoji ?>"><?= $emoji ?></span>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chatForm = document.getElementById('chat-form');
    const chatMessages = document.getElementById('chat-messages');
    const attachmentInput = document.getElementById('attachment');
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const removeFileBtn = document.getElementById('remove-file');
    const sendButton = document.getElementById('send-button');

    chatMessages.scrollTop = chatMessages.scrollHeight;

    attachmentInput.addEventListener('change', function (e) {
        if (e.target.files.length > 0) {
            fileName.textContent = e.target.files[0].name;
            filePreview.classList.remove('d-none');
        }
    });

    removeFileBtn.addEventListener('click', function () {
        attachmentInput.value = '';
        filePreview.classList.add('d-none');
    });

    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        sendButton.disabled = true;
        sendButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Sending...';

        const formData = new FormData(chatForm);
        formData.append('action', 'send_message');

        fetch('chat_ajax.php', {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if (data.success) {
                chatForm.reset();
                filePreview.classList.add('d-none');
            }
        }).finally(() => {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="bi bi-send"></i> Send';
        });
    });

    // Auto refresh new messages
    setInterval(() => {
        fetch(`chat_ajax.php?action=get_messages&group_id=<?= $group_id ?>&last_id=${getLastMessageId()}`)
            .then(res => res.json())
            .then(messages => {
                messages.forEach(msg => {
                    if (!document.querySelector(`[data-message-id="${msg.id}"]`)) {
                        const html = buildMessageHtml(msg, msg.user_id == <?= $_SESSION['user_id'] ?>);
                        chatMessages.insertAdjacentHTML('beforeend', html);
                    }
                });

                const nearBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
                if (nearBottom) chatMessages.scrollTop = chatMessages.scrollHeight;
            });
    }, 2000);

    function getLastMessageId() {
        const msgs = chatMessages.querySelectorAll('[data-message-id]');
        return msgs.length ? msgs[msgs.length - 1].dataset.messageId : 0;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function buildMessageHtml(message, isMe) {
        const reactionsHtml = message.reactions ? `
            <div class="reactions mt-2">
                ${message.reactions.split(',').map(r => {
                    const [emoji] = r.split(':');
                    return `<span class="badge bg-light text-dark me-1 reaction-emoji" data-emoji="${emoji}" data-message-id="${message.id}">${emoji}</span>`;
                }).join('')}
            </div>` : '';

        const attachment = message.attachment ? `
            <div class="mt-2">
                <a href="uploads/${escapeHtml(message.attachment)}" target="_blank">
                    <i class="bi bi-file-earmark"></i> ${escapeHtml(message.attachment)}
                </a>
            </div>` : '';

        return `
        <div class="mb-3 d-flex ${isMe ? 'justify-content-end' : 'justify-content-start'}" data-message-id="${message.id}">
            <div class="d-flex ${isMe ? 'flex-row-reverse' : ''}">
                <img src="${escapeHtml(message.profile_pic || 'images/default.png')}" class="rounded-circle me-2" width="40" height="40">
                <div>
                    <div class="bg-${isMe ? 'primary' : 'light'} text-${isMe ? 'white' : 'dark'} p-3 rounded-3">
                        ${escapeHtml(message.message).replace(/\n/g, '<br>')}
                        ${attachment}
                        ${reactionsHtml}
                    </div>
                    <small class="text-muted d-block ${isMe ? 'text-end' : ''}">
                        ${escapeHtml(message.user_name)} • ${new Date(message.sent_at).toLocaleString()}
                    </small>
                </div>
            </div>
        </div>`;
    }
});
</script>
