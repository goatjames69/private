(function () {
    'use strict';

    var chatId = window.supportChatId;
    var messagesEl = document.getElementById('supportChatMessages');
    var sendForm = document.getElementById('supportSendForm');
    var msgInput = document.getElementById('supportMsgInput');
    var emojiBtn = document.getElementById('supportEmojiBtn');
    var imgInput = document.getElementById('supportImgInput');
    var pollInterval = null;

    var EMOJIS = ['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','😉','😍','🥰','😘','😗','😋','😛','😜','🤪','😎','👍','👎','👌','✌️','🤞','🙏','❤️','💯','🔥','⭐','✨','💪','👋','🙌','😢','😭','😤','😡','🤔','😐'];

    function scrollToBottom() {
        if (messagesEl) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function renderMessage(msg, opts) {
        opts = opts || {};
        if (!messagesEl) return;
        var isAdminView = window.supportIsAdmin === true;
        var isMine = isAdminView ? (msg.from || '') !== 'user' : (msg.from || '') === 'user';
        var senderLabel = opts.senderName != null ? opts.senderName : (msg.sender_name || (isMine ? (isAdminView ? 'Admin' : 'You') : 'Support'));
        var showSeen = opts.showSeen !== false && isMine;
        var seen = opts.seen;
        if (showSeen && seen === undefined && opts.lastSeenByUser != null && opts.lastSeenByStaff != null) {
            var msgTime = msg.date ? new Date(msg.date).getTime() : 0;
            if (msg.from === 'user') seen = opts.lastSeenByStaff && new Date(opts.lastSeenByStaff).getTime() >= msgTime;
            else seen = opts.lastSeenByUser && new Date(opts.lastSeenByUser).getTime() >= msgTime;
        }
        if (showSeen && seen === undefined) seen = false;

        var div = document.createElement('div');
        div.className = 'support-msg ' + (isMine ? 'mine' : 'theirs');
        if (msg.id) div.setAttribute('data-msg-id', msg.id);
        var senderPart = '<div class="support-msg-sender">' + escapeHtml(senderLabel) + '</div>';
        var textPart = msg.text ? '<div class="support-msg-text">' + escapeHtml(msg.text).replace(/\n/g, '<br>') + '</div>' : '';
        var imgBase = (typeof window.supportBaseUrl === 'string' && window.supportBaseUrl) ? window.supportBaseUrl : '';
        var imgPart = msg.image ? '<div class="support-msg-img"><img src="' + imgBase + escapeHtml(msg.image) + '" alt=""></div>' : '';
        var seenLabel = '';
        if (showSeen) seenLabel = seen ? 'Seen' : (isAdminView ? 'Unread' : 'Delivered');
        var metaPart = '<div class="support-msg-meta"><span class="support-msg-time">' + formatTime(msg.date) + '</span>';
        if (seenLabel) metaPart += '<span class="support-msg-seen">' + seenLabel + '</span>';
        metaPart += '</div>';
        div.innerHTML = '<div class="support-msg-bubble">' + senderPart + textPart + imgPart + metaPart + '</div>';
        messagesEl.appendChild(div);
        scrollToBottom();
    }

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        var h = d.getHours();
        var m = d.getMinutes();
        var ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
    }

    var apiBase = (typeof window.supportBaseUrl === 'string' && window.supportBaseUrl) ? window.supportBaseUrl : '';

    function loadNewMessages(afterId) {
        if (!chatId) return;
        var url = apiBase + 'api/support_messages.php?chat_id=' + encodeURIComponent(chatId);
        if (afterId) url += '&after=' + encodeURIComponent(afterId);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.messages && data.messages.length) {
                    var opts = {
                        lastSeenByUser: data.last_seen_by_user_at || null,
                        lastSeenByStaff: data.last_seen_by_staff_at || null
                    };
                    data.messages.forEach(function (m) { renderMessage(m, opts); });
                }
            })
            .catch(function () {});
    }

    if (document.getElementById('supportNewBtn')) {
        document.getElementById('supportNewBtn').addEventListener('click', function () {
            var form = document.getElementById('supportCreateForm');
            if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (sendForm) {
        sendForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!chatId || !msgInput) return;
            var text = (msgInput.value || '').trim();
            var fileInput = imgInput;
            var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
            if (!text && !hasFile) return;

            var fd = new FormData();
            fd.append('chat_id', chatId);
            fd.append('message', text);
            fd.append('sender_context', window.supportIsAdmin === true ? 'admin' : 'user');
            if (hasFile) fd.append('image', fileInput.files[0]);

            var btn = sendForm.querySelector('.support-send-btn');
            if (btn) { btn.disabled = true; btn.setAttribute('data-loading', 'true'); }

            var sendUrl = apiBase + 'api/support_send.php';
            fetch(sendUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && data.message) {
                        renderMessage(data.message, { showSeen: true, seen: false });
                        msgInput.value = '';
                        if (fileInput) fileInput.value = '';
                    } else if (data.error) {
                        alert(data.error);
                    }
                })
                .catch(function () { if (window.JamesToasts) window.JamesToasts.error('Failed to send.'); else alert('Failed to send.'); })
                .then(function () { if (btn) { btn.disabled = false; btn.removeAttribute('data-loading'); } });
        });
    }

    if (emojiBtn && msgInput) {
        var picker = document.createElement('div');
        picker.className = 'support-emoji-picker';
        var grid = document.createElement('div');
        grid.className = 'support-emoji-grid';
        EMOJIS.forEach(function (emoji) {
            var span = document.createElement('span');
            span.textContent = emoji;
            span.addEventListener('click', function () {
                msgInput.value = (msgInput.value || '') + emoji;
                msgInput.focus();
            });
            grid.appendChild(span);
        });
        picker.appendChild(grid);
        emojiBtn.parentNode.appendChild(picker);

        emojiBtn.addEventListener('click', function () {
            picker.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target) && e.target !== emojiBtn) picker.classList.remove('open');
        });
    }

    if (chatId && messagesEl) {
        scrollToBottom();
        // Poll for new messages every 2.5s so chat works without realtime server (e.g. on hosting)
        var pollMs = 2500;
        if (window.JamesRealtime && window.JamesRealtime.on) {
            window.JamesRealtime.on('support_message', function (p) {
                if (p && p.chat_id === chatId && p.message) {
                    var opts = {};
                    if (p.message.sender_name) opts.senderName = p.message.sender_name;
                    renderMessage(p.message, opts);
                }
            });
            if (window.JamesRealtime.isConnected && typeof window.JamesRealtime.isConnected === 'function' && window.JamesRealtime.isConnected()) pollMs = 8000;
        }
        function pollMessages() {
            var lastMsg = messagesEl.querySelector('.support-msg:last-child');
            var lastId = lastMsg ? lastMsg.getAttribute('data-msg-id') || null : null;
            loadNewMessages(lastId || null);
        }
        pollMessages();
        pollInterval = setInterval(pollMessages, pollMs);
    }

    if (window.addEventListener) {
        window.addEventListener('beforeunload', function () {
            if (pollInterval) clearInterval(pollInterval);
        });
    }
})();
