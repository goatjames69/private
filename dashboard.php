<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (empty(trim($user['email'] ?? ''))) {
    header('Location: /profile.php?add_email=1');
    exit;
}
$success = '';
$error = '';

// Create uploads directory for user QR codes if it doesn't exist
$userQrDir = __DIR__ . '/uploads/user_qr_codes';
if (!file_exists($userQrDir)) {
    mkdir($userQrDir, 0755, true);
}

// Main balance withdrawal limits (admin-configurable)
$mainWithdrawLimits = getGameSettings();
$minMainWithdrawal = (float) ($mainWithdrawLimits['min_main_withdrawal'] ?? 100);
$maxMainWithdrawal = (float) ($mainWithdrawLimits['max_main_withdrawal'] ?? 500);

// Withdrawal is handled via AJAX (api/withdraw.php) for real-time UX; no page reload
$allGames = getGamesConfig();
$ourGames = [];
$providerGames = [];
foreach ($allGames as $game) {
    $arr = is_array($game) ? $game : ['name' => $game, 'slug' => strtolower(str_replace(' ', '', $game)), 'our_game' => false];
    if (!empty($arr['our_game'])) {
        $ourGames[] = $arr;
    } else {
        $providerGames[] = $arr;
    }
}

$canSpinToday = canUserSpinToday($user);
$spinStreak = getUserSpinStreak($user);
$spinRewards = getSpinRewardsConfig(1);
$spinRewards5 = getSpinRewardsConfig(5);

$payments = readJSON(PAYMENTS_FILE);
$paygateTx = [];
if (defined('PAYGATETX_FILE') && file_exists(PAYGATETX_FILE)) {
    $paygateTx = json_decode(file_get_contents(PAYGATETX_FILE), true);
}
if (!is_array($paygateTx)) $paygateTx = [];
$allUsers = readJSON(USERS_FILE);
$leaderboardTop = getWeeklyLeaderboard($allUsers, $payments, $paygateTx, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/user-dashboard.css">
    <link rel="stylesheet" href="assets/css/realtime.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="user-dashboard">
    <div class="ud-container">
        <header class="ud-header">
            <div style="display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;">
                <h1 style="margin:0;">Welcome back, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>!</h1>
                <button type="button" id="james-notification-trigger" class="james-notification-trigger" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="james-nc-badge" id="james-nc-badge" style="display:none;">0</span>
                </button>
            </div>
            <p class="ud-greeting">Your balance and games</p>
        </header>

        <section class="ud-card leaderboard-widget">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                <h3 class="ud-card-title" style="margin: 0;"><i class="fas fa-trophy"></i> Weekly Leaderboard</h3>
                <a href="/leaderboard.php" class="btn btn-sm" style="font-size: 13px;">View full</a>
            </div>
            <?php if (empty($leaderboardTop)): ?>
                <p style="color: var(--text-muted); font-size: 14px; margin: 0;">No activity this week yet.</p>
            <?php else: ?>
                <div class="leaderboard-list">
                    <?php foreach ($leaderboardTop as $row): ?>
                        <?php $photoUrl = getProfilePhotoUrl($row['user']); ?>
                        <a href="/leaderboard.php" class="leaderboard-row" style="display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border-color); text-decoration: none; color: inherit;">
                            <span class="leaderboard-rank" style="font-weight: 700; min-width: 24px; color: var(--warning);">#<?= $row['rank'] ?></span>
                            <?php if ($photoUrl): ?>
                                <img src="<?= htmlspecialchars($photoUrl) ?>" alt="" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-card-alt); display: flex; align-items: center; justify-content: center;"><i class="fas fa-user" style="font-size: 14px; color: var(--text-muted);"></i></div>
                            <?php endif; ?>
                            <span class="leaderboard-username" style="font-weight: 600; flex: 1;"><?= htmlspecialchars($row['user']['username'] ?? '') ?></span>
                            <span style="font-size: 12px; color: var(--text-muted);"><?= number_format($row['score']) ?> pts</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="ud-balance-card">
            <div class="ud-balance-label">Account Balance</div>
            <div class="ud-balance-amount" id="dashboardBalance">$<?= number_format($user['balance'], 2) ?></div>
            <div class="ud-balance-actions">
                <a href="/deposit.php" class="btn btn-block">Deposit</a>
                <button type="button" onclick="document.getElementById('withdrawModal').style.display='flex'" class="btn btn-success btn-block">Withdraw</button>
            </div>
        </div>

        <!-- Spin Wheel: Free 1/day or Spin for $5 -->
        <?php $canPaidSpin5 = $user['balance'] >= 5; ?>
        <section class="ud-spin-card">
            <div class="ud-spin-header">
                <span class="ud-spin-title"><i class="fas fa-gift"></i> Spin Wheel</span>
                <?php if ($canSpinToday): ?>
                    <span class="ud-spin-badge">1 Free today</span>
                <?php endif; ?>
            </div>
            <?php if ($spinStreak > 0): ?>
                <p class="ud-spin-streak"><i class="fas fa-fire"></i> <?= $spinStreak ?> day streak</p>
            <?php endif; ?>
            <p class="ud-spin-hint">Free spin once per day, or pay $5 to spin (bigger prize pool)!</p>
            <div class="ud-spin-cta-wrap ud-spin-buttons">
                <?php if ($canSpinToday): ?>
                    <button type="button" id="openSpinWheelBtn" class="ud-spin-btn ud-spin-btn-free"><i class="fas fa-gift"></i> Free Spin</button>
                <?php else: ?>
                    <button type="button" class="ud-spin-btn" disabled><i class="fas fa-check"></i> Free spin used</button>
                <?php endif; ?>
                <?php if ($canPaidSpin5): ?>
                    <button type="button" id="openSpinWheelPaid5Btn" class="ud-spin-btn ud-spin-btn-paid" data-spin-cost="5"><i class="fas fa-coins"></i> Spin for $5</button>
                <?php else: ?>
                    <button type="button" class="ud-spin-btn ud-spin-btn-paid" data-spin-cost="5" disabled title="Need $5.00 balance"><i class="fas fa-coins"></i> Spin for $5</button>
                <?php endif; ?>
            </div>
            <?php if (!$canSpinToday && !$canPaidSpin5): ?>
                <p class="ud-spin-tomorrow">Come back tomorrow for a free spin, or deposit to spin for $5!</p>
            <?php endif; ?>
        </section>

        <div id="dashboardAlert" class="alert" style="display:none;"></div>

        <!-- Withdrawal Modal -->
        <div id="withdrawModal" class="ud-modal-overlay" style="display: none;">
            <div class="ud-modal">
                <div class="ud-modal-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Request Withdrawal</h3>
                    <button type="button" class="ud-modal-close" onclick="document.getElementById('withdrawModal').style.display='none'" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="ud-modal-body">
                    <form id="withdrawForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Withdrawal Amount ($) *</label>
                            <input type="number" name="amount" id="withdrawAmount" class="form-control" step="0.01"
                                min="<?= $minMainWithdrawal ?>" max="<?= min($maxMainWithdrawal, $user['balance']) ?>"
                                placeholder="<?= number_format($minMainWithdrawal, 0) ?>–<?= number_format($maxMainWithdrawal, 0) ?>" required>
                            <small style="color: var(--text-secondary);">Min $<?= number_format($minMainWithdrawal, 0) ?> — Max $<?= number_format($maxMainWithdrawal, 0) ?>. Available: $<span id="withdrawAvailable"><?= number_format($user['balance'], 2) ?></span></small>
                        </div>
                        <div class="form-group">
                            <label>Withdrawal Method *</label>
                            <select name="method" class="form-control" required>
                                <option value="">Select Method</option>
                                <option value="chime">Chime</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Account Information *</label>
                            <textarea name="account_info" class="form-control" required placeholder="Chime tag, PayPal email..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Your QR Code (Optional)</label>
                            <input type="file" name="qr_code" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control">
                        </div>
                        <button type="submit" id="withdrawSubmitBtn" class="btn btn-block"><span class="btn-text">Submit Request</span></button>
                        <button type="button" onclick="document.getElementById('withdrawModal').style.display='none'" class="btn btn-secondary btn-block" style="margin-top: 10px;">Cancel</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Spin Wheel Modal -->
        <div id="spinWheelModal" class="ud-modal-overlay spin-wheel-modal-overlay" style="display: none;" role="dialog" aria-label="Spin Wheel">
            <div class="ud-modal spin-wheel-modal" onclick="event.stopPropagation()">
                <div class="ud-modal-header">
                    <h3><i class="fas fa-gift"></i> Daily Free Spin</h3>
                    <button type="button" id="spinWheelCloseBtn" class="ud-modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="ud-modal-body">
                    <div class="spin-wheel-container">
                        <div class="spin-wheel-pointer"></div>
                        <div class="spin-wheel-outer">
                            <div class="spin-wheel-inner" id="spinWheelSegments"></div>
                            <div class="spin-wheel-center">SPIN</div>
                        </div>
                    </div>
                    <div id="spinResultBox" class="spin-result-box" style="display: none;">
                        <div class="spin-result-label">You won</div>
                        <div id="spinResultValue" class="spin-result-value">$0.00</div>
                    </div>
                    <button type="button" id="spinWheelSpinBtn" class="ud-spin-btn spin-wheel-modal-btn"><i class="fas fa-sync-alt"></i> Free Spin</button>
                </div>
            </div>
        </div>

        <section class="ud-card" id="games">
            <h3 class="ud-card-title"><i class="fas fa-gamepad"></i> Games</h3>
            <?php if (!empty($ourGames)): ?>
            <h4 class="ud-games-section-title"><i class="fas fa-star"></i> Our Games</h4>
            <div class="ud-games-grid">
                <?php foreach ($ourGames as $game):
                    $gameName = $game['name'] ?? '';
                    $gameSlug = $game['slug'] ?? strtolower(str_replace(' ', '', $gameName));
                    $gameAccount = null;
                    foreach ($user['game_accounts'] ?? [] as $acc) {
                        if (($acc['game'] ?? '') === $gameName) { $gameAccount = $acc; break; }
                    }
                    $logoPath = getGameLogo($game);
                ?>
                    <a href="/games/play.php?g=<?= htmlspecialchars($gameSlug) ?>" class="ud-game-card">
                        <div class="ud-game-icon">
                            <?php if ($logoPath): ?>
                                <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($gameName) ?>">
                            <?php else: ?>
                                <i class="fas fa-gamepad" style="font-size: 28px; color: var(--text-muted);"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ud-game-name"><?= htmlspecialchars($gameName) ?></div>
                        <?php if ($gameAccount): ?><div class="ud-game-badge"><i class="fas fa-check-circle"></i> Ready</div><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($providerGames)): ?>
            <h4 class="ud-games-section-title"><i class="fas fa-puzzle-piece"></i> Provider Games</h4>
            <div class="ud-games-grid">
                <?php foreach ($providerGames as $game):
                    $gameName = $game['name'] ?? '';
                    $gameSlug = $game['slug'] ?? strtolower(str_replace(' ', '', $gameName));
                    $gameAccount = null;
                    foreach ($user['game_accounts'] ?? [] as $acc) {
                        if (($acc['game'] ?? '') === $gameName) { $gameAccount = $acc; break; }
                    }
                    $logoPath = getGameLogo($game);
                ?>
                    <a href="/games/play.php?g=<?= htmlspecialchars($gameSlug) ?>" class="ud-game-card">
                        <div class="ud-game-icon">
                            <?php if ($logoPath): ?>
                                <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($gameName) ?>">
                            <?php else: ?>
                                <i class="fas fa-gamepad" style="font-size: 28px; color: var(--text-muted);"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ud-game-name"><?= htmlspecialchars($gameName) ?></div>
                        <?php if ($gameAccount): ?><div class="ud-game-badge"><i class="fas fa-check-circle"></i> Ready</div><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="ud-card ud-card-cta">
            <a href="/profile.php#activity" class="ud-activity-link">
                <i class="fas fa-history"></i>
                <span>View all deposits, withdrawals & activity in Profile</span>
                <i class="fas fa-chevron-right"></i>
            </a>
        </section>
    </div>

    <nav class="ud-nav">
        <a href="/dashboard.php" class="active"><i class="fas fa-home"></i> Home</a>
        <a href="/deposit.php"><i class="fas fa-wallet"></i> Deposit</a>
        <a href="/games.php"><i class="fas fa-gamepad"></i> Games</a>
        <a href="/leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="/profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="/support.php"><i class="fas fa-headset"></i> Support</a>
    </nav>

    <script type="application/json" id="spinRewardsData"><?= json_encode(array_map(function($r) { return ['label' => $r['label']]; }, $spinRewards)) ?></script>
    <script type="application/json" id="spinRewards5Data"><?= json_encode(array_map(function($r) { return ['label' => $r['label']]; }, $spinRewards5)) ?></script>
    <script type="application/json" id="james-realtime-auth"><?= json_encode(['user_id' => $user['id'] ?? null, 'role' => 'user']) ?></script>
    <script>
    (function() {
        var dataEl = document.getElementById('spinRewardsData');
        if (dataEl) {
            var script = document.createElement('script');
            script.type = 'text/javascript';
            script.setAttribute('data-spin-rewards', dataEl.textContent);
            document.body.appendChild(script);
        }
        var data5El = document.getElementById('spinRewards5Data');
        if (data5El) {
            var script5 = document.createElement('script');
            script5.type = 'text/javascript';
            script5.setAttribute('data-spin-rewards-5', data5El.textContent);
            document.body.appendChild(script5);
        }
    })();
    window.updateDashboardBalance = function(bal) {
        var el = document.getElementById('dashboardBalance');
        if (el) el.textContent = '$' + parseFloat(bal).toFixed(2);
        var avail = document.getElementById('withdrawAvailable');
        if (avail) avail.textContent = parseFloat(bal).toFixed(2);
    };
    </script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/toasts.js"></script>
    <script src="assets/js/realtime.js"></script>
    <script src="assets/js/dashboard-realtime.js"></script>
    <script src="assets/js/spin-wheel.js"></script>
</body>
</html>
