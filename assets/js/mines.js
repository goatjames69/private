/**
 * Mines game – 5x5 grid, 1–24 mines, provably fair.
 * Handles: start, reveal, cashout, UI updates, sounds, auto-play.
 */
(function() {
    var config = window.MINES_CONFIG || { minBet: 0.1, maxBet: 500, balance: 0 };
    var state = {
        gameId: null,
        bet: 0,
        mines: 3,
        multiplier: 1,
        revealed: [],
        status: 'idle',
        clientSeed: '',
        nonce: 0,
        mode: 'manual',
        autoPlaysLeft: 0,
        autoProfitTarget: 0,
        autoLossLimit: 0
    };

    var grid = document.getElementById('minesGrid');
    var tiles = grid ? grid.querySelectorAll('.mines-tile') : [];
    var balanceEl = document.getElementById('minesBalance');
    var betEl = document.getElementById('minesBet');
    var minesSelect = document.getElementById('minesCount');
    var multiplierEl = document.getElementById('minesMultiplier');
    var profitEl = document.getElementById('minesProfit');
    var startBtn = document.getElementById('minesStartBtn');
    var cashoutBtn = document.getElementById('minesCashoutBtn');
    var soundOn = document.getElementById('minesSoundOn');
    var speedSelect = document.getElementById('minesSpeed');
    var autoOptions = document.getElementById('minesAutoOptions');
    var modeBtns = document.querySelectorAll('.mines-mode-btn');

    function getSpeedMs() {
        var v = speedSelect ? speedSelect.value : 'normal';
        if (v === 'fast') return 200;
        if (v === 'slow') return 800;
        return 400;
    }

    function playSound(type) {
        if (!soundOn || !soundOn.checked) return;
        try {
            var ctx = window.minesAudioContext || (window.minesAudioContext = new (window.AudioContext || window.webkitAudioContext)());
            var now = ctx.currentTime;
            if (type === 'click') {
                var osc = ctx.createOscillator();
                var g = ctx.createGain();
                osc.connect(g); g.connect(ctx.destination);
                osc.frequency.setValueAtTime(520, now);
                osc.type = 'sine';
                g.gain.setValueAtTime(0.12, now);
                g.gain.exponentialRampToValueAtTime(0.001, now + 0.08);
                osc.start(now); osc.stop(now + 0.08);
            } else if (type === 'gem') {
                var o1 = ctx.createOscillator(), o2 = ctx.createOscillator(), g = ctx.createGain();
                o1.connect(g); o2.connect(g); g.connect(ctx.destination);
                o1.frequency.setValueAtTime(523.25, now);
                o2.frequency.setValueAtTime(659.25, now + 0.02);
                o1.type = o2.type = 'sine';
                g.gain.setValueAtTime(0, now);
                g.gain.linearRampToValueAtTime(0.14, now + 0.02);
                g.gain.exponentialRampToValueAtTime(0.001, now + 0.2);
                o1.start(now); o1.stop(now + 0.2);
                o2.start(now + 0.02); o2.stop(now + 0.2);
            } else if (type === 'mine') {
                var o1 = ctx.createOscillator(), o2 = ctx.createOscillator(), g = ctx.createGain();
                o1.connect(g); o2.connect(g); g.connect(ctx.destination);
                o1.frequency.setValueAtTime(80, now);
                o1.frequency.exponentialRampToValueAtTime(40, now + 0.25);
                o2.frequency.setValueAtTime(120, now);
                o2.frequency.exponentialRampToValueAtTime(60, now + 0.25);
                o1.type = o2.type = 'sawtooth';
                g.gain.setValueAtTime(0.25, now);
                g.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                o1.start(now); o1.stop(now + 0.35);
                o2.start(now); o2.stop(now + 0.35);
            } else if (type === 'cashout') {
                var o1 = ctx.createOscillator(), o2 = ctx.createOscillator(), o3 = ctx.createOscillator(), g = ctx.createGain();
                o1.connect(g); o2.connect(g); o3.connect(g); g.connect(ctx.destination);
                o1.frequency.setValueAtTime(523.25, now);
                o2.frequency.setValueAtTime(659.25, now + 0.06);
                o3.frequency.setValueAtTime(783.99, now + 0.12);
                o1.type = o2.type = o3.type = 'sine';
                g.gain.setValueAtTime(0, now);
                g.gain.linearRampToValueAtTime(0.12, now + 0.02);
                g.gain.setValueAtTime(0.12, now + 0.18);
                g.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                o1.start(now); o1.stop(now + 0.35);
                o2.start(now + 0.06); o2.stop(now + 0.35);
                o3.start(now + 0.12); o3.stop(now + 0.35);
            }
        } catch (e) {}
    }

    function updateBalance(b) {
        config.balance = b;
        if (balanceEl) balanceEl.textContent = '$' + parseFloat(b).toFixed(2);
    }

    function updateMultiplier(m) {
        state.multiplier = m;
        if (multiplierEl) multiplierEl.textContent = parseFloat(m).toFixed(2) + '×';
    }

    function updateProfit(profit) {
        if (profitEl) {
            profitEl.textContent = (profit >= 0 ? '+' : '') + '$' + parseFloat(profit).toFixed(2);
            profitEl.style.color = profit >= 0 ? 'var(--mines-accent)' : 'var(--mines-danger)';
        }
    }

    function setControlsPlaying(playing) {
        if (startBtn) startBtn.disabled = playing;
        if (betEl) betEl.disabled = playing;
        if (minesSelect) minesSelect.disabled = playing;
        if (cashoutBtn) cashoutBtn.disabled = !playing || state.revealed.length === 0;
        for (var i = 0; i < tiles.length; i++) {
            tiles[i].disabled = !playing;
            if (!playing) {
                tiles[i].classList.remove('revealed', 'gem', 'mine', 'mines-tile-trigger', 'mines-tile-other-bomb');
                tiles[i].textContent = '';
                tiles[i].innerHTML = '';
            }
        }
    }

    function resetTiles() {
        for (var i = 0; i < tiles.length; i++) {
            tiles[i].classList.remove('revealed', 'gem', 'mine', 'mines-tile-trigger', 'mines-tile-other-bomb');
            tiles[i].textContent = '';
            tiles[i].innerHTML = '';
        }
    }

    var apiUrl = (typeof window.MINES_API_URL !== 'undefined' && window.MINES_API_URL && String(window.MINES_API_URL).indexOf('http') === 0) ? window.MINES_API_URL : '../api/mines.php';
    function apiBody(body) {
        var parts = [];
        for (var k in body) if (body.hasOwnProperty(k)) parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(body[k]));
        return parts.join('&');
    }
    function api(path, body) {
        var payload = (body != null && typeof body === 'object') ? body : (path != null && typeof path === 'object' ? path : {});
        var formBody = apiBody(payload);
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

    function loadMinesHistory() {
        var loadingEl = document.getElementById('minesHistoryLoading');
        var emptyEl = document.getElementById('minesHistoryEmpty');
        var tableWrap = document.getElementById('minesHistoryTableWrap');
        var tbody = document.getElementById('minesHistoryBody');
        if (!tbody) return;
        if (loadingEl) loadingEl.style.display = 'block';
        if (emptyEl) emptyEl.style.display = 'none';
        if (tableWrap) tableWrap.style.display = 'none';
        api({ action: 'history', limit: 50 }).then(function(res) {
            if (loadingEl) loadingEl.style.display = 'none';
            if (!res.success || !res.games || res.games.length === 0) {
                if (emptyEl) emptyEl.style.display = 'block';
                return;
            }
            if (tableWrap) tableWrap.style.display = 'block';
            var rows = '';
            res.games.forEach(function(g) {
                var resultClass = g.result === 'win' ? 'mines-history-result-win' : 'mines-history-result-loss';
                var profitClass = g.profit >= 0 ? 'mines-history-profit-win' : 'mines-history-profit-loss';
                var profitStr = (g.profit >= 0 ? '+' : '') + '$' + parseFloat(g.profit).toFixed(2);
                var details = g.multiplier != null ? g.multiplier.toFixed(2) + '×' : (g.tiles_revealed + ' tiles');
                var dateStr = g.created_at ? new Date(g.created_at).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '-';
                rows += '<tr><td>' + (dateStr) + '</td><td>$' + parseFloat(g.bet).toFixed(2) + '</td><td>' + (g.mines || '-') + '</td><td class="' + resultClass + '">' + (g.result || '-') + '</td><td class="' + profitClass + '">' + profitStr + '</td><td>' + details + '</td></tr>';
            });
            tbody.innerHTML = rows;
        }).catch(function() {
            if (loadingEl) loadingEl.style.display = 'none';
            if (emptyEl) emptyEl.style.display = 'block';
        });
    }

    function startGame() {
        var bet = parseFloat(betEl ? betEl.value : 0) || config.minBet;
        var mines = parseInt(minesSelect ? minesSelect.value : 3, 10) || 3;
        if (bet < config.minBet || bet > config.maxBet) {
            alert('Bet must be between $' + config.minBet.toFixed(2) + ' and $' + config.maxBet.toFixed(2));
            return;
        }
        if (bet > config.balance) {
            alert('Insufficient balance');
            return;
        }
        state.clientSeed = state.clientSeed || Math.random().toString(36).slice(2) + Date.now();
        state.nonce = (state.nonce || 0) + 1;

        api({ action: 'start', bet: bet, mines: mines, client_seed: state.clientSeed, nonce: state.nonce })
            .then(function(res) {
                if (!res.success) {
                    alert(res.error || 'Failed to start');
                    if (res.error && res.error.indexOf('balance') !== -1) updateBalance(config.balance);
                    return;
                }
                state.gameId = res.game_id;
                state.bet = res.bet;
                state.mines = res.mines;
                state.revealed = [];
                state.status = 'playing';
                state.multiplier = 1;
                updateBalance(res.balance);
                updateMultiplier(1);
                updateProfit(0);
                resetTiles();
                setControlsPlaying(true);
                if (cashoutBtn) cashoutBtn.disabled = true;
            })
            .catch(function(err) {
                alert(err && err.message ? err.message : 'Network error. Check the API URL and that you are logged in.');
            });
    }

    function revealTile(index) {
        if (!state.gameId || state.status !== 'playing' || state.revealed.indexOf(index) !== -1) return;
        var tile = tiles[index];
        if (!tile || tile.classList.contains('revealed')) return;

        playSound('click');
        tile.disabled = true;

        api({ action: 'reveal', game_id: state.gameId, tile: index })
            .then(function(res) {
                if (!res.success) {
                    tile.disabled = false;
                    alert(res.error || 'Reveal failed');
                    return;
                }
                state.revealed.push(index);
                updateBalance(res.balance);

                if (res.type === 'mine') {
                    var hitIndex = index;
                    tile.classList.add('revealed', 'mine', 'mines-tile-trigger');
                    tile.innerHTML = '<span class="mines-bomb-icon"><i class="fas fa-bomb"></i></span><span class="mines-blast" aria-hidden="true"></span>';
                    playSound('mine');
                    state.status = 'loss';
                    updateMultiplier(0);
                    updateProfit(res.profit);
                    if (res.mine_positions && Array.isArray(res.mine_positions)) {
                        for (var m = 0; m < res.mine_positions.length; m++) {
                            var idx = res.mine_positions[m];
                            if (idx === hitIndex) continue;
                            var otherTile = tiles[idx];
                            if (otherTile && !otherTile.classList.contains('revealed')) {
                                otherTile.classList.add('revealed', 'mine', 'mines-tile-other-bomb');
                                otherTile.innerHTML = '<span class="mines-bomb-icon"><i class="fas fa-bomb"></i></span>';
                            }
                        }
                    }
                    var gridWrap = document.querySelector('.mines-grid-wrap');
                    if (gridWrap) gridWrap.classList.add('mines-blast-shake');
                    setTimeout(function() {
                        if (gridWrap) gridWrap.classList.remove('mines-blast-shake');
                    }, 700);
                    setControlsPlaying(false);
                    var vg = document.getElementById('minesVerifyGameId');
                    if (vg) vg.value = state.gameId || '';
                    loadMinesHistory();
                    if (state.mode === 'auto') runAutoNext();
                    return;
                }

                tile.classList.add('revealed', 'gem');
                tile.innerHTML = '<i class="fas fa-gem"></i>';
                playSound('gem');
                updateMultiplier(res.multiplier);
                updateProfit(res.profit);
                if (cashoutBtn) cashoutBtn.disabled = false;
            })
            .catch(function(err) {
                tile.disabled = false;
                alert(err && err.message ? err.message : 'Network error');
            });
    }

    function cashout() {
        if (!state.gameId || state.status !== 'playing' || state.revealed.length === 0) return;

        api({ action: 'cashout', game_id: state.gameId })
            .then(function(res) {
                if (!res.success) {
                    alert(res.error || 'Cashout failed');
                    return;
                }
                playSound('cashout');
                updateBalance(res.balance);
                updateMultiplier(res.multiplier);
                updateProfit(res.profit);
                state.status = 'win';
                setControlsPlaying(false);
                var vg = document.getElementById('minesVerifyGameId');
                if (vg) vg.value = state.gameId || '';
                loadMinesHistory();
                if (state.mode === 'auto') runAutoNext();
            })
            .catch(function(err) {
                alert(err && err.message ? err.message : 'Network error');
            });
    }

    function runAutoNext() {
        if (state.mode !== 'auto' || state.autoPlaysLeft <= 0) return;
        state.autoPlaysLeft--;
        var delay = getSpeedMs();
        setTimeout(function() {
            if (state.autoProfitTarget > 0 && config.balance >= state.autoProfitTarget) return;
            if (state.autoLossLimit > 0 && config.balance <= state.autoLossLimit) return;
            startGame();
        }, delay);
    }

    function bindTile(i) {
        var tile = tiles[i];
        if (!tile) return;
        tile.addEventListener('click', function() {
            if (state.status !== 'playing' || tile.disabled || tile.classList.contains('revealed')) return;
            revealTile(i);
        });
    }

    for (var t = 0; t < tiles.length; t++) bindTile(t);

    if (startBtn) startBtn.addEventListener('click', function() {
        if (state.mode === 'auto') {
            state.autoPlaysLeft = parseInt(document.getElementById('minesAutoPlays')?.value || 10, 10) || 10;
            state.autoProfitTarget = parseFloat(document.getElementById('minesAutoProfit')?.value || 0) || 0;
            state.autoLossLimit = parseFloat(document.getElementById('minesAutoLoss')?.value || 0) || 0;
        }
        startGame();
    });

    if (cashoutBtn) cashoutBtn.addEventListener('click', cashout);

    if (betEl) {
        betEl.addEventListener('change', function() {
            var v = parseFloat(betEl.value);
            if (v < config.minBet) betEl.value = config.minBet;
            if (v > config.maxBet) betEl.value = config.maxBet;
        });
    }

    document.querySelectorAll('.mines-bet-buttons button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!betEl) return;
            var mult = parseFloat(btn.getAttribute('data-mult') || 1);
            var v = parseFloat(betEl.value) || config.minBet;
            v = Math.max(config.minBet, Math.min(config.maxBet, v * mult));
            betEl.value = v.toFixed(2);
        });
    });

    modeBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var mode = btn.getAttribute('data-mode');
            state.mode = mode;
            modeBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            if (autoOptions) autoOptions.style.display = mode === 'auto' ? 'block' : 'none';
        });
    });

    if (soundOn) {
        var saved = localStorage.getItem('mines_sound');
        if (saved !== null) soundOn.checked = saved === '1';
        soundOn.addEventListener('change', function() {
            localStorage.setItem('mines_sound', soundOn.checked ? '1' : '0');
        });
    }
    if (speedSelect) {
        var speedSaved = localStorage.getItem('mines_speed');
        if (speedSaved) speedSelect.value = speedSaved;
        else speedSelect.value = 'slow';
        speedSelect.addEventListener('change', function() {
            localStorage.setItem('mines_speed', speedSelect.value);
        });
    }

    var verifyBtn = document.getElementById('minesVerifyBtn');
    var verifyGameId = document.getElementById('minesVerifyGameId');
    var verifyResult = document.getElementById('minesVerifyResult');
    loadMinesHistory();

    if (verifyBtn && verifyGameId && verifyResult) {
        verifyBtn.addEventListener('click', function() {
            var gid = (verifyGameId.value || '').trim();
            if (!gid) { verifyResult.style.display = 'none'; return; }
            api(null, { action: 'verify', game_id: gid }).then(function(res) {
                verifyResult.style.display = 'block';
                verifyResult.textContent = res.success ? JSON.stringify({
                    game_id: res.game_id,
                    server_seed: res.server_seed,
                    client_seed: res.client_seed,
                    nonce: res.nonce,
                    mine_positions: res.mine_positions,
                    result: res.result,
                    bet: res.bet,
                    profit: res.profit
                }, null, 2) : (res.error || 'Not found');
            }).catch(function() {
                verifyResult.style.display = 'block';
                verifyResult.textContent = 'Network error';
            });
        });
    }
})();
