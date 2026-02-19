<?php
/**
 * Pick-a-Path – pick 1 of 3 nodes; 1 is Bust, 2 are Safe. Session-based, provably fair.
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
$minBet = (float) ($settings['pickpath_min_bet'] ?? 0.10);
$maxBet = (float) ($settings['pickpath_max_bet'] ?? 500);
$balance = (float) ($user['balance'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Pick-a-Path - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/realtime.css">
    <link rel="stylesheet" href="../assets/css/pickpath.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="pickpath-page">
    <header class="pickpath-header">
        <a href="../dashboard.php" class="pickpath-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <h1 class="pickpath-title"><i class="fas fa-route"></i> Pick-a-Path</h1>
        <div class="pickpath-balance-wrap">
            <span class="pickpath-balance-label">Balance</span>
            <span class="pickpath-balance" id="pickpathBalance">$<?= number_format($balance, 2) ?></span>
        </div>
    </header>

    <main class="pickpath-main">
        <section class="pickpath-controls">
            <div class="pickpath-control-row">
                <label>Bet amount</label>
                <div class="pickpath-bet-wrap">
                    <input type="number" id="pickpathBet" class="pickpath-input" step="0.01" min="<?= $minBet ?>" max="<?= $maxBet ?>" value="<?= min(1, $maxBet) ?>">
                    <div class="pickpath-bet-buttons">
                        <button type="button" class="pickpath-btn pickpath-btn-half" data-mult="0.5">½</button>
                        <button type="button" class="pickpath-btn pickpath-btn-double" data-mult="2">2×</button>
                    </div>
                </div>
                <small class="pickpath-hint">Min $<?= number_format($minBet, 2) ?> — Max $<?= number_format($maxBet, 2) ?></small>
            </div>
            <div class="pickpath-control-row pickpath-actions">
                <button type="button" id="pickpathStartBtn" class="pickpath-btn pickpath-btn-start"><i class="fas fa-play"></i> Start</button>
                <button type="button" id="pickpathCashoutBtn" class="pickpath-btn pickpath-btn-cashout" disabled><i class="fas fa-hand-holding-usd"></i> Cash out</button>
            </div>
        </section>

        <section class="pickpath-display">
            <div class="pickpath-stat">
                <span class="pickpath-stat-label">Stage</span>
                <span class="pickpath-stat-value" id="pickpathStage">—</span>
            </div>
            <div class="pickpath-stat">
                <span class="pickpath-stat-label">Multiplier</span>
                <span class="pickpath-stat-value" id="pickpathMultiplier">1.00×</span>
            </div>
            <div class="pickpath-stat">
                <span class="pickpath-stat-label">Profit</span>
                <span class="pickpath-stat-value pickpath-profit" id="pickpathProfit">$0.00</span>
            </div>
        </section>

        <section class="pickpath-grid-wrap">
            <p class="pickpath-prompt" id="pickpathPrompt">Start a game, then pick one path (1, 2, or 3). One path is a bust; two are safe.</p>
            <div class="pickpath-grid" id="pickpathGrid" aria-label="Pick a path">
                <button type="button" class="pickpath-node" data-choice="1" disabled aria-label="Path 1"><span class="pickpath-node-num">1</span></button>
                <button type="button" class="pickpath-node" data-choice="2" disabled aria-label="Path 2"><span class="pickpath-node-num">2</span></button>
                <button type="button" class="pickpath-node" data-choice="3" disabled aria-label="Path 3"><span class="pickpath-node-num">3</span></button>
            </div>
            <div class="pickpath-result" id="pickpathResult" style="display:none;"></div>
        </section>
    </main>

    <script>
    window.PICKPATH_CONFIG = {
        minBet: <?= json_encode($minBet) ?>,
        maxBet: <?= json_encode($maxBet) ?>,
        balance: <?= json_encode($balance) ?>
    };
    <?php
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/games/pickpath.php';
    $basePath = dirname(dirname($scriptName));
    $basePath = str_replace('\\', '/', $basePath);
    $apiPath = rtrim($basePath, '/') . '/api/pickpath.php';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $fullApiUrl = $scheme . '://' . $host . (strpos($apiPath, '/') === 0 ? '' : '/') . $apiPath;
    ?>
    window.PICKPATH_API_URL = <?= json_encode($fullApiUrl) ?>;
    </script>
    <script src="../assets/js/toasts.js"></script>
    <script src="../assets/js/pickpath.js"></script>
</body>
</html>
