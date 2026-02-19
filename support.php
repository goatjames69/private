<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (empty(trim($user['email'] ?? ''))) {
    header('Location: /profile.php?add_email=1');
    exit;
}
$chats = getSupportChatsByUserId($user['id']);
usort($chats, function ($a, $b) {
    return strtotime($b['updated_at'] ?? $b['created_at'] ?? '') - strtotime($a['updated_at'] ?? $a['created_at'] ?? '');
});

$reasons = getSupportReasons();
$createError = '';
$createSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $reason = trim($_POST['reason'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (!in_array($reason, $reasons)) {
        $createError = 'Please select a valid reason.';
    } else {
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $allowed)) {
                if (!file_exists(SUPPORT_UPLOADS_DIR)) mkdir(SUPPORT_UPLOADS_DIR, 0755, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $name = 'sup_' . $user['id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], SUPPORT_UPLOADS_DIR . '/' . $name)) {
                    $imagePath = 'uploads/support/' . $name;
                }
            }
        }
        $chat = createSupportChat($user['id'], $reason, $message, $imagePath);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $pathBase = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';
        header('Location: ' . $scheme . '://' . $host . $pathBase . 'support.php?id=' . urlencode($chat['id']));
        exit;
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$pathBase = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';
$baseUrl = $scheme . '://' . $host . $pathBase;
$openChatId = isset($_GET['id']) ? trim($_GET['id']) : null;
$openChat = $openChatId ? getSupportChatById($openChatId) : null;
if ($openChat && ($openChat['user_id'] ?? '') !== $user['id']) {
    $openChat = null;
    $openChatId = null;
}
if ($openChatId && $openChat) {
    markSupportChatSeenByUser($openChatId);
    $openChat = getSupportChatById($openChatId);
}

$supportChatCss = @file_get_contents(__DIR__ . '/assets/css/support-chat.css');
if ($supportChatCss === false) $supportChatCss = '';
$supportChatCss = str_replace('</style>', '<' . '/style>', $supportChatCss);

$supportChatJs = @file_get_contents(__DIR__ . '/assets/js/support-chat.js');
if ($supportChatJs === false) $supportChatJs = '';
$supportChatJs = str_replace('</script>', '<' . '/script>', $supportChatJs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Support - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/user-dashboard.css">
    <link rel="stylesheet" href="assets/css/realtime.css">
    <style><?= $supportChatCss ?></style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="user-dashboard support-page">
    <div class="ud-container">
        <header class="ud-header">
            <h1><i class="fas fa-headset"></i> Support</h1>
            <p class="ud-greeting">Chat with us — we're here to help</p>
        </header>

        <?php if ($createError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($createError) ?></div>
        <?php endif; ?>

        <?php if (!$openChatId): ?>
        <section class="support-section">
            <div class="support-actions">
                <button type="button" class="btn btn-block support-new-btn" id="supportNewBtn">
                    <i class="fas fa-plus-circle"></i> New support ticket
                </button>
            </div>

            <div id="supportCreateForm" class="support-create-form card" style="display: none;">
                <h3 class="card-title"><i class="fas fa-ticket-alt"></i> Create ticket</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="create_ticket" value="1">
                    <div class="form-group">
                        <label>Reason *</label>
                        <select name="reason" class="form-control" required>
                            <option value="">Select reason</option>
                            <?php foreach ($reasons as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message (optional)</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Describe your issue..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Attach image (optional)</label>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                    </div>
                    <button type="submit" class="btn btn-block">Create ticket</button>
                </form>
            </div>

            <h3 class="support-list-title"><i class="fas fa-inbox"></i> Your tickets</h3>
            <?php if (empty($chats)): ?>
                <div class="support-empty">
                    <i class="fas fa-comments"></i>
                    <p>No support tickets yet. Create one if you need help.</p>
                </div>
            <?php else: ?>
                <ul class="support-ticket-list">
                    <?php foreach ($chats as $c): ?>
                        <li>
                            <a href="<?= htmlspecialchars($baseUrl) ?>support.php?id=<?= urlencode($c['id']) ?>" class="support-ticket-item <?= ($c['status'] ?? '') === 'closed' ? 'closed' : '' ?>">
                                <span class="support-ticket-reason"><?= htmlspecialchars($c['reason'] ?? 'Support') ?></span>
                                <span class="support-ticket-meta">
                                    <?= date('M j, g:i A', strtotime($c['updated_at'] ?? $c['created_at'] ?? '')) ?>
                                    <?php if (($c['status'] ?? '') === 'closed'): ?>
                                        <span class="support-badge closed">Closed</span>
                                    <?php else: ?>
                                        <span class="support-badge open">Open</span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php else: ?>
        <section class="support-chat-section" id="supportChatSection">
            <div class="support-chat-header">
                <a href="<?= htmlspecialchars($baseUrl) ?>support.php" class="support-back"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="support-chat-title">
                    <span><?= htmlspecialchars($openChat['reason'] ?? 'Support') ?></span>
                    <span class="support-chat-status <?= ($openChat['status'] ?? '') ?>"><?= ($openChat['status'] ?? 'open') ?></span>
                </div>
            </div>
            <div class="support-chat-messages" id="supportChatMessages">
                <?php foreach ($openChat['messages'] ?? [] as $m):
                    $isMine = ($m['from'] ?? '') === 'user';
                    $senderLabel = $isMine ? 'You' : getSupportMessageSenderLabel($m, $openChat);
                    $seenByOther = isSupportMessageSeenByOther($m, $openChat);
                ?>
                    <div class="support-msg <?= $isMine ? 'mine' : 'theirs' ?>" data-msg-id="<?= htmlspecialchars($m['id'] ?? '') ?>">
                        <div class="support-msg-bubble">
                            <div class="support-msg-sender"><?= htmlspecialchars($senderLabel) ?></div>
                            <?php if (!empty($m['text'])): ?>
                                <div class="support-msg-text"><?= nl2br(htmlspecialchars($m['text'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($m['image'])): ?>
                                <div class="support-msg-img"><img src="<?= htmlspecialchars($baseUrl . $m['image']) ?>" alt=""></div>
                            <?php endif; ?>
                            <div class="support-msg-meta">
                                <span class="support-msg-time"><?= date('g:i A', strtotime($m['date'] ?? '')) ?></span>
                                <?php if ($isMine): ?>
                                    <span class="support-msg-seen"><?= $seenByOther ? 'Seen' : 'Delivered' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (($openChat['status'] ?? '') === 'closed'): ?>
                <div class="support-chat-closed">
                    <p>This chat is closed. Open a new ticket if you need more help.</p>
                </div>
            <?php else: ?>
            <div class="support-chat-input-wrap">
                <form id="supportSendForm" class="support-chat-form">
                    <input type="hidden" name="chat_id" value="<?= htmlspecialchars($openChatId) ?>">
                    <div class="support-chat-input-row">
                        <button type="button" class="support-emoji-btn" id="supportEmojiBtn" title="Emoji"><i class="fas fa-smile"></i></button>
                        <input type="text" name="message" class="form-control support-msg-input" id="supportMsgInput" placeholder="Type a message..." autocomplete="off">
                        <label class="support-img-btn" title="Send image"><i class="fas fa-image"></i>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" id="supportImgInput">
                        </label>
                        <button type="submit" class="support-send-btn" title="Send"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <div class="support-footer-link">
            <a href="<?= htmlspecialchars($baseUrl) ?>dashboard.php" class="btn btn-secondary btn-block">← Back to Dashboard</a>
        </div>
    </div>

    <nav class="ud-nav">
        <a href="<?= htmlspecialchars($baseUrl) ?>dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>deposit.php"><i class="fas fa-wallet"></i> Deposit</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>games.php"><i class="fas fa-gamepad"></i> Games</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>support.php" class="active"><i class="fas fa-headset"></i> Support</a>
    </nav>

    <script>
    window.supportChatId = <?= json_encode($openChatId) ?>;
    window.supportIsAdmin = false;
    window.supportBaseUrl = <?= json_encode($baseUrl) ?>;
    </script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/toasts.js"></script>
    <script src="assets/js/realtime.js"></script>
    <script>
    (function() {
        if (window.JamesRealtime) window.JamesRealtime.auth(<?= json_encode($user['id'] ?? null) ?>, 'user');
    })();
    </script>
    <script><?= $supportChatJs ?></script>
</body>
</html>
