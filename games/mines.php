<?php
/**
 * Mines game – 5x5 grid, 1–24 mines, provably fair.
 * Requires login; balance and bet limits from game_settings.
 */
require_once __DIR__ . '/../config.php';
requireLogin();

$user = getCurrentUser();
if (!$user) {
    header('Location: /auth/login.php');
    exit;
}
if (empty(trim($user['email'] ?? ''))) {
    header('Location: /profile.php?add_email=1');
    exit;
}

$settings = getGameSettings();
$minBet = (float) ($settings['mines_min_bet'] ?? 0.10);
$maxBet = (float) ($settings['mines_max_bet'] ?? 500);
$balance = (float) ($user['balance'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mines - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/realtime.css">
    <link rel="stylesheet" href="../assets/css/mines.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="mines-page">
    <header class="mines-header">
        <a href="/dashboard.php" class="mines-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <h1 class="mines-title"><i class="fas fa-bomb"></i> Mines</h1>
        <div class="mines-balance-wrap">
            <span class="mines-balance-label">Balance</span>
            <span class="mines-balance" id="minesBalance">$<?= number_format($balance, 2) ?></span>
        </div>
    </header>

    <main class="mines-main">
        <section class="mines-controls">
            <div class="mines-control-row">
                <label>Bet amount</label>
                <div class="mines-bet-wrap">
                    <input type="number" id="minesBet" class="mines-input" step="0.01" min="<?= $minBet ?>" max="<?= $maxBet ?>" value="<?= min(1, $maxBet) ?>">
                    <div class="mines-bet-buttons">
                        <button type="button" class="mines-btn mines-btn-half" data-mult="0.5">½</button>
                        <button type="button" class="mines-btn mines-btn-double" data-mult="2">2×</button>
                    </div>
                </div>
                <small class="mines-hint">Min $<?= number_format($minBet, 2) ?> — Max $<?= number_format($maxBet, 2) ?></small>
            </div>
            <div class="mines-control-row">
                <label>Mines</label>
                <select id="minesCount" class="mines-select">
                    <?php for ($m = 1; $m <= 24; $m++): ?>
                        <option value="<?= $m ?>"<?= $m === 3 ? ' selected' : '' ?>><?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mines-control-row mines-mode-row">
                <button type="button" class="mines-mode-btn active" data-mode="manual">Manual</button>
                <button type="button" class="mines-mode-btn" data-mode="auto">Auto</button>
            </div>
            <div class="mines-control-row mines-actions">
                <button type="button" id="minesStartBtn" class="mines-btn mines-btn-start"><i class="fas fa-play"></i> Start</button>
                <button type="button" id="minesCashoutBtn" class="mines-btn mines-btn-cashout" disabled><i class="fas fa-hand-holding-usd"></i> Cash out</button>
            </div>
        </section>

        <section class="mines-display">
            <div class="mines-stat">
                <span class="mines-stat-label">Multiplier</span>
                <span class="mines-stat-value" id="minesMultiplier">1.00×</span>
            </div>
            <div class="mines-stat">
                <span class="mines-stat-label">Profit</span>
                <span class="mines-stat-value mines-profit" id="minesProfit">$0.00</span>
            </div>
        </section>

        <section class="mines-grid-wrap">
            <div class="mines-grid" id="minesGrid" aria-label="Mines game grid">
                <?php for ($i = 0; $i < 25; $i++): ?>
                    <button type="button" class="mines-tile" data-index="<?= $i ?>" disabled aria-label="Tile <?= $i + 1 ?>"></button>
                <?php endfor; ?>
            </div>
        </section>

        <section class="mines-auto-options" id="minesAutoOptions" style="display:none;">
            <label>Stop on profit</label>
            <input type="number" id="minesAutoProfit" class="mines-input" step="0.01" min="0" placeholder="0 = off">
            <label>Stop on loss</label>
            <input type="number" id="minesAutoLoss" class="mines-input" step="0.01" min="0" placeholder="0 = off">
            <label>Plays</label>
            <input type="number" id="minesAutoPlays" class="mines-input" min="1" value="10">
        </section>

        <section class="mines-settings" style="display: none;">
            <label class="mines-toggle-wrap">
                <input type="checkbox" id="minesSoundOn" checked>
                <span>Sound</span>
            </label>
            <label class="mines-toggle-wrap">
                <span>Speed</span>
                <select id="minesSpeed" class="mines-select mines-speed">
                    <option value="fast">Fast</option>
                    <option value="normal">Normal</option>
                    <option value="slow" selected>Slow</option>
                </select>
            </label>
        </section>

        <section class="mines-history-wrap">
            <h3 class="mines-history-title"><i class="fas fa-history"></i> Game History</h3>
            <div id="minesHistoryLoading" class="mines-history-loading" style="display:none;">Loading…</div>
            <div id="minesHistoryEmpty" class="mines-history-empty" style="display:none;">No games yet. Play to see your wins and losses here.</div>
            <div id="minesHistoryTableWrap" class="mines-history-table-wrap" style="display:none;">
                <table class="mines-history-table" id="minesHistoryTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bet</th>
                            <th>Mines</th>
                            <th>Result</th>
                            <th>Profit</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="minesHistoryBody"></tbody>
                </table>
            </div>
        </section>

        <details class="mines-provably">
            <summary>Provably fair – verify a game</summary>
            <p class="mines-provably-hint">After a game ends, you can verify the server seed and mine positions.</p>
            <input type="text" id="minesVerifyGameId" class="mines-input" placeholder="Paste game ID">
            <button type="button" id="minesVerifyBtn" class="mines-btn mines-btn-half">Verify</button>
            <pre id="minesVerifyResult" class="mines-verify-result" style="display:none;"></pre>
        </details>
    </main>

    <script>
    window.MINES_CONFIG = {
        minBet: <?= json_encode($minBet) ?>,
        maxBet: <?= json_encode($maxBet) ?>,
        balance: <?= json_encode($balance) ?>
    };
    <?php
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/games/mines.php';
    $basePath = dirname(dirname($scriptName));
    $basePath = str_replace('\\', '/', $basePath);
    $apiPath = rtrim($basePath, '/') . '/api/mines.php';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $fullApiUrl = $scheme . '://' . $host . (strpos($apiPath, '/') === 0 ? '' : '/') . $apiPath;
    ?>
    window.MINES_API_URL = <?= json_encode($fullApiUrl) ?>;
    </script>
    <script src="../assets/js/toasts.js"></script>
    <script src="../assets/js/mines.js"></script>
</body>
</html>
