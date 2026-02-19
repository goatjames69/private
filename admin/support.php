<?php
require_once __DIR__ . '/../config.php';
requireStaffOrAdmin();

$chats = getSupportChats();
usort($chats, function ($a, $b) {
    return strtotime($b['updated_at'] ?? $b['created_at'] ?? '') - strtotime($a['updated_at'] ?? $a['created_at'] ?? '');
});

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'open';
if (!in_array($tab, ['open', 'closed', 'all'])) $tab = 'open';
$filtered = $chats;
if ($tab === 'open') $filtered = array_values(array_filter($chats, function ($c) { return ($c['status'] ?? '') === 'open'; }));
if ($tab === 'closed') $filtered = array_values(array_filter($chats, function ($c) { return ($c['status'] ?? '') === 'closed'; }));

$openChatId = isset($_GET['id']) ? trim($_GET['id']) : null;
$openChat = $openChatId ? getSupportChatById($openChatId) : null;
if ($openChatId && $openChat) {
    markSupportChatSeenByStaff($openChatId);
    $openChat = getSupportChatById($openChatId);
}
$openChatUser = $openChat ? getUserDisplayNameForSupport($openChat['user_id']) : '';

$supportChatCss = @file_get_contents(__DIR__ . '/../assets/css/support-chat.css');
if ($supportChatCss === false) $supportChatCss = '';
$supportChatCss = str_replace('</style>', '<' . '/style>', $supportChatCss);

$supportChatJs = @file_get_contents(__DIR__ . '/../assets/js/support-chat.js');
if ($supportChatJs === false) $supportChatJs = '';
$supportChatJs = str_replace('</script>', '<' . '/script>', $supportChatJs);

$adminPageTitle = 'Support Chat';
$adminCurrentPage = 'support';
$adminPageSubtitle = 'Chat with users and manage tickets';
if (!isset($pendingCounts)) $pendingCounts = [];
require __DIR__ . '/_header.php';
?>

<div class="admin-card">
    <div class="admin-card-header" style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-bottom: 20px;">
        <h2 class="admin-card-title"><i class="fas fa-headset"></i> Support Chat</h2>
        <div class="admin-tabs" style="margin-left: auto;">
            <a href="?tab=open<?= $openChatId ? '&id=' . urlencode($openChatId) : '' ?>" class="admin-tab <?= $tab === 'open' ? 'active' : '' ?>">Open</a>
            <a href="?tab=closed<?= $openChatId ? '&id=' . urlencode($openChatId) : '' ?>" class="admin-tab <?= $tab === 'closed' ? 'active' : '' ?>">Closed</a>
            <a href="?tab=all<?= $openChatId ? '&id=' . urlencode($openChatId) : '' ?>" class="admin-tab <?= $tab === 'all' ? 'active' : '' ?>">All</a>
        </div>
    </div>

    <?php if (!$openChatId): ?>
    <p style="color: var(--text-muted); margin-bottom: 16px;">Click a ticket to open the chat and reply. You can close or reopen tickets.</p>
    <?php if (empty($filtered)): ?>
        <div class="admin-empty"><i class="fas fa-inbox"></i><p>No <?= $tab ?> tickets</p></div>
    <?php else: ?>
        <ul class="support-ticket-list admin-support-list">
            <?php foreach ($filtered as $c): ?>
                <li>
                    <a href="?tab=<?= urlencode($tab) ?>&id=<?= urlencode($c['id']) ?>" class="support-ticket-item <?= ($c['status'] ?? '') === 'closed' ? 'closed' : '' ?>">
                        <span class="support-ticket-reason"><?= htmlspecialchars($c['reason'] ?? 'Support') ?></span>
                        <span class="support-ticket-user"><?= htmlspecialchars(getUserDisplayNameForSupport($c['user_id'] ?? '')) ?></span>
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

    <?php else: ?>
    <div class="admin-support-chat-wrap">
        <div class="support-chat-header" style="margin-bottom: 0;">
            <a href="?tab=<?= urlencode($tab) ?>" class="support-back"><i class="fas fa-arrow-left"></i> Back</a>
            <div class="support-chat-title">
                <span><?= htmlspecialchars($openChat['reason'] ?? 'Support') ?></span>
                <span class="support-chat-status <?= ($openChat['status'] ?? '') ?>"><?= ($openChat['status'] ?? 'open') ?></span>
                <span style="font-size: 0.85rem; color: var(--text-muted); margin-left: 8px;">— <?= htmlspecialchars($openChatUser) ?></span>
            </div>
            <div class="admin-support-actions">
                <?php if (($openChat['status'] ?? '') === 'open'): ?>
                    <button type="button" class="btn btn-warning btn-sm" id="supportCloseBtn" data-chat-id="<?= htmlspecialchars($openChatId) ?>"><i class="fas fa-lock"></i> Close</button>
                <?php else: ?>
                    <button type="button" class="btn btn-success btn-sm" id="supportReopenBtn" data-chat-id="<?= htmlspecialchars($openChatId) ?>"><i class="fas fa-unlock"></i> Reopen</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="support-chat-messages admin-support-messages" id="supportChatMessages">
            <?php foreach ($openChat['messages'] ?? [] as $m):
                $isTheirs = ($m['from'] ?? '') === 'user';
                $senderLabel = getSupportMessageSenderLabel($m, $openChat);
                $seenByOther = isSupportMessageSeenByOther($m, $openChat);
            ?>
                <div class="support-msg <?= $isTheirs ? 'theirs' : 'mine' ?>" data-msg-id="<?= htmlspecialchars($m['id'] ?? '') ?>">
                    <div class="support-msg-bubble">
                        <div class="support-msg-sender"><?= htmlspecialchars($senderLabel) ?></div>
                        <?php if (!empty($m['text'])): ?>
                            <div class="support-msg-text"><?= nl2br(htmlspecialchars($m['text'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($m['image'])): ?>
                            <div class="support-msg-img"><img src="../<?= htmlspecialchars($m['image']) ?>" alt=""></div>
                        <?php endif; ?>
                        <div class="support-msg-meta">
                            <span class="support-msg-time"><?= date('g:i A', strtotime($m['date'] ?? '')) ?></span>
                            <?php if (!$isTheirs): ?>
                                <span class="support-msg-seen"><?= $seenByOther ? 'Seen' : 'Unread' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (($openChat['status'] ?? '') === 'closed'): ?>
            <div class="support-chat-closed">
                <p>This chat is closed. Reopen above to reply.</p>
            </div>
        <?php else: ?>
        <div class="support-chat-input-wrap">
            <form id="supportSendForm" class="support-chat-form">
                <input type="hidden" name="chat_id" value="<?= htmlspecialchars($openChatId) ?>">
                <div class="support-chat-input-row">
                    <button type="button" class="support-emoji-btn" id="supportEmojiBtn" title="Emoji"><i class="fas fa-smile"></i></button>
                    <input type="text" name="message" class="form-control support-msg-input" id="supportMsgInput" placeholder="Type your reply..." autocomplete="off">
                    <label class="support-img-btn" title="Send image"><i class="fas fa-image"></i>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" id="supportImgInput">
                    </label>
                    <button type="submit" class="support-send-btn" title="Send"><i class="fas fa-paper-plane"></i></button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style><?= $supportChatCss ?></style>
<script>
window.supportChatId = <?= json_encode($openChatId) ?>;
window.supportIsAdmin = true;
window.supportBaseUrl = '../';
</script>
<script><?= $supportChatJs ?></script>
<script>
(function() {
    var closeBtn = document.getElementById('supportCloseBtn');
    var reopenBtn = document.getElementById('supportReopenBtn');
    var statusEl = document.querySelector('.support-chat-status');
    var closedWrap = document.querySelector('.support-chat-closed');
    var inputWrap = document.querySelector('.support-chat-input-wrap');
    function setStatus(action) {
        var chatId = (closeBtn || reopenBtn).getAttribute('data-chat-id');
        var fd = new FormData();
        fd.append('chat_id', chatId);
        fd.append('action', action);
        fetch('../api/support_status.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.status) {
                    if (statusEl) { statusEl.textContent = data.status; statusEl.className = 'support-chat-status ' + data.status; }
                    if (data.status === 'closed') {
                        if (closeBtn) { closeBtn.outerHTML = '<button type="button" class="btn btn-success btn-sm" id="supportReopenBtn" data-chat-id="' + chatId + '"><i class="fas fa-unlock"></i> Reopen</button>'; }
                        if (closedWrap) closedWrap.style.display = 'block';
                        if (inputWrap) inputWrap.style.display = 'none';
                        var newReopen = document.getElementById('supportReopenBtn');
                        if (newReopen) newReopen.addEventListener('click', function() { setStatus('reopen'); });
                    } else {
                        if (reopenBtn) { reopenBtn.outerHTML = '<button type="button" class="btn btn-warning btn-sm" id="supportCloseBtn" data-chat-id="' + chatId + '"><i class="fas fa-lock"></i> Close</button>'; }
                        if (closedWrap) closedWrap.style.display = 'none';
                        if (inputWrap) inputWrap.style.display = 'block';
                        var newClose = document.getElementById('supportCloseBtn');
                        if (newClose) newClose.addEventListener('click', function() { setStatus('close'); });
                    }
                    if (window.JamesToasts) window.JamesToasts.success('Ticket ' + data.status + '.');
                }
            })
            .catch(function() { if (window.JamesToasts) window.JamesToasts.error('Failed to update status'); });
    }
    if (closeBtn) closeBtn.addEventListener('click', function() { setStatus('close'); });
    if (reopenBtn) reopenBtn.addEventListener('click', function() { setStatus('reopen'); });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
