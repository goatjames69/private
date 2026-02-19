/**
 * Dashboard real-time: auth, withdrawal AJAX, balance sync
 */
(function () {
    'use strict';

    var authEl = document.getElementById('james-realtime-auth');
    if (authEl) {
        try {
            var auth = JSON.parse(authEl.textContent);
            if (window.JamesRealtime) window.JamesRealtime.auth(auth.user_id || null, auth.role || 'user');
        } catch (e) {}
    }

    if (window.JamesRealtime) {
        window.JamesRealtime.on('disconnected', function () {
            if (window.JamesRealtime.startPollFallback) window.JamesRealtime.startPollFallback('/api/me.php', 8000);
        });
        window.JamesRealtime.on('user_balance_updated', function (payload) {
            if (payload && payload.balance !== undefined && window.updateDashboardBalance) {
                window.updateDashboardBalance(payload.balance);
                if (window.JamesToasts) window.JamesToasts.info('Your balance was updated to $' + parseFloat(payload.balance).toFixed(2), 'Balance updated');
            }
        });
        window.JamesRealtime.on('notification', function (payload) {
            if (payload && payload.title && window.JamesToasts) {
                window.JamesToasts.notify(payload.title, payload.body || '', payload.type || 'info');
            }
        });
    }

    var form = document.getElementById('withdrawForm');
    var submitBtn = document.getElementById('withdrawSubmitBtn');
    var modal = document.getElementById('withdrawModal');
    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (submitBtn.getAttribute('data-loading') === 'true') return;
            var fd = new FormData(form);
            submitBtn.setAttribute('data-loading', 'true');
            submitBtn.disabled = true;
            fetch('/api/withdraw.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    submitBtn.removeAttribute('data-loading');
                    submitBtn.disabled = false;
                    if (data.success) {
                        if (modal) modal.style.display = 'none';
                        if (window.JamesToasts) window.JamesToasts.success(data.message || 'Withdrawal request submitted.');
                        if (data.balance !== undefined && window.updateDashboardBalance) window.updateDashboardBalance(data.balance);
                        form.reset();
                    } else {
                        if (window.JamesToasts) window.JamesToasts.error(data.error || 'Request failed');
                    }
                })
                .catch(function () {
                    submitBtn.removeAttribute('data-loading');
                    submitBtn.disabled = false;
                    if (window.JamesToasts) window.JamesToasts.error('Network error. Please try again.');
                });
        });
    }
})();
