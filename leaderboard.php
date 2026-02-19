<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$payments = readJSON(PAYMENTS_FILE);
$paygateTx = [];
if (defined('PAYGATETX_FILE') && file_exists(PAYGATETX_FILE)) {
    $paygateTx = json_decode(file_get_contents(PAYGATETX_FILE), true);
}
if (!is_array($paygateTx)) $paygateTx = [];
$allUsers = readJSON(USERS_FILE);
$leaderboardData = getWeeklyLeaderboardData($allUsers, $payments, $paygateTx);

$sortBy = $_GET['sort'] ?? 'score';
if (!in_array($sortBy, ['score', 'deposit', 'referrals'], true)) $sortBy = 'score';

if ($sortBy === 'score') {
    usort($leaderboardData, function ($a, $b) { return $b['score'] <=> $a['score']; });
} elseif ($sortBy === 'deposit') {
    usort($leaderboardData, function ($a, $b) { return $b['weekly_deposit'] <=> $a['weekly_deposit']; });
} else {
    usort($leaderboardData, function ($a, $b) { return $b['referrals'] <=> $a['referrals']; });
}

$leaderboardData = array_slice($leaderboardData, 0, 100);
foreach ($leaderboardData as $i => &$r) {
    $r['rank'] = $i + 1;
}
unset($r);

$sortLabel = $sortBy === 'score' ? 'Score' : ($sortBy === 'deposit' ? 'Weekly Deposit' : 'Referrals');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Leaderboard - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/user-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="user-dashboard">
    <div class="ud-container">
        <header class="ud-header">
            <h1><i class="fas fa-trophy"></i> Weekly Leaderboard</h1>
            <p class="ud-greeting">Top 100 by score, deposit, or referrals (last 7 days)</p>
        </header>

        <section class="ud-card">
            <p class="ud-card-subtitle" style="margin-bottom: 16px;">Score = weekly deposit ($) + (referrals × <?= LEADERBOARD_REFERRAL_POINTS ?> pts). Rank up by depositing and referring friends!</p>
            <div class="ud-tabs" role="tablist" style="margin-bottom: 16px;">
                <a href="?sort=score" class="ud-tab <?= $sortBy === 'score' ? 'active' : '' ?>" role="tab">By Score</a>
                <a href="?sort=deposit" class="ud-tab <?= $sortBy === 'deposit' ? 'active' : '' ?>" role="tab">By Deposit</a>
                <a href="?sort=referrals" class="ud-tab <?= $sortBy === 'referrals' ? 'active' : '' ?>" role="tab">By Referrals</a>
            </div>

            <?php if (empty($leaderboardData)): ?>
                <div class="ud-empty-state">
                    <div class="ud-empty-state-icon"><i class="fas fa-trophy"></i></div>
                    <p>No activity this week yet.</p>
                </div>
            <?php else: ?>
                <div class="ud-table-wrap">
                    <table class="ud-table leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th></th>
                                <th>Username</th>
                                <th>Score</th>
                                <th>Weekly Deposit</th>
                                <th>Referrals</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboardData as $row): ?>
                                <?php $photoUrl = getProfilePhotoUrl($row['user']); ?>
                                <tr>
                                    <td><strong style="color: var(--warning);">#<?= $row['rank'] ?></strong></td>
                                    <td>
                                        <?php if ($photoUrl): ?>
                                            <img src="<?= htmlspecialchars($photoUrl) ?>" alt="" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--bg-card-alt); display: flex; align-items: center; justify-content: center;"><i class="fas fa-user" style="color: var(--text-muted);"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['user']['username'] ?? '') ?></strong></td>
                                    <td><?= number_format($row['score']) ?> pts</td>
                                    <td>$<?= number_format($row['weekly_deposit'], 2) ?></td>
                                    <td><?= (int)$row['referrals'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <nav class="ud-nav">
        <a href="/dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="/deposit.php"><i class="fas fa-wallet"></i> Deposit</a>
        <a href="/games.php"><i class="fas fa-gamepad"></i> Games</a>
        <a href="/leaderboard.php" class="active"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="/profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="/support.php"><i class="fas fa-headset"></i> Support</a>
    </nav>

    <script src="assets/js/main.js"></script>
</body>
</html>
