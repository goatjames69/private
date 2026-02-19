<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$profileSuccess = '';
$profileError = '';

// Ensure user has a referral code (for existing users who registered before referral feature)
$users = readJSON(USERS_FILE);
$userReferralCode = ensureUserReferralCode($user, $users);
$user = getCurrentUser();

$profilePhotoDir = __DIR__ . '/uploads/profile_photos';
if (!file_exists($profilePhotoDir)) {
    mkdir($profilePhotoDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_photo']) && !empty($_FILES['profile_photo']['tmp_name'])) {
    $file = $_FILES['profile_photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    if (!in_array($file['type'] ?? '', $allowed)) {
        $profileError = 'Please upload a JPEG, PNG, GIF, or WebP image.';
    } elseif ($file['size'] > $maxSize) {
        $profileError = 'Image must be under 2MB.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $user['id']) . '.' . $ext;
        $path = $profilePhotoDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $path)) {
            $relativePath = 'uploads/profile_photos/' . $filename;
            $users = readJSON(USERS_FILE);
            foreach ($users as &$u) {
                if (($u['id'] ?? '') === $user['id']) {
                    $u['profile_photo'] = $relativePath;
                    $user = $u;
                    break;
                }
            }
            writeJSON(USERS_FILE, $users);
            $profileSuccess = 'Profile photo updated. It will appear on the leaderboard.';
        } else {
            $profileError = 'Failed to save image.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    $newEmail = trim($_POST['email'] ?? '');
    if (empty($newEmail)) {
        $profileError = 'Email is required.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $profileError = 'Please enter a valid email address.';
    } else {
        $users = readJSON(USERS_FILE);
        foreach ($users as $u) {
            if (!empty($u['email']) && strtolower($u['email']) === strtolower($newEmail) && ($u['id'] ?? '') !== $user['id']) {
                $profileError = 'This email is already used by another account.';
                break;
            }
        }
        if ($profileError === '') {
            foreach ($users as &$u) {
                if (($u['id'] ?? '') === $user['id']) {
                    $u['email'] = $newEmail;
                    $user = $u;
                    break;
                }
            }
            unset($u);
            writeJSON(USERS_FILE, $users);
            $profileSuccess = 'Email updated successfully.';
        }
    }
}

$payments = readJSON(PAYMENTS_FILE);
$withdrawalRequests = readJSON(WITHDRAWAL_REQUESTS_FILE);
$gameRequests = readJSON(GAME_REQUESTS_FILE);
$gameWithdrawals = readJSON(GAME_WITHDRAWALS_FILE);

$userPayments = array_filter($payments, function($p) use ($user) { return $p['user_id'] === $user['id']; });
$userPayments = array_reverse(array_values($userPayments));

$userWithdrawals = array_filter($withdrawalRequests, function($w) use ($user) { return $w['user_id'] === $user['id']; });
$userWithdrawals = array_reverse(array_values($userWithdrawals));

$userGameRequests = array_filter($gameRequests, function($r) use ($user) { return $r['user_id'] === $user['id']; });
$userGameRequests = array_reverse(array_values($userGameRequests));

$userGameWithdrawals = array_filter($gameWithdrawals, function($w) use ($user) { return $w['user_id'] === $user['id']; });
$userGameWithdrawals = array_reverse(array_values($userGameWithdrawals));

$allLogs = [];
foreach ($userPayments as $p) {
    $allLogs[] = ['type' => 'Deposit', 'detail' => '$' . number_format($p['amount'], 2) . ' · ' . ucfirst($p['method'] ?? ''), 'date' => $p['date'], 'status' => $p['status'] ?? 'pending'];
}
foreach ($userWithdrawals as $w) {
    $allLogs[] = ['type' => 'Withdrawal', 'detail' => '$' . number_format($w['amount'], 2) . ' · ' . ucfirst($w['method'] ?? ''), 'date' => $w['date'], 'status' => $w['status']];
}
foreach ($userGameRequests as $r) {
    $allLogs[] = ['type' => 'Game Deposit', 'detail' => $r['game'] . ' · $' . number_format($r['amount'], 2), 'date' => $r['date'], 'status' => $r['status']];
}
foreach ($userGameWithdrawals as $w) {
    $allLogs[] = ['type' => 'Game Withdraw', 'detail' => $w['game'] . ' · $' . number_format($w['amount'], 2), 'date' => $w['date'], 'status' => $w['status']];
}
$referralBonusHistory = $user['referral_bonus_history'] ?? [];
if (!is_array($referralBonusHistory)) $referralBonusHistory = [];
foreach ($referralBonusHistory as $rb) {
    $detail = '$' . number_format((float)($rb['amount'] ?? 0), 2) . ' · Referral bonus';
    if (!empty($rb['referred_username'])) {
        $detail .= ' (from ' . ($rb['referred_username']) . ')';
    } else {
        $detail .= ' (from referral)';
    }
    $allLogs[] = ['type' => 'Referral Bonus', 'detail' => $detail, 'date' => $rb['date'] ?? date('Y-m-d H:i:s'), 'status' => 'approved'];
}
usort($allLogs, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Profile - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/realtime.css">
    <link rel="stylesheet" href="assets/css/user-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="user-dashboard">
    <div class="ud-container">
        <header class="ud-profile-header">
            <h1><i class="fas fa-user-circle"></i> Profile</h1>
            <p>Account info, game accounts & full activity history</p>
        </header>

        <?php
        $userEmail = trim($user['email'] ?? '');
        $needsEmail = $userEmail === '';
        $addEmailPrompt = isset($_GET['add_email']) || $needsEmail;
        ?>
        <?php if ($addEmailPrompt && $needsEmail): ?>
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <strong><i class="fas fa-envelope"></i> Please add your email.</strong> Email is required for your account. Add it below.
            </div>
        <?php endif; ?>
        <?php if ($profileSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($profileSuccess) ?></div>
        <?php endif; ?>
        <?php if ($profileError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($profileError) ?></div>
        <?php endif; ?>

        <section class="ud-card">
            <h3 class="ud-card-title"><i class="fas fa-user-circle"></i> Profile Photo</h3>
            <p class="ud-card-subtitle" style="margin-bottom: 12px;">Your photo is shown on the leaderboard. Max 2MB; JPEG, PNG, GIF, or WebP.</p>
            <?php $photoUrl = getProfilePhotoUrl($user); ?>
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
                <?php if ($photoUrl): ?>
                    <img src="<?= htmlspecialchars($photoUrl) ?>?t=<?= time() ?>" alt="Profile" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);">
                <?php else: ?>
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--bg-card-alt); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center;"><i class="fas fa-user" style="font-size: 32px; color: var(--text-muted);"></i></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="update_profile_photo" value="1">
                    <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control" style="max-width: 220px;" required>
                    <button type="submit" class="btn btn-sm">Upload Photo</button>
                </form>
            </div>
        </section>

        <section class="ud-card">
            <h3 class="ud-card-title"><i class="fas fa-envelope"></i> Email (required)</h3>
            <?php if ($userEmail): ?>
                <p style="margin: 0 0 12px 0;">Current: <strong><?= htmlspecialchars($userEmail) ?></strong></p>
            <?php else: ?>
                <p style="margin: 0 0 12px 0; color: var(--warning);">Not set. Please add your email below.</p>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="update_email" value="1">
                <div class="form-group" style="max-width: 400px;">
                    <label for="profile_email"><?= $userEmail ? 'Update email' : 'Your email *' ?></label>
                    <input type="email" id="profile_email" name="email" class="form-control" required value="<?= htmlspecialchars($userEmail) ?>" placeholder="you@example.com">
                </div>
                <button type="submit" class="btn btn-block" style="max-width: 400px;"><?= $userEmail ? 'Update Email' : 'Save Email' ?></button>
            </form>
        </section>

        <section class="ud-card">
            <h3 class="ud-card-title"><i class="fas fa-user-friends"></i> Refer a Friend</h3>
            <p class="ud-card-subtitle" style="margin-bottom: 12px;">Share your referral code. When a friend registers with it and makes their <strong>first deposit</strong>, you receive a <strong>50% bonus</strong> of that deposit amount. They get no bonus.</p>
            <div class="referral-code-wrap" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px;">
                <input type="text" id="profile-referral-code" class="form-control" value="<?= htmlspecialchars($user['referral_code'] ?? $userReferralCode) ?>" readonly style="max-width: 180px; font-family: ui-monospace, monospace; font-weight: 600; letter-spacing: 0.08em;">
                <button type="button" id="copy-referral-code" class="btn btn-sm" aria-label="Copy referral code"><i class="fas fa-copy"></i> Copy</button>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Your friends enter this code when registering (optional field).</p>
        </section>

        <section class="ud-card">
            <h3 class="ud-card-title"><i class="fas fa-id-card"></i> Account Information</h3>
            <table class="ud-info-table">
                <tr>
                    <td>Full Name</td>
                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                </tr>
                <tr>
                    <td>Username</td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td><?= $userEmail ? htmlspecialchars($userEmail) : '<span style="color: var(--text-muted);">Not set</span>' ?></td>
                </tr>
                <tr>
                    <td>Phone</td>
                    <td><?= htmlspecialchars($user['phone']) ?></td>
                </tr>
                <tr>
                    <td>Balance</td>
                    <td style="color: var(--success); font-weight: 700;">$<?= number_format($user['balance'], 2) ?></td>
                </tr>
            </table>
        </section>

        <section class="ud-card">
            <h3 class="ud-card-title"><i class="fas fa-gamepad"></i> Game Accounts</h3>
            <?php if (empty($user['game_accounts'])): ?>
                <div class="ud-empty-state">
                    <div class="ud-empty-state-icon"><i class="fas fa-dice"></i></div>
                    <p>No game accounts yet. Request an account from any game page.</p>
                </div>
            <?php else: ?>
                <div class="ud-table-wrap">
                    <table class="ud-table">
                        <thead>
                            <tr><th>Game</th><th>Username</th><th>Password</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user['game_accounts'] as $account): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($account['game']) ?></strong></td>
                                    <td><?= htmlspecialchars($account['username']) ?></td>
                                    <td>
                                        <span id="pwd-<?= htmlspecialchars($account['game']) ?>" style="font-family: monospace;">••••••••</span>
                                        <button type="button" class="btn btn-sm ud-pwd-toggle" style="margin-left: 8px;" data-game="<?= htmlspecialchars($account['game']) ?>" data-password="<?= htmlspecialchars($account['password']) ?>">Show</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="ud-card" id="activity">
            <h3 class="ud-card-title"><i class="fas fa-history"></i> Activity & Logs</h3>
            <p class="ud-card-subtitle">View all your deposits, withdrawals and requests</p>
            <div class="ud-tabs" role="tablist">
                <button type="button" class="ud-tab active" data-tab="all" role="tab">All</button>
                <button type="button" class="ud-tab" data-tab="deposits" role="tab">Deposits</button>
                <button type="button" class="ud-tab" data-tab="withdrawals" role="tab">Withdrawals</button>
                <button type="button" class="ud-tab" data-tab="game-deposits" role="tab">Game Deposits</button>
                <button type="button" class="ud-tab" data-tab="game-withdrawals" role="tab">Game Withdrawals</button>
            </div>

            <div id="pane-all" class="ud-tab-pane active" role="tabpanel">
                <?php if (empty($allLogs)): ?>
                    <div class="ud-empty-state"><div class="ud-empty-state-icon"><i class="fas fa-clipboard-list"></i></div><p>No activity yet</p></div>
                <?php else: ?>
                    <div class="ud-table-wrap">
                        <table class="ud-table">
                            <thead><tr><th>Type</th><th>Detail</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($allLogs as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['type']) ?></td>
                                        <td><?= htmlspecialchars($row['detail']) ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($row['date'])) ?></td>
                                        <td><span class="badge badge-<?= htmlspecialchars($row['status']) ?>"><?= ucfirst($row['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="pane-deposits" class="ud-tab-pane" role="tabpanel">
                <?php if (empty($userPayments)): ?>
                    <div class="ud-empty-state"><div class="ud-empty-state-icon"><i class="fas fa-wallet"></i></div><p>No deposits yet</p></div>
                <?php else: ?>
                    <div class="ud-table-wrap">
                        <table class="ud-table">
                            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($userPayments as $p): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($p['date'])) ?></td>
                                        <td>$<?= number_format($p['amount'], 2) ?></td>
                                        <td><?= ucfirst($p['method'] ?? '') ?></td>
                                        <td><span class="badge badge-<?= htmlspecialchars($p['status'] ?? 'pending') ?>"><?= ucfirst($p['status'] ?? 'Pending') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="pane-withdrawals" class="ud-tab-pane" role="tabpanel">
                <?php if (empty($userWithdrawals)): ?>
                    <div class="ud-empty-state"><div class="ud-empty-state-icon"><i class="fas fa-building-columns"></i></div><p>No withdrawal requests yet</p></div>
                <?php else: ?>
                    <div class="ud-table-wrap">
                        <table class="ud-table">
                            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($userWithdrawals as $w): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($w['date'])) ?></td>
                                        <td>$<?= number_format($w['amount'], 2) ?></td>
                                        <td><?= ucfirst($w['method'] ?? '') ?></td>
                                        <td><span class="badge badge-<?= htmlspecialchars($w['status']) ?>"><?= ucfirst($w['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="pane-game-deposits" class="ud-tab-pane" role="tabpanel">
                <?php if (empty($userGameRequests)): ?>
                    <div class="ud-empty-state"><div class="ud-empty-state-icon"><i class="fas fa-gamepad"></i></div><p>No game deposit requests yet</p></div>
                <?php else: ?>
                    <div class="ud-table-wrap">
                        <table class="ud-table">
                            <thead><tr><th>Date</th><th>Game</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($userGameRequests as $r): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($r['date'])) ?></td>
                                        <td><?= htmlspecialchars($r['game']) ?></td>
                                        <td>$<?= number_format($r['amount'], 2) ?></td>
                                        <td><span class="badge badge-<?= htmlspecialchars($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="pane-game-withdrawals" class="ud-tab-pane" role="tabpanel">
                <?php if (empty($userGameWithdrawals)): ?>
                    <div class="ud-empty-state"><div class="ud-empty-state-icon"><i class="fas fa-dice"></i></div><p>No game withdrawal requests yet</p></div>
                <?php else: ?>
                    <div class="ud-table-wrap">
                        <table class="ud-table">
                            <thead><tr><th>Date</th><th>Game</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($userGameWithdrawals as $w): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($w['date'])) ?></td>
                                        <td><?= htmlspecialchars($w['game']) ?></td>
                                        <td>$<?= number_format($w['amount'], 2) ?></td>
                                        <td><span class="badge badge-<?= htmlspecialchars($w['status']) ?>"><?= ucfirst($w['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="ud-card ud-logout-wrap">
            <a href="/auth/logout.php" class="btn btn-danger btn-block"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </section>
    </div>

    <nav class="ud-nav">
        <a href="/dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="/deposit.php"><i class="fas fa-wallet"></i> Deposit</a>
        <a href="/games.php"><i class="fas fa-gamepad"></i> Games</a>
        <a href="/leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="/profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
        <a href="/support.php"><i class="fas fa-headset"></i> Support</a>
    </nav>

    <script>
    (function() {
        var tabs = document.querySelectorAll('.ud-tabs .ud-tab');
        var panes = document.querySelectorAll('.ud-tab-pane');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var id = tab.getAttribute('data-tab');
                tabs.forEach(function(t) { t.classList.remove('active'); });
                panes.forEach(function(p) {
                    p.classList.remove('active');
                    if (p.id === 'pane-' + id) p.classList.add('active');
                });
                tab.classList.add('active');
            });
        });
    })();
    document.querySelectorAll('.ud-pwd-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var game = btn.getAttribute('data-game');
            var password = btn.getAttribute('data-password') || '';
            var el = document.getElementById('pwd-' + game);
            if (!el) return;
            if (el.textContent.indexOf('•') !== -1) {
                el.textContent = password;
                btn.textContent = 'Hide';
            } else {
                el.textContent = '••••••••';
                btn.textContent = 'Show';
            }
        });
    });
    var copyRefBtn = document.getElementById('copy-referral-code');
    var refInput = document.getElementById('profile-referral-code');
    if (copyRefBtn && refInput) {
        copyRefBtn.addEventListener('click', function() {
            refInput.select();
            refInput.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                copyRefBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
                if (window.JamesToasts) window.JamesToasts.success('Referral code copied');
                setTimeout(function() { copyRefBtn.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 2000);
            } catch (e) {}
        });
    }
    </script>
    <script src="assets/js/toasts.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
