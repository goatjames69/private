<?php
require_once '../config.php';
requireStaffOrAdmin();

$users = readJSON(USERS_FILE);
$payments = readJSON(PAYMENTS_FILE);
$withdrawals = readJSON(WITHDRAWAL_REQUESTS_FILE);
$gameRequests = readJSON(GAME_REQUESTS_FILE);
$gameWithdrawals = readJSON(GAME_WITHDRAWALS_FILE);
$accountRequests = readJSON(GAME_ACCOUNT_REQUESTS_FILE);

$pendingPayments = count(array_filter($payments, function($p) { return $p['status'] === 'pending'; }));
$pendingWithdrawals = count(array_filter($withdrawals, function($w) { return $w['status'] === 'pending'; }));
$pendingGameRequests = count(array_filter($gameRequests, function($r) { return $r['status'] === 'pending'; }));
$pendingGameWithdrawals = count(array_filter($gameWithdrawals, function($r) { return $r['status'] === 'pending'; }));
$pendingAccountRequests = count(array_filter($accountRequests, function($r) { return $r['status'] === 'pending'; }));

$pendingCounts = [
    'payments' => $pendingPayments,
    'withdrawals' => $pendingWithdrawals,
    'game_requests' => $pendingGameRequests,
    'game_withdrawals' => $pendingGameWithdrawals,
    'account_requests' => $pendingAccountRequests
];

$totalUsers = count($users);
$totalBalance = array_sum(array_column($users, 'balance'));
$totalApprovedDeposits = array_reduce($payments, function($carry, $payment) {
    return $carry + ($payment['status'] === 'approved' ? floatval($payment['amount']) : 0);
}, 0);
$totalApprovedWithdrawals = array_reduce($withdrawals, function($carry, $withdrawal) {
    return $carry + ($withdrawal['status'] === 'approved' ? floatval($withdrawal['amount']) : 0);
}, 0);
$netProfit = $totalApprovedDeposits - $totalApprovedWithdrawals;

function findUserById(array $users, $userId) {
    foreach ($users as $user) {
        if (($user['id'] ?? null) === $userId) {
            return $user;
        }
    }
    return null;
}

$recentApprovedDeposits = array_slice(array_reverse(array_filter($payments, function($p) { return $p['status'] === 'approved'; })), 0, 5);
$recentPaymentRequests = array_slice(array_reverse($payments), 0, 5);
$recentWithdrawals = array_slice(array_reverse($withdrawals), 0, 5);
$recentGameDeposits = array_slice(array_reverse($gameRequests), 0, 5);
$recentGameWithdrawals = array_slice(array_reverse($gameWithdrawals), 0, 5);
$recentAccountRequests = array_slice(array_reverse($accountRequests), 0, 5);

$paygateTxLog = [];
if (defined('PAYGATETX_FILE') && file_exists(PAYGATETX_FILE)) {
    $paygateTxLog = json_decode(file_get_contents(PAYGATETX_FILE), true);
}
if (!is_array($paygateTxLog)) $paygateTxLog = [];
// Backfill id and status for old entries so admin can approve/reject
$paygateTxLogChanged = false;
foreach ($paygateTxLog as $i => $tx) {
    if (empty($tx['id'])) {
        $paygateTxLog[$i]['id'] = 'pg_' . $i . '_' . substr(md5(($tx['email'] ?? '') . ($tx['server_time'] ?? '')), 0, 8);
        $paygateTxLog[$i]['status'] = $tx['status'] ?? 'pending';
        $paygateTxLogChanged = true;
    }
}
if ($paygateTxLogChanged) {
    writeJSON(PAYGATETX_FILE, $paygateTxLog);
}
$recentPaygateTx = array_slice(array_reverse($paygateTxLog), 0, 10);

$adminPageTitle = 'Dashboard';
$adminCurrentPage = 'dashboard';
$adminPageSubtitle = 'Overview and quick actions';
require __DIR__ . '/_header.php';
?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Total Users</div>
        <div class="admin-stat-value"><?= $totalUsers ?></div>
        <a href="/admin/users.php" class="admin-stat-link">View users →</a>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Pending Deposits</div>
        <div class="admin-stat-value"><?= $pendingPayments ?></div>
        <?php if (canAccess('payments')): ?><a href="/admin/payments.php?tab=deposits" class="admin-stat-link">Review →</a><?php endif; ?>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Pending Withdrawals</div>
        <div class="admin-stat-value"><?= $pendingWithdrawals ?></div>
        <?php if (canAccess('payments')): ?><a href="/admin/payments.php?tab=withdrawals" class="admin-stat-link">Review →</a><?php endif; ?>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Pending Game Requests</div>
        <div class="admin-stat-value"><?= $pendingGameRequests + $pendingAccountRequests ?></div>
        <?php if (canAccess('game_accounts')): ?><a href="/admin/games.php" class="admin-stat-link">Manage →</a><?php endif; ?>
    </div>
    <?php if (isAdmin()): ?>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Total Balance (Users)</div>
        <div class="admin-stat-value">$<?= number_format($totalBalance, 2) ?></div>
    </div>
    <?php endif; ?>
</div>

<?php if (isAdmin()): ?>
<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-chart-pie"></i> Financial Overview</h3>
    <div class="admin-stats-grid">
        <div class="admin-stat-card highlight">
            <div class="admin-stat-label">Approved Deposits</div>
            <div class="admin-stat-value">$<?= number_format($totalApprovedDeposits, 2) ?></div>
        </div>
        <div class="admin-stat-card danger">
            <div class="admin-stat-label">Approved Withdrawals</div>
            <div class="admin-stat-value">$<?= number_format($totalApprovedWithdrawals, 2) ?></div>
        </div>
        <div class="admin-stat-card <?= $netProfit >= 0 ? 'highlight' : 'danger' ?>">
            <div class="admin-stat-label">Net Profit</div>
            <div class="admin-stat-value">$<?= number_format($netProfit, 2) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <a href="/admin/users.php" class="btn btn-block"><i class="fas fa-users"></i> Manage Users</a>
        <a href="/admin/activity.php" class="btn btn-block"><i class="fas fa-sync-alt"></i> Spin Logs & Activity</a>
        <?php if (canAccess('payments')): ?><a href="/admin/payments.php" class="btn btn-block"><i class="fas fa-money-bill-wave"></i> Payment Requests</a><?php endif; ?>
        <?php if (canAccess('game_accounts')): ?><a href="/admin/games.php" class="btn btn-block"><i class="fas fa-gamepad"></i> Game Accounts</a><?php endif; ?>
        <?php if (isAdmin()): ?><a href="/admin/game_catalog.php" class="btn btn-block"><i class="fas fa-th-large"></i> Game Catalog</a><?php endif; ?>
        <?php if (canAccess('payment_methods')): ?><a href="/admin/payment_methods.php" class="btn btn-block"><i class="fas fa-credit-card"></i> Payment Methods</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-history"></i> Recent Activity</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px;">
        <div class="admin-user-profile-section">
            <h4><i class="fas fa-check-circle"></i> Approved Deposits</h4>
            <?php if (empty($recentApprovedDeposits)): ?>
                <p style="color: var(--text-muted); font-size: 13px;">No approvals yet</p>
            <?php else: ?>
                <?php foreach ($recentApprovedDeposits as $payment):
                    $u = findUserById($users, $payment['user_id']); ?>
                    <p style="margin: 6px 0; font-size: 13px;">
                        <strong><?= htmlspecialchars($u['username'] ?? 'Unknown') ?></strong>
                        — $<?= number_format($payment['amount'], 2) ?> (<?= ucfirst($payment['method']) ?>)
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="admin-user-profile-section">
            <h4><i class="fas fa-file-invoice-dollar"></i> Payment Requests</h4>
            <?php if (empty($recentPaymentRequests)): ?>
                <p style="color: var(--text-muted); font-size: 13px;">No payment activity</p>
            <?php else: ?>
                <?php foreach ($recentPaymentRequests as $payment):
                    $u = findUserById($users, $payment['user_id']); ?>
                    <p style="margin: 6px 0; font-size: 13px;">
                        <strong><?= htmlspecialchars($u['username'] ?? 'Unknown') ?></strong>
                        — $<?= number_format($payment['amount'], 2) ?> <span class="badge badge-<?= $payment['status'] ?>"><?= $payment['status'] ?></span>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="admin-user-profile-section">
            <h4><i class="fas fa-money-bill-wave"></i> Withdrawals</h4>
            <?php if (empty($recentWithdrawals)): ?>
                <p style="color: var(--text-muted); font-size: 13px;">No withdrawal activity</p>
            <?php else: ?>
                <?php foreach ($recentWithdrawals as $w):
                    $u = findUserById($users, $w['user_id']); ?>
                    <p style="margin: 6px 0; font-size: 13px;">
                        <strong><?= htmlspecialchars($u['username'] ?? 'Unknown') ?></strong>
                        — $<?= number_format($w['amount'], 2) ?> <span class="badge badge-<?= $w['status'] ?>"><?= $w['status'] ?></span>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="admin-user-profile-section">
            <h4><i class="fas fa-gamepad"></i> Game Deposits</h4>
            <?php if (empty($recentGameDeposits)): ?>
                <p style="color: var(--text-muted); font-size: 13px;">No game deposit activity</p>
            <?php else: ?>
                <?php foreach ($recentGameDeposits as $r):
                    $u = findUserById($users, $r['user_id']); ?>
                    <p style="margin: 6px 0; font-size: 13px;">
                        <strong><?= htmlspecialchars($u['username'] ?? 'Unknown') ?></strong>
                        — $<?= number_format($r['amount'], 2) ?> to <?= htmlspecialchars($r['game']) ?>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="admin-user-profile-section">
            <h4><i class="fas fa-user-plus"></i> Account Requests</h4>
            <?php if (empty($recentAccountRequests)): ?>
                <p style="color: var(--text-muted); font-size: 13px;">No account requests</p>
            <?php else: ?>
                <?php foreach ($recentAccountRequests as $r):
                    $u = findUserById($users, $r['user_id']); ?>
                    <p style="margin: 6px 0; font-size: 13px;">
                        <strong><?= htmlspecialchars($u['username'] ?? 'Unknown') ?></strong>
                        — <?= htmlspecialchars($r['game']) ?> <span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="admin-user-profile-section admin-paygate-section">
            <h4><i class="fas fa-credit-card"></i> PayGate / Card Deposits</h4>
            <?php if (empty($recentPaygateTx)): ?>
                <p style="color: var(--text-muted); font-size: 13px;">No card deposit links yet</p>
            <?php else: ?>
                <?php foreach ($recentPaygateTx as $tx):
                    $pgId = $tx['id'] ?? '';
                    $pgStatus = $tx['status'] ?? 'pending';
                    $isPending = ($pgStatus === 'pending');
                ?>
                <div class="admin-paygate-row" data-paygate-id="<?= htmlspecialchars($pgId) ?>">
                    <p style="margin: 6px 0; font-size: 13px;">
                        <strong><?= htmlspecialchars($tx['email'] ?? '—') ?></strong>
                        — $<?= htmlspecialchars(number_format((float)($tx['amount'] ?? 0), 2)) ?>
                        <span style="color: var(--text-muted); font-size: 11px;"><?= htmlspecialchars($tx['server_time'] ?? '') ?></span>
                        <span class="badge badge-<?= $pgStatus === 'approved' ? 'approved' : ($pgStatus === 'rejected' ? 'rejected' : 'pending') ?>"><?= htmlspecialchars(ucfirst($pgStatus)) ?></span>
                    </p>
                    <?php if (!empty($tx['link'])): ?>
                        <p style="margin: 2px 0 4px 0;"><a href="<?= htmlspecialchars($tx['link']) ?>" target="_blank" rel="noopener" style="font-size: 11px; color: var(--accent);">Open payment link</a></p>
                    <?php endif; ?>
                    <?php if ($isPending && canAccess('payments')): ?>
                        <p style="margin: 6px 0 8px 0;">
                            <button type="button" class="btn btn-success btn-sm paygate-approve-btn" data-id="<?= htmlspecialchars($pgId) ?>" data-action="approve">Approve</button>
                            <button type="button" class="btn btn-danger btn-sm paygate-reject-btn" data-id="<?= htmlspecialchars($pgId) ?>" data-action="reject">Reject</button>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$adminExtraScript = '<script>
(function() {
    document.querySelectorAll(".paygate-approve-btn, .paygate-reject-btn").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var id = this.getAttribute("data-id");
            var action = this.getAttribute("data-action");
            if (!id || !action) return;
            var row = this.closest(".admin-paygate-row");
            if (row) row.style.opacity = "0.6";
            this.disabled = true;
            var fd = new FormData();
            fd.append("id", id);
            fd.append("action", action);
            fetch("/api/paygate_tx_status.php", { method: "POST", body: fd, credentials: "same-origin" })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && window.JamesToasts) window.JamesToasts.success("PayGate tx " + action + "d.");
                    if (data.success) window.location.reload();
                    else { if (row) row.style.opacity = "1"; btn.disabled = false; if (window.JamesToasts) window.JamesToasts.error(data.error || "Failed"); }
                })
                .catch(function() { if (row) row.style.opacity = "1"; btn.disabled = false; if (window.JamesToasts) window.JamesToasts.error("Request failed"); });
        });
    });
})();
</script>';
require __DIR__ . '/_footer.php';
?>
