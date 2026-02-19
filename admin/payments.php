<?php
require_once '../config.php';
requireStaffOrAdmin();
if (!canAccess('payments')) {
    header('Location: /admin/dashboard.php');
    exit;
}

$payments = readJSON(PAYMENTS_FILE);
$showFullUserDetails = isAdmin();
$withdrawals = readJSON(WITHDRAWAL_REQUESTS_FILE);
$gameWithdrawals = readJSON(GAME_WITHDRAWALS_FILE);
$users = readJSON(USERS_FILE);
$success = '';
$error = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'approved') $success = 'Request approved.';
    elseif ($_GET['msg'] === 'rejected') $success = 'Request rejected.';
}

function findUserById(array $users, $userId) {
    foreach ($users as $user) {
        if (($user['id'] ?? null) === $userId) {
            return $user;
        }
    }
    return null;
}

function getGameUsername($user, $game) {
    if (!$user || empty($user['game_accounts'])) return '—';
    foreach ($user['game_accounts'] as $account) {
        if (($account['game'] ?? '') === $game) {
            return $account['username'] ?? '—';
        }
    }
    return '—';
}

// Handle payment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'])) {
    $paymentId = $_POST['payment_id'] ?? '';
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        foreach ($payments as &$payment) {
            if ($payment['id'] === $paymentId && $payment['status'] === 'pending') {
                $payment['status'] = 'approved';
                foreach ($users as &$user) {
                    if ($user['id'] === $payment['user_id']) {
                        $user['balance'] += $payment['amount'];
                        $user['deposit_history'][] = ['id' => $paymentId, 'amount' => $payment['amount'], 'method' => $payment['method'], 'date' => date('Y-m-d H:i:s')];
                        // Referral: first deposit -> 50% bonus to referrer (referred user gets no bonus)
                        if (!empty($user['referred_by']) && empty($user['referral_bonus_paid'])) {
                            $user['referral_bonus_paid'] = true;
                            $bonus = (float) $payment['amount'] * 0.5;
                            $referrerId = $user['referred_by'];
                            $referredUsername = $user['username'] ?? '';
                            foreach ($users as &$refUser) {
                                if (($refUser['id'] ?? '') === $referrerId) {
                                    $refUser['balance'] = (float)($refUser['balance'] ?? 0) + $bonus;
                                    if (!isset($refUser['referral_bonus_history']) || !is_array($refUser['referral_bonus_history'])) {
                                        $refUser['referral_bonus_history'] = [];
                                    }
                                    $refUser['referral_bonus_history'][] = [
                                        'amount' => $bonus,
                                        'date' => date('Y-m-d H:i:s'),
                                        'referred_username' => $referredUsername,
                                        'source' => 'deposit'
                                    ];
                                    break;
                                }
                            }
                        }
                        break;
                    }
                }
                writeJSON(PAYMENTS_FILE, $payments);
                writeJSON(USERS_FILE, $users);
                header('Location: /admin/payments.php?tab=deposits&status=' . urlencode($_GET['status'] ?? 'all') . '&msg=approved');
                exit;
            }
        }
    } elseif ($action === 'reject') {
        foreach ($payments as &$payment) {
            if ($payment['id'] === $paymentId && $payment['status'] === 'pending') {
                $payment['status'] = 'rejected';
                writeJSON(PAYMENTS_FILE, $payments);
                header('Location: /admin/payments.php?tab=deposits&status=' . urlencode($_GET['status'] ?? 'all') . '&msg=rejected');
                exit;
            }
        }
    }
}

// Handle withdrawal approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdrawal_id'])) {
    $withdrawalId = $_POST['withdrawal_id'] ?? '';
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        foreach ($withdrawals as &$withdrawal) {
            if ($withdrawal['id'] === $withdrawalId && $withdrawal['status'] === 'pending') {
                foreach ($users as &$user) {
                    if ($user['id'] === $withdrawal['user_id']) {
                        if ($user['balance'] >= $withdrawal['amount']) {
                            $user['balance'] -= $withdrawal['amount'];
                            $withdrawal['status'] = 'approved';
                            writeJSON(WITHDRAWAL_REQUESTS_FILE, $withdrawals);
                            writeJSON(USERS_FILE, $users);
                            header('Location: /admin/payments.php?tab=withdrawals&msg=approved');
                            exit;
                        } else {
                            $error = 'Insufficient user balance';
                        }
                        break;
                    }
                }
                break;
            }
        }
    } elseif ($action === 'reject') {
        foreach ($withdrawals as &$withdrawal) {
            if ($withdrawal['id'] === $withdrawalId && $withdrawal['status'] === 'pending') {
                $withdrawal['status'] = 'rejected';
                writeJSON(WITHDRAWAL_REQUESTS_FILE, $withdrawals);
                header('Location: /admin/payments.php?tab=withdrawals&msg=rejected');
                exit;
            }
        }
    }
}

// Handle game withdrawal approval/rejection
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
                        header('Location: /admin/payments.php?tab=game_withdrawals&msg=approved');
                        exit;
                    }
                }
                $error = 'User not found for this request';
            } elseif ($action === 'reject') {
                $request['status'] = 'rejected';
                writeJSON(GAME_WITHDRAWALS_FILE, $gameWithdrawals);
                header('Location: /admin/payments.php?tab=game_withdrawals&msg=rejected');
                exit;
            }
            break;
        }
    }
    unset($request);
}

$pendingDeposits = count(array_filter($payments, function($p) { return $p['status'] === 'pending'; }));
$pendingWithdrawalsCount = count(array_filter($withdrawals, function($w) { return $w['status'] === 'pending'; }));
$pendingGameWithdrawalsCount = count(array_filter($gameWithdrawals, function($w) { return $w['status'] === 'pending'; }));

$pendingCounts = [
    'payments' => $pendingDeposits,
    'withdrawals' => $pendingWithdrawalsCount,
    'game_requests' => 0,
    'account_requests' => 0
];

$tab = $_GET['tab'] ?? 'deposits';
$statusFilter = $_GET['status'] ?? 'all';

// Filter payments by status
$paymentsFiltered = $payments;
if ($statusFilter !== 'all') {
    $paymentsFiltered = array_filter($payments, function($p) use ($statusFilter) { return $p['status'] === $statusFilter; });
}
$paymentsFiltered = array_reverse(array_values($paymentsFiltered));

$withdrawalsAll = array_reverse($withdrawals);
$gameWithdrawalsAll = array_reverse($gameWithdrawals);

$adminPageTitle = 'Payments';
$adminCurrentPage = 'payments';
$adminPageSubtitle = 'Deposits, withdrawals & game withdrawals';
require __DIR__ . '/_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-tabs">
    <a href="?tab=deposits" class="admin-tab <?= $tab === 'deposits' ? 'active' : '' ?>">
        <i class="fas fa-money-bill-wave"></i> Deposits
        <span class="admin-tab-badge"><?= count($payments) ?></span>
        <?php if ($pendingDeposits > 0): ?><span class="admin-tab-badge" style="background: var(--warning); color: #1a1d29;"><?= $pendingDeposits ?> pending</span><?php endif; ?>
    </a>
    <a href="?tab=withdrawals" class="admin-tab <?= $tab === 'withdrawals' ? 'active' : '' ?>">
        <i class="fas fa-hand-holding-usd"></i> Withdrawals
        <span class="admin-tab-badge"><?= count($withdrawals) ?></span>
        <?php if ($pendingWithdrawalsCount > 0): ?><span class="admin-tab-badge" style="background: var(--warning); color: #1a1d29;"><?= $pendingWithdrawalsCount ?> pending</span><?php endif; ?>
    </a>
    <a href="?tab=game_withdrawals" class="admin-tab <?= $tab === 'game_withdrawals' ? 'active' : '' ?>">
        <i class="fas fa-exchange-alt"></i> Game Withdrawals
        <span class="admin-tab-badge"><?= count($gameWithdrawals) ?></span>
        <?php if ($pendingGameWithdrawalsCount > 0): ?><span class="admin-tab-badge" style="background: var(--warning); color: #1a1d29;"><?= $pendingGameWithdrawalsCount ?> pending</span><?php endif; ?>
    </a>
</div>

<?php if ($tab === 'deposits'): ?>
    <div class="admin-toolbar">
        <div class="admin-filters">
            <a href="?tab=deposits&status=all" class="admin-filter-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?tab=deposits&status=pending" class="admin-filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?tab=deposits&status=approved" class="admin-filter-btn <?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved</a>
            <a href="?tab=deposits&status=rejected" class="admin-filter-btn <?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
        </div>
        <span style="color: var(--text-muted); font-size: 13px;"><?= count($paymentsFiltered) ?> deposit(s)</span>
    </div>
    <div class="card">
        <h3 class="admin-section-title"><i class="fas fa-money-bill-wave"></i> Deposit Requests</h3>
        <?php if (empty($paymentsFiltered)): ?>
            <div class="admin-empty"><i class="fas fa-inbox"></i><p>No deposits found</p></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <?php if ($showFullUserDetails): ?><th>Payment Info</th><?php endif; ?>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentsFiltered as $payment):
                            $user = findUserById($users, $payment['user_id']);
                            $username = $user['username'] ?? 'Unknown';
                        ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($payment['date'])) ?></td>
                                <td><strong><?= htmlspecialchars($username) ?></strong></td>
                                <td style="color: var(--success); font-weight: 600;">$<?= number_format($payment['amount'], 2) ?></td>
                                <td><?= ucfirst($payment['method'] ?? '-') ?></td>
                                <?php if ($showFullUserDetails): ?><td style="max-width: 200px; word-break: break-word;"><?= htmlspecialchars(mb_substr($payment['payment_info'] ?? 'N/A', 0, 60)) ?><?= mb_strlen($payment['payment_info'] ?? '') > 60 ? '…' : '' ?></td><?php endif; ?>
                                <td><span class="badge badge-<?= $payment['status'] ?>"><?= ucfirst($payment['status']) ?></span></td>
                                <td>
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <div class="admin-actions">
                                            <form method="POST" action="?tab=deposits&status=<?= urlencode($statusFilter) ?>" style="display: inline;">
                                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>"><input type="hidden" name="action" value="approve">
                                                <button type="button" class="btn btn-success btn-sm" data-confirm="Approve this payment?">Approve</button>
                                            </form>
                                            <form method="POST" action="?tab=deposits&status=<?= urlencode($statusFilter) ?>" style="display: inline;">
                                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>"><input type="hidden" name="action" value="reject">
                                                <button type="button" class="btn btn-danger btn-sm" data-confirm="Reject this payment?">Reject</button>
                                            </form>
                                        </div>
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

<?php if ($tab === 'withdrawals'): ?>
    <div class="admin-toolbar">
        <span style="color: var(--text-muted); font-size: 13px;"><?= count($withdrawalsAll) ?> withdrawal(s) — <?= $pendingWithdrawalsCount ?> pending</span>
    </div>
    <div class="card">
        <h3 class="admin-section-title"><i class="fas fa-hand-holding-usd"></i> Withdrawal Requests</h3>
        <?php if (empty($withdrawalsAll)): ?>
            <div class="admin-empty"><i class="fas fa-inbox"></i><p>No withdrawal requests</p></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <?php if ($showFullUserDetails): ?><th>Account Info</th><th>QR Code</th><?php endif; ?>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawalsAll as $withdrawal):
                            $user = findUserById($users, $withdrawal['user_id']);
                            $username = $user['username'] ?? 'Unknown';
                        ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($withdrawal['date'])) ?></td>
                                <td><strong><?= htmlspecialchars($username) ?></strong></td>
                                <td style="color: var(--warning); font-weight: 600;">$<?= number_format($withdrawal['amount'], 2) ?></td>
                                <td><?= ucfirst($withdrawal['method'] ?? '-') ?></td>
                                <?php if ($showFullUserDetails): ?>
                                <td style="max-width: 180px; word-break: break-word;"><?= htmlspecialchars(mb_substr($withdrawal['account_info'] ?? 'N/A', 0, 50)) ?><?= mb_strlen($withdrawal['account_info'] ?? '') > 50 ? '…' : '' ?></td>
                                <td>
                                    <?php if (!empty($withdrawal['qr_code'])): ?>
                                        <a href="/<?= htmlspecialchars($withdrawal['qr_code']) ?>" target="_blank" style="display: inline-block;">
                                            <img src="/<?= htmlspecialchars($withdrawal['qr_code']) ?>" alt="QR" style="max-width: 56px; max-height: 56px; border-radius: 8px; border: 1px solid var(--border-color);">
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td><span class="badge badge-<?= $withdrawal['status'] ?>"><?= ucfirst($withdrawal['status']) ?></span></td>
                                <td>
                                    <?php if ($withdrawal['status'] === 'pending'): ?>
                                        <div class="admin-actions">
                                            <form method="POST" action="?tab=withdrawals" style="display: inline;">
                                                <input type="hidden" name="withdrawal_id" value="<?= $withdrawal['id'] ?>"><input type="hidden" name="action" value="approve">
                                                <button type="button" class="btn btn-success btn-sm" data-confirm="Approve withdrawal? Balance will be deducted.">Approve</button>
                                            </form>
                                            <form method="POST" action="?tab=withdrawals" style="display: inline;">
                                                <input type="hidden" name="withdrawal_id" value="<?= $withdrawal['id'] ?>"><input type="hidden" name="action" value="reject">
                                                <button type="button" class="btn btn-danger btn-sm" data-confirm="Reject this withdrawal?">Reject</button>
                                            </form>
                                        </div>
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
    <div class="admin-toolbar">
        <span style="color: var(--text-muted); font-size: 13px;"><?= count($gameWithdrawalsAll) ?> game withdrawal(s) — <?= $pendingGameWithdrawalsCount ?> pending</span>
    </div>
    <div class="card">
        <h3 class="admin-section-title"><i class="fas fa-exchange-alt"></i> Game Withdrawal Requests</h3>
        <?php if (empty($gameWithdrawalsAll)): ?>
            <div class="admin-empty"><i class="fas fa-inbox"></i><p>No game withdrawal requests</p></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Game</th>
                            <th>Game Username</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gameWithdrawalsAll as $request):
                            $user = findUserById($users, $request['user_id']);
                            $username = $user['username'] ?? 'Unknown';
                        ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($request['date'])) ?></td>
                                <td><strong><?= htmlspecialchars($username) ?></strong></td>
                                <td><?= htmlspecialchars($request['game']) ?></td>
                                <td><?= htmlspecialchars(getGameUsername($user, $request['game'])) ?></td>
                                <td style="color: var(--success); font-weight: 600;">$<?= number_format($request['amount'], 2) ?></td>
                                <td><span class="badge badge-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
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

<?php require __DIR__ . '/_footer.php'; ?>
