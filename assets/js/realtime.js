/**
 * JAMES GAMEROOM - Real-time via lightweight polling (no SSE/WebSocket, no server load).
 * Polls api/realtime_poll.php every few seconds; single request/response, no long connections.
 */
(function () {
    'use strict';

    var base = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
    var POLL_URL = window.JAMES_REALTIME_POLL_URL || base + '/api/realtime_poll.php';
    var POLL_INTERVAL = 5000;
    var POLL_INTERVAL_HIDDEN = 15000;
    var pollTimer = null;
    var lastId = 0;
    var handlers = {};
    var authPayload = null;
    var connected = false;

    function emit(event, data) {
        (handlers[event] || []).concat(handlers['*'] || []).forEach(function (fn) { fn(data); });
    }

    function getInterval() {
        if (typeof document !== 'undefined' && document.hidden) return POLL_INTERVAL_HIDDEN;
        return POLL_INTERVAL;
    }

    function poll() {
        var url = POLL_URL + (POLL_URL.indexOf('?') >= 0 ? '&' : '?') + 'last_id=' + lastId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                connected = true;
                if (data && Array.isArray(data.events) && data.events.length) {
                    data.events.forEach(function (e) {
                        lastId = e.id || lastId;
                        emit(e.type || '', e.payload || {});
                        emit('*', { type: e.type || '', payload: e.payload || {} });
                    });
                }
                if (data && data.last_id != null) lastId = data.last_id;
            })
            .catch(function () {
                connected = false;
                emit('disconnected', {});
            });
    }

    function tick() {
        poll();
        pollTimer = setTimeout(tick, getInterval());
    }

    function startPolling() {
        if (pollTimer) return;
        tick();
    }

    function stopPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        connected = false;
    }

    function startPollFallback(pollUrl, interval) {
        interval = interval || 6000;
        if (pollTimer) return;
        function fallbackPoll() {
            fetch(pollUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.balance !== undefined) emit('user_balance_updated', { user_id: (data.user_id || authPayload && authPayload.user_id), balance: data.balance });
                })
                .catch(function () {});
        }
        setInterval(fallbackPoll, interval);
        fallbackPoll();
    }

    window.JamesRealtime = {
        connect: function () { startPolling(); },
        disconnect: function () { stopPolling(); },
        auth: function (user_id, role) {
            authPayload = { user_id: user_id || null, role: role || null };
        },
        on: function (event, fn) {
            if (!handlers[event]) handlers[event] = [];
            handlers[event].push(fn);
        },
        off: function (event, fn) {
            if (!handlers[event]) return;
            var i = handlers[event].indexOf(fn);
            if (i !== -1) handlers[event].splice(i, 1);
        },
        isConnected: function () { return connected; },
        startPollFallback: startPollFallback
    };

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startPolling);
        } else {
            startPolling();
        }
        document.addEventListener('visibilitychange', function () {
            if (pollTimer) {
                clearTimeout(pollTimer);
                pollTimer = setTimeout(tick, getInterval());
            }
        });
    }
})();
