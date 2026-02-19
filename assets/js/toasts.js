/**
 * Toast notifications and notification center - real-time UI feedback
 */
(function () {
    'use strict';

    var container = null;
    var centerEl = null;
    var centerList = null;
    var centerCount = null;
    var notifications = [];
    var unreadCount = 0;

    function ensureContainer() {
        if (container) return;
        container = document.createElement('div');
        container.id = 'james-toast-container';
        container.className = 'james-toast-container';
        container.style.cssText = 'position:fixed;top:16px;right:16px;z-index:999999;display:flex;flex-direction:column;gap:10px;max-width:360px;pointer-events:none;';
        document.body.appendChild(container);
    }

    function ensureNotificationCenter() {
        if (centerEl) return;
        centerEl = document.createElement('div');
        centerEl.id = 'james-notification-center';
        centerEl.className = 'james-notification-center';
        centerEl.innerHTML = '<div class="james-nc-header"><span>Notifications</span><button type="button" class="james-nc-mark-read" aria-label="Mark all read">Mark read</button></div><div class="james-nc-list"></div><div class="james-nc-empty">No notifications yet.</div>';
        centerList = centerEl.querySelector('.james-nc-list');
        var empty = centerEl.querySelector('.james-nc-empty');
        centerEl.querySelector('.james-nc-mark-read').addEventListener('click', function () {
            unreadCount = 0;
            notifications.forEach(function (n) { n.read = true; });
            updateCenterBadge();
            renderCenterList();
        });
        document.body.appendChild(centerEl);
    }

    function addToCenter(item) {
        notifications.unshift(item);
        if (notifications.length > 100) notifications.pop();
        if (!item.read) unreadCount++;
        updateCenterBadge();
        renderCenterList();
    }

    function updateCenterBadge() {
        var n = unreadCount > 99 ? '99+' : (unreadCount || '');
        var triggerBadge = document.getElementById('james-nc-badge');
        if (triggerBadge) {
            triggerBadge.textContent = n;
            triggerBadge.style.display = unreadCount > 0 ? 'block' : 'none';
            triggerBadge.classList.toggle('show', unreadCount > 0);
        }
    }

    function renderCenterList() {
        if (!centerList) return;
        var empty = centerEl.querySelector('.james-nc-empty');
        centerList.innerHTML = '';
        notifications.slice(0, 50).forEach(function (n) {
            var div = document.createElement('div');
            div.className = 'james-nc-item' + (n.read ? '' : ' unread');
            div.innerHTML = '<span class="james-nc-icon ' + (n.type || 'info') + '"></span><div class="james-nc-content"><div class="james-nc-title">' + escapeHtml(n.title || 'Notification') + '</div><div class="james-nc-body">' + escapeHtml(n.body || '') + '</div><div class="james-nc-time">' + (n.time || '') + '</div></div>';
            div.addEventListener('click', function () { n.read = true; unreadCount = Math.max(0, unreadCount - 1); updateCenterBadge(); renderCenterList(); });
            centerList.appendChild(div);
        });
        if (empty) empty.style.display = notifications.length ? 'none' : 'block';
    }

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function showToast(message, type, title) {
        type = type || 'success';
        ensureContainer();
        var el = document.createElement('div');
        el.className = 'james-toast james-toast-' + type + ' visible';
        el.setAttribute('role', 'alert');
        var bg = type === 'error' ? '#991b1b' : type === 'warning' ? '#92400e' : type === 'info' ? '#1e40af' : '#166534';
        el.style.cssText = 'pointer-events:auto;padding:14px 18px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.35);color:#fff;background:' + bg + ';opacity:1;transform:translateX(0);';
        el.innerHTML = (title ? '<div class="james-toast-title" style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">' + escapeHtml(title) + '</div>' : '') + '<div class="james-toast-message" style="font-size:0.875rem;opacity:0.95;">' + escapeHtml(message) + '</div>';
        container.appendChild(el);
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transform = 'translateX(24px)';
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
        }, 4000);
    }

    function addNotification(title, body, type) {
        type = type || 'info';
        var item = {
            title: title,
            body: body || '',
            type: type,
            read: false,
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        };
        addToCenter(item);
        return item;
    }

    function getNotificationCenterTrigger() {
        return document.getElementById('james-notification-trigger');
    }

    function toggleCenter() {
        ensureNotificationCenter();
        centerEl.classList.toggle('open');
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('#james-notification-trigger');
        if (trigger) toggleCenter();
        if (centerEl && centerEl.classList.contains('open') && !centerEl.contains(e.target) && !trigger) centerEl.classList.remove('open');
    });

    /** Custom confirm dialog (yes/no). Returns Promise<boolean>. No browser confirm(). */
    function showConfirm(message, options) {
        options = options || {};
        var title = options.title || 'Confirm';
        var confirmText = options.confirmText || 'Yes';
        var cancelText = options.cancelText || 'No';
        var type = options.type || 'default';

        var overlay = document.createElement('div');
        overlay.className = 'james-confirm-overlay james-confirm-visible';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'james-confirm-title');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,0.7);opacity:1;visibility:visible;';

        var modal = document.createElement('div');
        modal.className = 'james-confirm-modal james-confirm-' + type;
        modal.style.cssText = 'width:100%;max-width:400px;padding:24px;border-radius:16px;background:#1e2430;border:1px solid rgba(255,255,255,0.1);box-shadow:0 24px 48px rgba(0,0,0,0.5);color:#e2e8f0;';
        modal.innerHTML =
            '<div class="james-confirm-title" id="james-confirm-title" style="font-size:18px;font-weight:700;margin-bottom:12px;">' + escapeHtml(title) + '</div>' +
            '<div class="james-confirm-message" style="font-size:15px;line-height:1.5;color:#94a3b8;margin-bottom:24px;">' + escapeHtml(message) + '</div>' +
            '<div class="james-confirm-actions" style="display:flex;gap:12px;justify-content:flex-end;">' +
            '<button type="button" class="james-confirm-btn james-confirm-cancel" style="padding:10px 20px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:#2d3142;color:#94a3b8;border:1px solid rgba(255,255,255,0.1);">' + escapeHtml(cancelText) + '</button>' +
            '<button type="button" class="james-confirm-btn james-confirm-yes" style="padding:10px 20px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#166534,#15803d);color:#fff;border:none;">' + escapeHtml(confirmText) + '</button>' +
            '</div>';
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        function close(res) {
            overlay.style.opacity = '0';
            overlay.style.visibility = 'hidden';
            setTimeout(function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                document.body.style.overflow = '';
            }, 200);
            resolveConfirm(res);
        }

        var resolveConfirm;
        var promise = new Promise(function (resolve) { resolveConfirm = resolve; });

        modal.querySelector('.james-confirm-yes').addEventListener('click', function () { close(true); });
        modal.querySelector('.james-confirm-cancel').addEventListener('click', function () { close(false); });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close(false);
        });
        overlay.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close(false);
            if (e.key === 'Enter') { e.preventDefault(); close(true); }
        });

        try { modal.querySelector('.james-confirm-yes').focus(); } catch (err) {}
        return promise;
    }

    /** Global handler: buttons with data-confirm submit their form only after user confirms */
    function handleConfirmClick(e) {
        var btn = e.target.closest('button[data-confirm], input[data-confirm], [data-confirm][role="button"]');
        if (!btn) return;
        var form = btn.closest('form');
        if (!form) return;
        var msg = btn.getAttribute('data-confirm');
        if (!msg) return;
        e.preventDefault();
        e.stopPropagation();
        try {
            showConfirm(msg).then(function (y) {
                if (y) {
                    if (btn.name && btn.value !== undefined) {
                        var h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = btn.name;
                        h.value = btn.value;
                        form.appendChild(h);
                    }
                    form.submit();
                }
            });
        } catch (err) {
            form.submit();
        }
    }

    if (document.body) {
        document.body.addEventListener('click', handleConfirmClick, true);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            document.body.addEventListener('click', handleConfirmClick, true);
        });
    }

    window.JamesToasts = {
        success: function (msg, title) { showToast(msg, 'success', title); },
        error: function (msg, title) { showToast(msg, 'error', title); },
        warning: function (msg, title) { showToast(msg, 'warning', title); },
        info: function (msg, title) { showToast(msg, 'info', title); },
        notify: function (title, body, type) {
            addNotification(title, body, type || 'info');
            showToast(body || title, type || 'info', title);
        },
        confirm: function (message, options) {
            return showConfirm(message, options || {});
        },
        ensureCenter: ensureNotificationCenter,
        getUnreadCount: function () { return unreadCount; }
    };
})();
