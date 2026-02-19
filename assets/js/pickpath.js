/**
 * Pick-a-Path – 3 nodes, session-based. Client only receives result (safe/bust), never bomb location.
 */
(function() {
    var config = window.PICKPATH_CONFIG || { minBet: 0.1, maxBet: 500, balance: 0 };
    var state = {
        gameId: null,
        token: null,
        bet: 0,
        multiplier: 1,
        step: 0,
        status: 'idle'
    };

    var balanceEl = document.getElementById('pickpathBalance');
    var betEl = document.getElementById('pickpathBet');
    var stageEl = document.getElementById('pickpathStage');
    var multiplierEl = document.getElementById('pickpathMultiplier');
    var profitEl = document.getElementById('pickpathProfit');
    var startBtn = document.getElementById('pickpathStartBtn');
    var cashoutBtn = document.getElementById('pickpathCashoutBtn');
    var grid = document.getElementById('pickpathGrid');
    var nodes = grid ? grid.querySelectorAll('.pickpath-node') : [];
    var promptEl = document.getElementById('pickpathPrompt');
    var resultEl = document.getElementById('pickpathResult');

    var apiUrl = (typeof window.PICKPATH_API_URL !== 'undefined' && window.PICKPATH_API_URL && String(window.PICKPATH_API_URL).indexOf('http') === 0)
        ? window.PICKPATH_API_URL : '../api/pickpath.php';

    function apiBody(body) {
        var parts = [];
        for (var k in body) if (body.hasOwnProperty(k)) parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(body[k]));
        return parts.join('&');
    }

    function api(body) {
        var formBody = apiBody(body || {});
        return fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formBody
        }).then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            var ct = r.headers.get('Content-Type') || '';
            if (ct.indexOf('application/json') === -1) throw new Error('Invalid response');
            return r.json();
        });
    }

    function updateBalance(b) {
        config.balance = b;
        if (balanceEl) balanceEl.textContent = '$' + parseFloat(b).toFixed(2);
    }

    var STAGE_LABELS = ['0', '1', '2', '3', '4', '5 (Goal)'];

    function updateStage(step) {
        state.step = step;
        if (stageEl) stageEl.textContent = step >= 0 && step < STAGE_LABELS.length ? STAGE_LABELS[step] : String(step);
    }

    function updateMultiplier(m) {
        state.multiplier = m;
        if (multiplierEl) multiplierEl.textContent = parseFloat(m).toFixed(2) + '×';
    }

    function updateProfit(profit) {
        if (profitEl) {
            profitEl.textContent = (profit >= 0 ? '+' : '') + '$' + parseFloat(profit).toFixed(2);
            profitEl.style.color = profit >= 0 ? 'var(--pp-accent)' : 'var(--pp-danger)';
        }
    }

    function setPlaying(playing) {
        if (startBtn) startBtn.disabled = playing;
        if (betEl) betEl.disabled = playing;
        if (cashoutBtn) cashoutBtn.disabled = !playing || state.step === 0;
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].disabled = !playing;
            nodes[i].classList.remove('pickpath-safe', 'pickpath-bust');
            nodes[i].querySelector('.pickpath-node-num').textContent = nodes[i].getAttribute('data-choice');
        }
        if (resultEl) { resultEl.style.display = 'none'; resultEl.className = 'pickpath-result'; resultEl.textContent = ''; }
        if (stageEl) stageEl.textContent = playing ? STAGE_LABELS[0] : '—';
        if (promptEl) promptEl.textContent = playing ? 'Pick path 1, 2, or 3. One is a bust!' : 'Start a game, then pick one path (1, 2, or 3). One path is a bust; two are safe.';
    }

    function resetState() {
        state.gameId = null;
        state.token = null;
        state.step = 0;
        state.multiplier = 1;
        state.status = 'idle';
        updateMultiplier(1);
        updateProfit(0);
        setPlaying(false);
    }

    function showResult(msg, isBust) {
        if (resultEl) {
            resultEl.textContent = msg;
            resultEl.className = 'pickpath-result pickpath-result-' + (isBust ? 'bust' : 'safe');
            resultEl.style.display = 'block';
        }
    }

    function startGame() {
        var bet = parseFloat(betEl ? betEl.value : 0) || config.minBet;
        if (bet < config.minBet || bet > config.maxBet) {
            if (window.JamesToasts) window.JamesToasts.error('Bet must be between $' + config.minBet.toFixed(2) + ' and $' + config.maxBet.toFixed(2));
            else alert('Bet must be between $' + config.minBet.toFixed(2) + ' and $' + config.maxBet.toFixed(2));
            return;
        }
        if (bet > config.balance) {
            if (window.JamesToasts) window.JamesToasts.error('Insufficient balance');
            else alert('Insufficient balance');
            return;
        }

        api({ action: 'start', bet: bet })
            .then(function(res) {
                if (!res.success) {
                    if (window.JamesToasts) window.JamesToasts.error(res.error || 'Failed to start');
                    else alert(res.error || 'Failed to start');
                    if (res.error && res.error.indexOf('balance') !== -1) updateBalance(config.balance);
                    return;
                }
                state.gameId = res.game_id;
                state.token = res.token || '';
                state.bet = res.bet;
                state.step = res.step || 0;
                state.multiplier = res.multiplier || 1;
                state.status = 'playing';
                updateBalance(res.balance);
                updateStage(state.step);
                updateMultiplier(state.multiplier);
                updateProfit(0);
                setPlaying(true);
                if (cashoutBtn) cashoutBtn.disabled = true;
            })
            .catch(function(err) {
                if (window.JamesToasts) window.JamesToasts.error(err && err.message ? err.message : 'Network error');
                else alert(err && err.message ? err.message : 'Network error');
            });
    }

    function pickNode(choice) {
        if (!state.gameId || state.status !== 'playing') return;
        var node = document.querySelector('.pickpath-node[data-choice="' + choice + '"]');
        if (!node || node.disabled) return;

        for (var i = 0; i < nodes.length; i++) nodes[i].disabled = true;

        var payload = { action: 'move', choice: choice, expected_step: state.step };
        if (state.token) payload.token = state.token;

        api(payload)
            .then(function(res) {
                if (!res.success) {
                    if (window.JamesToasts) window.JamesToasts.error(res.error || 'Move failed');
                    else alert(res.error || 'Move failed');
                    if (state.status === 'playing') for (var j = 0; j < nodes.length; j++) nodes[j].disabled = false;
                    return;
                }
                updateBalance(res.balance);

                if (res.result === 'bust') {
                    state.status = 'lost';
                    node.classList.add('pickpath-bust');
                    node.querySelector('.pickpath-node-num').textContent = '💥';
                    showResult('Bust! You lost $' + parseFloat(state.bet).toFixed(2), true);
                    updateMultiplier(0);
                    updateProfit(res.profit);
                    setPlaying(false);
                    if (window.JamesToasts) window.JamesToasts.error('Bust! -$' + parseFloat(state.bet).toFixed(2));
                    return;
                }

                state.step = res.step || state.step + 1;
                state.multiplier = res.multiplier;
                node.classList.add('pickpath-safe');
                node.querySelector('.pickpath-node-num').textContent = '✓';
                updateStage(state.step);
                updateMultiplier(state.multiplier);
                updateProfit(res.profit);
                showResult('Safe! Stage ' + state.step + ' — ' + parseFloat(state.multiplier).toFixed(2) + '×', false);
                if (cashoutBtn) cashoutBtn.disabled = false;
                for (var k = 0; k < nodes.length; k++) {
                    if (!nodes[k].classList.contains('pickpath-safe')) nodes[k].disabled = false;
                }
            })
            .catch(function(err) {
                if (window.JamesToasts) window.JamesToasts.error(err && err.message ? err.message : 'Network error');
                else alert(err && err.message ? err.message : 'Network error');
                if (state.status === 'playing') for (var n = 0; n < nodes.length; n++) nodes[n].disabled = false;
            });
    }

    function cashout() {
        if (!state.gameId || state.status !== 'playing' || state.step === 0) return;

        var payload = { action: 'cashout' };
        if (state.token) payload.token = state.token;

        api(payload)
            .then(function(res) {
                if (!res.success) {
                    if (window.JamesToasts) window.JamesToasts.error(res.error || 'Cashout failed');
                    else alert(res.error || 'Cashout failed');
                    return;
                }
                updateBalance(res.balance);
                updateMultiplier(res.multiplier);
                updateProfit(res.profit);
                showResult('Cashed out! +$' + parseFloat(res.profit).toFixed(2), false);
                if (window.JamesToasts) window.JamesToasts.success('Cashed out +$' + parseFloat(res.profit).toFixed(2));
                state.status = 'cashed_out';
                setPlaying(false);
            })
            .catch(function(err) {
                if (window.JamesToasts) window.JamesToasts.error(err && err.message ? err.message : 'Network error');
                else alert(err && err.message ? err.message : 'Network error');
            });
    }

    if (startBtn) startBtn.addEventListener('click', startGame);
    if (cashoutBtn) cashoutBtn.addEventListener('click', cashout);

    for (var t = 0; t < nodes.length; t++) {
        (function(choice, node) {
            node.addEventListener('click', function() {
                if (state.status !== 'playing' || node.disabled) return;
                pickNode(choice);
            });
        })(parseInt(nodes[t].getAttribute('data-choice'), 10), nodes[t]);
    }

    if (betEl) {
        betEl.addEventListener('change', function() {
            var v = parseFloat(betEl.value);
            if (v < config.minBet) betEl.value = config.minBet;
            if (v > config.maxBet) betEl.value = config.maxBet;
        });
    }

    document.querySelectorAll('.pickpath-bet-buttons button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!betEl) return;
            var mult = parseFloat(btn.getAttribute('data-mult') || 1);
            var v = parseFloat(betEl.value) || config.minBet;
            v = Math.max(config.minBet, Math.min(config.maxBet, v * mult));
            betEl.value = v.toFixed(2);
        });
    });
})();
