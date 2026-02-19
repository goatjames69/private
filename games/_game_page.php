<?php
require_once __DIR__ . '/../config.php';
requireLogin();

if (!isset($gameName)) {
    http_response_code(500);
    exit('Game is not configured.');
}

$gameIcon = $gameIcon ?? '🎮';
$gameExternalLink = getGameLink($gameName);

$user = getCurrentUser();
if (empty(trim($user['email'] ?? ''))) {
    header('Location: /profile.php?add_email=1');
    exit;
}
$success = '';
$error = '';

$gameAccounts = isset($user['game_accounts']) && is_array($user['game_accounts']) ? $user['game_accounts'] : [];
$gameAccount = null;
foreach ($gameAccounts as $acc) {
        if (($acc['game'] ?? '') === $gameName) {
            $gameAccount = $acc;
            break;
        }
}

$rolloverInfo = $gameAccount ? getUserGameRolloverInfo($user, $gameName) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_account'])) {
        $hasAccount = $gameAccount !== null;

        if ($hasAccount) {
            $error = 'You already have an account for this game';
        } else {
            $accountRequests = readJSON(GAME_ACCOUNT_REQUESTS_FILE);
            $hasPending = false;
            foreach ($accountRequests as $request) {
                if ($request['user_id'] === $user['id'] && $request['game'] === $gameName && $request['status'] === 'pending') {
                    $hasPending = true;
                    break;
                }
            }

            if ($hasPending) {
                $error = 'You already have a pending username/password request for this game';
            } else {
                $accountRequests[] = [
                    'id' => generateId(),
                    'user_id' => $user['id'],
                    'game' => $gameName,
                    'status' => 'pending',
                    'date' => date('Y-m-d H:i:s')
                ];
                writeJSON(GAME_ACCOUNT_REQUESTS_FILE, $accountRequests);
                $success = 'Account request submitted. Admin will create your account.';
            }
        }
    }

    if (isset($_POST['deposit'])) {
        $amount = floatval($_POST['amount'] ?? 0);

        if ($amount <= 0) {
            $error = 'Amount must be greater than 0';
        } elseif ($amount > $user['balance']) {
            $error = 'Insufficient balance';
        } else {
            $gameRequests = readJSON(GAME_REQUESTS_FILE);

            $request = [
                'id' => generateId(),
                'user_id' => $user['id'],
                'game' => $gameName,
                'amount' => $amount,
                'status' => 'pending',
                'date' => date('Y-m-d H:i:s')
            ];

            $gameRequests[] = $request;
            writeJSON(GAME_REQUESTS_FILE, $gameRequests);
            $success = 'Deposit request submitted! Admin will process it.';
        }
    }

    if (isset($_POST['reset_password'])) {
        if (!$gameAccount) {
            $error = 'You need to have a game account first';
        } else {
            $passwordResetRequests = readJSON(PASSWORD_RESET_REQUESTS_FILE);
            
            $hasPendingRequest = false;
            foreach ($passwordResetRequests as $req) {
                if ($req['user_id'] === $user['id'] && $req['game'] === $gameName && $req['status'] === 'pending') {
                    $hasPendingRequest = true;
                    break;
                }
            }

            if ($hasPendingRequest) {
                $error = 'You already have a pending password reset request for this game';
            } else {
                $request = [
                    'id' => generateId(),
                    'user_id' => $user['id'],
                    'game' => $gameName,
                    'status' => 'pending',
                    'date' => date('Y-m-d H:i:s')
                ];

                $passwordResetRequests[] = $request;
                writeJSON(PASSWORD_RESET_REQUESTS_FILE, $passwordResetRequests);
                $success = 'Password reset request submitted! Admin will process it.';
            }
        }
    }

    if (isset($_POST['withdraw_game'])) {
        $amount = floatval($_POST['amount'] ?? 0);
        $rollover = $gameAccount ? getUserGameRolloverInfo($user, $gameName) : null;

        if ($amount <= 0) {
            $error = 'Amount must be greater than 0';
        } elseif ($rollover === null) {
            $error = 'Unable to verify rollover. Please try again.';
        } else {
            $minWithdrawal = $rollover['min_withdrawal'];
            $maxWithdrawable = $rollover['max_withdrawable'];
            if ($amount < $minWithdrawal) {
                $error = 'Amount must be $' . number_format($minWithdrawal, 2) . ' or more (minimum withdrawal).';
            } elseif ($amount > $maxWithdrawable) {
                $error = 'You can withdraw up to $' . number_format($maxWithdrawable, 2) . ' (4× rollover on deposits + bonus, minus already withdrawn).';
            } else {
                $gameWithdrawals = readJSON(GAME_WITHDRAWALS_FILE);
                $request = [
                    'id' => generateId(),
                    'user_id' => $user['id'],
                    'game' => $gameName,
                    'amount' => $amount,
                    'status' => 'pending',
                    'date' => date('Y-m-d H:i:s')
                ];
                $gameWithdrawals[] = $request;
                writeJSON(GAME_WITHDRAWALS_FILE, $gameWithdrawals);
                $success = 'Game withdrawal request submitted! Admin will add funds back to your balance once approved.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($gameName) ?> - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="display: flex; align-items: center; gap: 12px;">
                <?php
                $logoPath = getGameLogo($gameName);
                if ($logoPath):
                ?>
                    <img src="<?= htmlspecialchars($logoPath) ?>" 
                         alt="<?= htmlspecialchars($gameName) ?> Logo" 
                         style="width: 40px; height: 40px; object-fit: contain; border-radius: 6px;">
                <?php else: ?>
                    <span><?= htmlspecialchars($gameIcon) ?></span>
                <?php endif; ?>
                <span><?= htmlspecialchars($gameName) ?></span>
            </h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($gameExternalLink): ?>
            <div class="card" style="margin-bottom: 20px;">
                <a href="<?= htmlspecialchars($gameExternalLink) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-block" style="font-size: 1.1rem; padding: 14px;">
                    <i class="fas fa-external-link-alt"></i> Open Game
                </a>
            </div>
        <?php endif; ?>

        <?php if ($gameAccount): ?>
            <div class="card">
                <h3 class="card-title">✅ Your Game Account</h3>
                <table class="table">
                    <tr>
                        <td><strong>Username:</strong></td>
                        <td><?= htmlspecialchars($gameAccount['username']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Password:</strong></td>
                        <td>
                            <span id="game-password" style="font-family: monospace;">••••••••</span>
                            <button onclick="toggleGamePassword('<?= htmlspecialchars($gameAccount['password']) ?>')" 
                                    class="btn btn-sm" style="margin-left: 10px;">Show</button>
                        </td>
                    </tr>
                </table>
                <form method="POST" action="" style="margin-top: 15px;">
                    <input type="hidden" name="reset_password" value="1">
                    <button type="submit" class="btn btn-warning btn-block">🔑 Request Password Reset</button>
                </form>
            </div>

            <div class="card">
                <h3 class="card-title"><i class="fas fa-wallet"></i> Deposit to Game</h3>
                <p style="color: var(--text-secondary); margin-bottom: 15px;">
                    Current Balance: <strong style="color: var(--accent-neon);">$<?= number_format($user['balance'], 2) ?></strong>
                    <?php $gs = getGameSettings(); if (($gs['game_deposit_bonus_percent'] ?? 0) > 0): ?>
                        — <strong><?= (int)$gs['game_deposit_bonus_percent'] ?>% bonus</strong> on deposits when approved.
                    <?php endif; ?>
                </p>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Deposit Amount ($) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="1" max="<?= $user['balance'] ?>" required>
                    </div>
                    <input type="hidden" name="deposit" value="1">
                    <button type="submit" class="btn btn-block">Submit Deposit Request</button>
                </form>
            </div>

            <?php if ($rolloverInfo && count($rolloverInfo['deposit_log']) > 0): ?>
            <div class="card">
                <h3 class="card-title"><i class="fas fa-history"></i> Deposit History (<?= htmlspecialchars($gameName) ?>)</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Deposit</th>
                                <th>Bonus</th>
                                <th>Total Credited</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rolloverInfo['deposit_log'] as $log):
                                $base = (float)($log['base_amount'] ?? 0);
                                $bonus = (float)($log['bonus_amount'] ?? 0);
                                $total = isset($log['total_credited']) ? (float)$log['total_credited'] : ($base + $bonus);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($log['date'] ?? '') ?></td>
                                <td>$<?= number_format($base, 2) ?></td>
                                <td><?= $bonus > 0 ? '$' . number_format($bonus, 2) : '—' ?></td>
                                <td><strong>$<?= number_format($total, 2) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 10px;">
                    Total deposited (incl. bonus): <strong>$<?= number_format($rolloverInfo['total_deposit_plus_bonus'], 2) ?></strong>
                    — Withdrawable limit: <strong><?= (int)$rolloverInfo['rollover_multiplier'] ?>×</strong> = $<?= number_format($rolloverInfo['rollover_allowed'], 2) ?>.
                    Already withdrawn: $<?= number_format($rolloverInfo['total_withdrawn'], 2) ?>.
                </p>
            </div>
            <?php endif; ?>

            <div class="card">
                <h3 class="card-title"><i class="fas fa-arrow-left"></i> Withdraw from Game</h3>
                <p style="color: var(--text-secondary); margin-bottom: 15px;">
                    Once approved, the amount will be moved to your main balance. You can then withdraw to Chime or PayPal from the dashboard.
                </p>
                <?php
                $canWithdraw = $rolloverInfo && $rolloverInfo['max_withdrawable'] >= $rolloverInfo['min_withdrawal'];
                ?>
                <?php if ($rolloverInfo): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 15px;">
                    <strong>Rules:</strong> Min <strong>$<?= number_format($rolloverInfo['min_withdrawal'], 2) ?></strong> per withdrawal.
                    You can withdraw up to <strong>$<?= number_format($rolloverInfo['max_withdrawable'], 2) ?></strong> (<?= (int)$rolloverInfo['rollover_multiplier'] ?>× rollover on deposits + bonus, minus already withdrawn).
                </p>
                <?php if ($canWithdraw): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Withdrawal Amount ($)</label>
                        <input type="number" name="amount" class="form-control" step="0.01"
                            min="<?= $rolloverInfo['min_withdrawal'] ?>"
                            max="<?= $rolloverInfo['max_withdrawable'] ?>"
                            placeholder="e.g. 300" required>
                        <small class="form-text text-muted">$<?= number_format($rolloverInfo['min_withdrawal'], 0) ?> – $<?= number_format($rolloverInfo['max_withdrawable'], 0) ?></small>
                    </div>
                    <input type="hidden" name="withdraw_game" value="1">
                    <button type="submit" class="btn btn-success btn-block">Withdraw</button>
                </form>
                <?php else: ?>
                <p style="color: var(--danger, #dc3545); font-size: 0.9rem; margin-bottom: 15px;">
                    You need at least $<?= number_format($rolloverInfo['min_withdrawal'], 0) ?> available to withdraw (<?= (int)$rolloverInfo['rollover_multiplier'] ?>× rollover on deposits + bonus, minus already withdrawn). Deposit more to this game to increase your withdrawable amount.
                </p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <h3 class="card-title">Request Game Account</h3>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    Request a username and password for <?= htmlspecialchars($gameName) ?>. Admin will create your account.
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="request_account" value="1">
                    <button type="submit" class="btn btn-block">Request Username/Password</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <a href="/dashboard.php" class="btn btn-secondary btn-block">← Back to Dashboard</a>
        </div>
    </div>

    <nav class="mobile-nav">
        <a href="/dashboard.php">
            <div class="mobile-nav-icon"><i class="fas fa-home"></i></div>
            <div>Home</div>
        </a>
        <a href="/deposit.php">
            <div class="mobile-nav-icon"><i class="fas fa-wallet"></i></div>
            <div>Deposit</div>
        </a>
        <a href="/dashboard.php#games">
            <div class="mobile-nav-icon"><i class="fas fa-gamepad"></i></div>
            <div>Games</div>
        </a>
        <a href="/profile.php">
            <div class="mobile-nav-icon"><i class="fas fa-user"></i></div>
            <div>Profile</div>
        </a>
    </nav>

    <script>
        function toggleGamePassword(password) {
            const elem = document.getElementById('game-password');
            if (elem.textContent.includes('•')) {
                elem.textContent = password;
            } else {
                elem.textContent = '••••••••';
            }
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>

