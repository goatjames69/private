<?php
require_once '../config.php';
requireStaffOrAdmin();
if (!canAccess('game_accounts')) {
    header('Location: /admin/dashboard.php');
    exit;
}

$users = readJSON(USERS_FILE);
foreach ($users as &$u) {
    if (!isset($u['game_accounts']) || !is_array($u['game_accounts'])) {
        $u['game_accounts'] = [];
    }
}
unset($u);
$gameRequests = readJSON(GAME_REQUESTS_FILE);
$passwordResetRequests = readJSON(PASSWORD_RESET_REQUESTS_FILE);
$gameWithdrawals = readJSON(GAME_WITHDRAWALS_FILE);
$accountRequests = readJSON(GAME_ACCOUNT_REQUESTS_FILE);
$success = '';
$error = '';

function findUserById(array $users, $userId) {
    foreach ($users as $user) {
        if (($user['id'] ?? null) === $userId) return $user;
    }
    return null;
}

function getUsername($user) {
    return is_array($user) && isset($user['username']) ? $user['username'] : 'Unknown';
}

function getGameUsername($user, $game) {
    if (!$user || empty($user['game_accounts'])) return '—';
    foreach ($user['game_accounts'] as $account) {
        if (($account['game'] ?? '') === $game) return $account['username'] ?? '—';
    }
    return '—';
}

// Handle game account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    $userId = $_POST['user_id'] ?? '';
    $game = trim($_POST['game'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($game) || empty($username) || empty($password)) {
        $error = 'All fields are required';
    } else {
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $exists = false;
                foreach ($user['game_accounts'] as $acc) {
                    if ($acc['game'] === $game) { $exists = true; break; }
                }
                if ($exists) {
                    $error = 'User already has an account for this game';
                } else {
                    $user['game_accounts'][] = ['game' => $game, 'username' => $username, 'password' => $password];
                    writeJSON(USERS_FILE, $users);
                    $success = 'Game account created successfully';
                    foreach ($accountRequests as &$req) {
                        if ($req['user_id'] === $userId && $req['game'] === $game && $req['status'] === 'pending') {
                            $req['status'] = 'completed';
                            $req['completed_at'] = date('Y-m-d H:i:s');
                            writeJSON(GAME_ACCOUNT_REQUESTS_FILE, $accountRequests);
                            break;
                        }
                    }
                    unset($req);
                }
                break;
            }
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $requestId = $_POST['request_id'] ?? '';
    $newPassword = trim($_POST['new_password'] ?? '');
    if (empty($newPassword)) {
        $error = 'New password is required';
    } else {
        foreach ($passwordResetRequests as &$request) {
            if ($request['id'] === $requestId && $request['status'] === 'pending') {
                $request['status'] = 'completed';
                foreach ($users as &$user) {
                    if ($user['id'] === $request['user_id']) {
                        foreach ($user['game_accounts'] as &$account) {
                            if ($account['game'] === $request['game']) {
                                $account['password'] = $newPassword;
                                writeJSON(USERS_FILE, $users);
                                writeJSON(PASSWORD_RESET_REQUESTS_FILE, $passwordResetRequests);
                                $success = 'Password reset completed successfully';
                                break 2;
                            }
                        }
                    }
                }
                break;
            }
        }
    }
}

// Handle account request status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_request_id'])) {
    $requestId = $_POST['account_request_id'] ?? '';
    $action = $_POST['action'] ?? '';
    foreach ($accountRequests as &$request) {
        if ($request['id'] === $requestId && $request['status'] === 'pending') {
            $request['status'] = $action === 'complete' ? 'completed' : 'rejected';
            $request['completed_at'] = date('Y-m-d H:i:s');
            writeJSON(GAME_ACCOUNT_REQUESTS_FILE, $accountRequests);
            $success = $action === 'complete' ? 'Account request marked as completed' : 'Account request rejected';
            break;
        }
    }
    unset($request);
}

// Handle game withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['game_withdrawal_id'])) {
    $requestId = $_POST['game_withdrawal_id'] ?? '';
    $action = $_POST['action'] ?? '';
    foreach ($gameWithdrawals as &$request) {
        if ($request['id'] === $requestId && $request['status'] === 'pending') {
            if ($action === 'approve') {
                $request['status'] = 'approved';
                foreach ($users as &$user) {
                    if ($user['id'] === $request['user_id']) {
                        if (!isset($user['game_withdrawal_history']) || !is_array($user['game_withdrawal_history'])) {
                            $user['game_withdrawal_history'] = [];
                        }
                        $user['balance'] += $request['amount'];
                        $user['game_withdrawal_history'][] = ['id' => $requestId, 'game' => $request['game'], 'amount' => $request['amount'], 'date' => date('Y-m-d H:i:s')];
                        writeJSON(GAME_WITHDRAWALS_FILE, $gameWithdrawals);
                        writeJSON(USERS_FILE, $users);
                        $success = 'Game withdrawal approved and user balance updated';
                        break 2;
                    }
                }
            } elseif ($action === 'reject') {
                $request['status'] = 'rejected';
                writeJSON(GAME_WITHDRAWALS_FILE, $gameWithdrawals);
                $success = 'Game withdrawal rejected';
            }
            break;
        }
    }
    unset($request);
}

// Handle game deposit approval (with bonus from game settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_deposit'])) {
    $requestId = $_POST['request_id'] ?? '';
    $settings = getGameSettings();
    $bonusPercent = (float) ($settings['game_deposit_bonus_percent'] ?? 0);
    foreach ($gameRequests as &$request) {
        if ($request['id'] === $requestId && $request['status'] === 'pending') {
            foreach ($users as &$user) {
                if ($user['id'] === $request['user_id']) {
                    if ($user['balance'] >= $request['amount']) {
                        $baseAmount = (float) $request['amount'];
                        $bonusAmount = round($baseAmount * ($bonusPercent / 100), 2);
                        $user['balance'] -= $baseAmount;
                        if (!isset($user['game_deposit_log']) || !is_array($user['game_deposit_log'])) {
                            $user['game_deposit_log'] = [];
                        }
                        $user['game_deposit_log'][] = [
                            'request_id' => $requestId,
                            'game' => $request['game'],
                            'base_amount' => $baseAmount,
                            'bonus_amount' => $bonusAmount,
                            'total_credited' => $baseAmount + $bonusAmount,
                            'date' => date('Y-m-d H:i:s')
                        ];
                        $request['status'] = 'approved';
                        $request['base_amount'] = $baseAmount;
                        $request['bonus_amount'] = $bonusAmount;
                        writeJSON(USERS_FILE, $users);
                        writeJSON(GAME_REQUESTS_FILE, $gameRequests);
                        $success = 'Game deposit approved' . ($bonusAmount > 0 ? ' (+ $' . number_format($bonusAmount, 2) . ' bonus)' : '');
                    } else {
                        $error = 'Insufficient user balance';
                    }
                    break;
                }
            }
            break;
        }
    }
}

$pendingAccount = count(array_filter($accountRequests, function($r) { return $r['status'] === 'pending'; }));
$pendingDeposits = count(array_filter($gameRequests, function($r) { return $r['status'] === 'pending'; }));
$pendingGameWith = count(array_filter($gameWithdrawals, function($r) { return $r['status'] === 'pending'; }));
$pendingResetsCount = count(array_filter($passwordResetRequests, function($r) { return $r['status'] === 'pending'; }));

$pendingCounts = [
    'payments' => 0,
    'withdrawals' => 0,
    'game_requests' => $pendingDeposits,
    'account_requests' => $pendingAccount
];

$tab = $_GET['tab'] ?? 'create';
$filter = $_GET['filter'] ?? 'all';
$gameRequestsFiltered = $gameRequests;
if ($filter !== 'all') {
    $gameRequestsFiltered = array_filter($gameRequests, function($r) use ($filter) { return $r['status'] === $filter; });
}
$gameRequestsFiltered = array_reverse(array_values($gameRequestsFiltered));

$pendingAccountRequests = array_reverse(array_values(array_filter($accountRequests, function($r) { return $r['status'] === 'pending'; })));
$pendingGameWithdrawals = array_reverse(array_values(array_filter($gameWithdrawals, function($r) { return $r['status'] === 'pending'; })));
$pendingResets = array_reverse(array_values(array_filter($passwordResetRequests, function($r) { return $r['status'] === 'pending'; })));

$gamesConfig = getGamesConfig();
$games = array_map(function($g) { return is_array($g) ? ($g['name'] ?? '') : $g; }, $gamesConfig);

$adminPageTitle = 'Game Accounts';
$adminCurrentPage = 'games';
$adminPageSubtitle = 'Account requests, game deposits & withdrawals, password resets';
require __DIR__ . '/_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-tabs">
    <a href="?tab=create" class="admin-tab <?= $tab === 'create' ? 'active' : '' ?>"><i class="fas fa-plus-circle"></i> Create Account</a>
    <a href="?tab=account_requests" class="admin-tab <?= $tab === 'account_requests' ? 'active' : '' ?>">
        <i class="fas fa-user-plus"></i> Account Requests
        <?php if ($pendingAccount > 0): ?><span class="admin-tab-badge" style="background: var(--warning); color: #1a1d29;"><?= $pendingAccount ?></span><?php endif; ?>
    </a>
    <a href="?tab=game_deposits" class="admin-tab <?= $tab === 'game_deposits' ? 'active' : '' ?>">
        <i class="fas fa-gamepad"></i> Game Deposits
        <?php if ($pendingDeposits > 0): ?><span class="admin-tab-badge" style="background: var(--warning); color: #1a1d29;"><?= $pendingDeposits ?></span><?php endif; ?>
    </a>
    <a href="?tab=game_withdrawals" class="admin-tab <?= $tab === 'game_withdrawals' ? 'active' : '' ?>">
        <i class="fas fa-exchange-alt"></i> Game Withdrawals
        <?php if ($pendingGameWith > 0): ?><span class="admin-tab-badge" style="background: var(--warning); color: #1a1d29;"><?= $pendingGameWith ?></span><?php endif; ?>
    </a>
    <a href="?tab=password_resets" class="admin-tab <?= $tab === 'password_resets' ? 'active' : '' ?>">
        <i class="fas fa-key"></i> Password Resets
        <?php if ($pendingResetsCount > 0): ?><span class="admin-tab-badge" style="background: var(--warning); color: #1a1d29;"><?= $pendingResetsCount ?></span><?php endif; ?>
    </a>
</div>

<?php if ($tab === 'create'): ?>
    <div class="card">
        <h3 class="admin-section-title"><i class="fas fa-plus-circle"></i> Create Game Account</h3>
        <form method="POST" action="?tab=create">
            <div class="form-group" style="margin-bottom: 20px;">
                <label><i class="fas fa-search"></i> Search user (username, name, or phone)</label>
                <input type="text" id="createAccountUserSearch" class="form-control" placeholder="Type to filter users..." autocomplete="off">
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label>User</label>
                    <select name="user_id" id="createAccountUserSelect" class="form-control" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): 
                            $searchText = strtolower(($user['username'] ?? '') . ' ' . ($user['full_name'] ?? '') . ' ' . ($user['phone'] ?? ''));
                        ?>
                            <option value="<?= $user['id'] ?>" data-search="<?= htmlspecialchars($searchText) ?>"><?= htmlspecialchars($user['username']) ?> — <?= htmlspecialchars($user['full_name']) ?><?= !empty($user['phone']) ? ' (' . htmlspecialchars($user['phone']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Game</label>
                    <select name="game" class="form-control" required>
                        <option value="">Select Game</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?= htmlspecialchars($game) ?>"><?= htmlspecialchars($game) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" class="form-control" required>
                </div>
            </div>
            <button type="submit" name="create_account" class="btn btn-block" style="margin-top: 20px;">Create Account</button>
        </form>
    </div>
<?php endif; ?>

<?php if ($tab === 'account_requests'): ?>
    <div class="card">
        <h3 class="admin-section-title"><i class="fas fa-user-plus"></i> Username / Password Requests</h3>
        <?php if (empty($pendingAccountRequests)): ?>
            <div class="admin-empty"><i class="fas fa-inbox"></i><p>No pending account requests</p></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>Date</th><th>User</th><th>Game</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingAccountRequests as $request):
                            $user = findUserById($users, $request['user_id']);
                        ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($request['date'])) ?></td>
                                <td><strong><?= htmlspecialchars(getUsername($user)) ?></strong></td>
                                <td><?= htmlspecialchars($request['game']) ?></td>
                                <td><span class="badge badge-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span></td>
                                <td>
                                    <div class="admin-actions">
                                        <form method="POST" action="?tab=account_requests" style="display: inline;">
                                            <input type="hidden" name="account_request_id" value="<?= $request['id'] ?>"><input type="hidden" name="action" value="complete">
                                            <button type="button" class="btn btn-success btn-sm" data-confirm="Mark as completed? (Create the account in Create Account tab.)">Complete</button>
                                        </form>
                                        <form method="POST" action="?tab=account_requests" style="display: inline;">
                                            <input type="hidden" name="account_request_id" value="<?= $request['id'] ?>"><input type="hidden" name="action" value="reject">
                                            <button type="button" class="btn btn-danger btn-sm" data-confirm="Reject this request?">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($tab === 'game_deposits'): ?>
    <div class="admin-toolbar">
        <div class="admin-filters">
            <a href="?tab=game_deposits&filter=all" class="admin-filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?tab=game_deposits&filter=pending" class="admin-filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?tab=game_deposits&filter=approved" class="admin-filter-btn <?= $filter === 'approved' ? 'active' : '' ?>">Approved</a>
        </div>
        <span style="color: var(--text-muted); font-size: 13px;"><?= count($gameRequestsFiltered) ?> request(s)</span>
    </div>
    <div class="card">
        <h3 class="admin-section-title"><i class="fas fa-gamepad"></i> Game Deposit Requests</h3>
        <?php if (empty($gameRequestsFiltered)): ?>
            <div class="admin-empty"><i class="fas fa-inbox"></i><p>No game deposit requests</p></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th><th>User</th><th>Game</th><th>Game Username</th><th>Amount</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gameRequestsFiltered as $request):
                            $user = findUserById($users, $request['user_id']);
                        ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($request['date'])) ?></td>
                                <td><strong><?= htmlspecialchars(getUsername($user)) ?></strong></td>
                                <td><?= htmlspecialchars($request['game']) ?></td>
                                <td><?= htmlspecialchars(getGameUsername($user, $request['game'])) ?></td>
                                <td style="color: var(--success); font-weight: 600;">$<?= number_format($request['amount'], 2) ?></td>
                                <td><span class="badge badge-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" action="?tab=game_deposits&filter=<?= urlencode($filter) ?>" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <button type="submit" name="approve_deposit" class="btn btn-success btn-sm" type="button" data-confirm="Approve? User balance will be deducted.">Approve</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($tab === 'game_withdrawals'): ?>
    <div class="card">
        <h3 class="admin-section-title"><i class="fas fa-exchange-alt"></i> Game Withdrawal Requests</h3>
        <?php if (empty($pendingGameWithdrawals)): ?>
            <div class="admin-empty"><i class="fas fa-inbox"></i><p>No pending game withdrawal requests</p></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th><th>User</th><th>Game</th><th>Game Username</th><th>Amount</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingGameWithdrawals as $request):
                            $user = findUserById($users, $request['user_id']);
                        ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($request['date'])) ?></td>
                                <td><strong><?= htmlspecialchars(getUsername($user)) ?></strong></td>
                                <td><?= htmlspecialchars($request['game']) ?></td>
                                <td><?= htmlspecialchars(getGameUsername($user, $request['game'])) ?></td>
                                <td style="color: var(--success); font-weight: 600;">$<?= number_format($request['amount'], 2) ?></td>
                                <td><span class="badge badge-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span></td>
                                <td>
                                    <div class="admin-actions">
                                        <form method="POST" action="?tab=game_withdrawals" style="display: inline;">
                                            <input type="hidden" name="game_withdrawal_id" value="<?= $request['id'] ?>"><input type="hidden" name="action" value="approve">
                                            <button type="button" class="btn btn-success btn-sm" data-confirm="Approve? Funds will be added to user balance.">Approve</button>
                                        </form>
                                        <form method="POST" action="?tab=game_withdrawals" style="display: inline;">
                                            <input type="hidden" name="game_withdrawal_id" value="<?= $request['id'] ?>"><input type="hidden" name="action" value="reject">
                                            <button type="button" class="btn btn-danger btn-sm" data-confirm="Reject this request?">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($tab === 'password_resets'): ?>
    <div class="card">
        <h3 class="admin-section-title"><i class="fas fa-key"></i> Password Reset Requests</h3>
        <?php if (empty($pendingResets)): ?>
            <div class="admin-empty"><i class="fas fa-inbox"></i><p>No pending password reset requests</p></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>Date</th><th>User</th><th>Game</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingResets as $reset):
                            $user = findUserById($users, $reset['user_id']);
                        ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($reset['date'])) ?></td>
                                <td><strong><?= htmlspecialchars(getUsername($user)) ?></strong></td>
                                <td><?= htmlspecialchars($reset['game']) ?></td>
                                <td>
                                    <form method="POST" action="?tab=password_resets" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                                        <input type="hidden" name="request_id" value="<?= $reset['id'] ?>">
                                        <input type="password" name="new_password" placeholder="New password" class="form-control" style="width: 180px;" minlength="4" required>
                                        <button type="button" name="reset_password" value="1" class="btn btn-success btn-sm" data-confirm="Reset password for <?= htmlspecialchars(addslashes($reset['game'])) ?>?">Reset</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($tab === 'create'): ?>
<script>
(function() {
    var searchInput = document.getElementById('createAccountUserSearch');
    var userSelect = document.getElementById('createAccountUserSelect');
    if (!searchInput || !userSelect) return;
    searchInput.addEventListener('input', function() {
        var q = this.value.trim().toLowerCase();
        userSelect.querySelectorAll('option').forEach(function(opt) {
            if (opt.value === '') { opt.style.display = ''; return; }
            var show = !q || (opt.getAttribute('data-search') || '').indexOf(q) !== -1;
            opt.style.display = show ? '' : 'none';
        });
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
