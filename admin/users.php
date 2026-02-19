<?php
require_once '../config.php';
requireStaffOrAdmin();

$allUsers = readJSON(USERS_FILE);
$showFullUserDetails = isAdmin();
$canEditGameCredentials = isAdmin() || canAccess('edit_game_credentials');
$payments = readJSON(PAYMENTS_FILE);
$withdrawals = readJSON(WITHDRAWAL_REQUESTS_FILE);
$gameRequests = readJSON(GAME_REQUESTS_FILE);
$gameWithdrawals = readJSON(GAME_WITHDRAWALS_FILE);
$minesGames = file_exists(MINES_GAMES_FILE) ? json_decode(file_get_contents(MINES_GAMES_FILE), true) : [];
if (!is_array($minesGames)) $minesGames = [];

$pendingCounts = [
    'payments' => count(array_filter($payments, function($p) { return $p['status'] === 'pending'; })),
    'withdrawals' => count(array_filter($withdrawals, function($w) { return $w['status'] === 'pending'; })),
    'game_requests' => count(array_filter($gameRequests, function($r) { return $r['status'] === 'pending'; })),
    'account_requests' => count(array_filter(readJSON(GAME_ACCOUNT_REQUESTS_FILE), function($r) { return $r['status'] === 'pending'; }))
];

$search = trim($_GET['search'] ?? '');
$success = '';
$error = '';
$users = $allUsers;

// Balance update, game account update, password reset are handled via AJAX (api/admin_update_user.php) for real-time UX

// Search: admin = username, full name, phone; staff = username only
if ($search !== '') {
    $q = strtolower($search);
    $users = array_filter($allUsers, function($user) use ($q, $showFullUserDetails) {
        if ($showFullUserDetails) {
            return stripos($user['username'] ?? '', $q) !== false
                || stripos($user['full_name'] ?? '', $q) !== false
                || stripos($user['phone'] ?? '', $q) !== false;
        }
        return stripos($user['username'] ?? '', $q) !== false;
    });
    $users = array_values($users);
}

$adminPageTitle = 'Users';
$adminCurrentPage = 'users';
$adminPageSubtitle = count($users) . ' user' . (count($users) !== 1 ? 's' : '') . ($search ? ' matching "' . htmlspecialchars($search) . '"' : '');
require __DIR__ . '/_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-toolbar">
    <form method="GET" action="" class="admin-search-box">
        <i class="fas fa-search"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
               placeholder="<?= $showFullUserDetails ? 'Search by username, full name, or phone...' : 'Search by username...' ?>"
               autofocus>
    </form>
    <div class="admin-filters">
        <?php if ($search): ?>
            <a href="/admin/users.php" class="admin-filter-btn">Clear search</a>
        <?php endif; ?>
        <span style="color: var(--text-muted); font-size: 13px;">
            <?= count($users) ?> of <?= count($allUsers) ?> users
        </span>
    </div>
</div>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-users"></i> All Users</h3>
    <?php if (empty($users)): ?>
        <div class="admin-empty">
            <i class="fas fa-user-slash"></i>
            <p><?= $search ? 'No users match your search.' : 'No users yet.' ?></p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <?php if ($showFullUserDetails): ?>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Balance</th>
                        <?php endif; ?>
                        <th>Game Accounts</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?= htmlspecialchars($user['id']) ?>">
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <?php if ($showFullUserDetails): ?>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['phone']) ?></td>
                            <td class="admin-user-balance-cell" data-user-id="<?= htmlspecialchars($user['id']) ?>" style="color: var(--success); font-weight: 600;">$<?= number_format($user['balance'], 2) ?></td>
                            <?php endif; ?>
                            <td><?= count($user['game_accounts'] ?? []) ?></td>
                            <td>
                                <div class="admin-actions">
                                    <button type="button" class="btn btn-sm" onclick="openUserModal('<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-eye"></i> View & History
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Game Account Modal -->
<div id="editGameAccountModal" class="admin-modal-overlay" style="display: none;" onclick="if(event.target===this) closeEditGameAccountModal()">
    <div class="admin-modal" onclick="event.stopPropagation()" style="max-width: 480px;">
        <div class="admin-modal-header">
            <h2><i class="fas fa-edit"></i> Edit Game Account</h2>
            <button type="button" class="admin-modal-close" onclick="closeEditGameAccountModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="admin-modal-body">
            <form id="editGameAccountForm" class="admin-ajax-form" data-action="update_game_account">
                <input type="hidden" name="user_id" id="editGameUserId" value="">
                <input type="hidden" name="game" id="editGameName" value="">
                <div class="form-group">
                    <label>Game</label>
                    <input type="text" id="editGameNameDisplay" class="form-control" readonly disabled style="background: var(--bg-secondary); color: var(--text-muted);">
                </div>
                <div class="form-group">
                    <label>Game Username *</label>
                    <input type="text" name="game_username" id="editGameUsername" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Game Password *</label>
                    <input type="text" name="game_password" id="editGamePassword" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-block"><span class="btn-text">Save Changes</span></button>
            </form>
        </div>
    </div>
</div>

<!-- User Profile & Full History Modal -->
<div id="userModal" class="admin-modal-overlay" style="display: none;" onclick="if(event.target===this) closeUserModal()">
    <div class="admin-modal" onclick="event.stopPropagation()">
        <div class="admin-modal-header">
            <h2><i class="fas fa-user"></i> <span id="modalUserName">User</span></h2>
            <button type="button" class="admin-modal-close" onclick="closeUserModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="admin-modal-body" id="userModalBody">
            <!-- Filled by JS -->
        </div>
    </div>
</div>

<?php
$usersJson = json_encode($users);
$paymentsJson = json_encode($payments);
$withdrawalsJson = json_encode($withdrawals);
$gameRequestsJson = json_encode($gameRequests);
$gameWithdrawalsJson = json_encode($gameWithdrawals);
$minesGamesJson = json_encode($minesGames);
?>
<script>
const allUsersData = <?= $usersJson ?>;
const allPayments = <?= $paymentsJson ?>;
const allWithdrawals = <?= $withdrawalsJson ?>;
const allGameRequests = <?= $gameRequestsJson ?>;
const allGameWithdrawals = <?= $gameWithdrawalsJson ?>;
const allMinesGames = <?= $minesGamesJson ?>;
const showFullUserDetails = <?= $showFullUserDetails ? 'true' : 'false' ?>;
const canEditGameCredentials = <?= $canEditGameCredentials ? 'true' : 'false' ?>;

function openUserModal(userId) {
    const user = allUsersData.find(u => u.id === userId);
    if (!user) return;

    document.getElementById('modalUserName').textContent = showFullUserDetails ? (user.username + ' — ' + user.full_name) : user.username;
    const paymentsForUser = allPayments.filter(p => p.user_id === userId);
    const withdrawalsForUser = allWithdrawals.filter(w => w.user_id === userId);
    const gameDepositsForUser = allGameRequests.filter(r => r.user_id === userId);
    const gameWithdrawalsForUser = allGameWithdrawals.filter(r => r.user_id === userId);

    let html = '';

    if (showFullUserDetails) {
        html += '<div class="admin-user-profile-section"><h4><i class="fas fa-info-circle"></i> Account</h4>';
        html += '<table class="admin-table"><tbody>';
        html += '<tr><td><strong>Username</strong></td><td>' + escapeHtml(user.username) + '</td></tr>';
        html += '<tr><td><strong>Full Name</strong></td><td>' + escapeHtml(user.full_name) + '</td></tr>';
        html += '<tr><td><strong>Phone</strong></td><td>' + escapeHtml(user.phone) + '</td></tr>';
        html += '<tr><td><strong>Balance</strong></td><td id="modalUserBalance" style="color: var(--success); font-weight: 600;">$' + parseFloat(user.balance).toFixed(2) + '</td></tr></tbody></table></div>';
        html += '<div class="admin-user-profile-section"><h4><i class="fas fa-wallet"></i> Update Balance</h4>';
        html += '<form class="admin-ajax-form" data-action="update_balance"><input type="hidden" name="user_id" value="' + escapeHtml(user.id) + '"><div class="form-group"><label>New Balance ($)</label><input type="number" name="balance" class="form-control" step="0.01" value="' + user.balance + '" required></div><button type="submit" class="btn btn-block"><span class="btn-text">Update Balance</span></button></form></div>';
        html += '<div class="admin-user-profile-section"><h4><i class="fas fa-key"></i> Reset Password</h4>';
        html += '<form class="admin-ajax-form" data-action="reset_password"><input type="hidden" name="user_id" value="' + escapeHtml(user.id) + '"><div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" minlength="4" required></div><button type="submit" class="btn btn-warning btn-block"><span class="btn-text">Reset Password</span></button></form></div>';
        html += '<div class="admin-user-profile-section"><h4><i class="fas fa-money-bill-wave"></i> Deposit History (' + paymentsForUser.length + ')</h4>';
        if (paymentsForUser.length === 0) html += '<p style="color: var(--text-muted); font-size: 13px;">No deposits</p>';
        else {
            html += '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Info</th></tr></thead><tbody>';
            paymentsForUser.sort((a,b) => new Date(b.date) - new Date(a.date));
            paymentsForUser.slice(0, 50).forEach(p => { html += '<tr><td>' + formatDate(p.date) + '</td><td>$' + parseFloat(p.amount).toFixed(2) + '</td><td>' + (p.method || '-') + '</td><td><span class="badge badge-' + (p.status || 'pending') + '">' + (p.status || 'pending') + '</span></td><td style="max-width:180px;word-break:break-all;">' + escapeHtml((p.payment_info || '').substring(0, 80)) + '</td></tr>'; });
            if (paymentsForUser.length > 50) html += '<tr><td colspan="5" style="color: var(--text-muted);">... and ' + (paymentsForUser.length - 50) + ' more</td></tr>';
            html += '</tbody></table></div>';
        }
        html += '</div>';
        html += '<div class="admin-user-profile-section"><h4><i class="fas fa-hand-holding-usd"></i> Withdrawal History (' + withdrawalsForUser.length + ')</h4>';
        if (withdrawalsForUser.length === 0) html += '<p style="color: var(--text-muted); font-size: 13px;">No withdrawals</p>';
        else {
            html += '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Account Info</th></tr></thead><tbody>';
            withdrawalsForUser.sort((a,b) => new Date(b.date) - new Date(a.date));
            withdrawalsForUser.slice(0, 50).forEach(w => { html += '<tr><td>' + formatDate(w.date) + '</td><td>$' + parseFloat(w.amount).toFixed(2) + '</td><td>' + (w.method || '-') + '</td><td><span class="badge badge-' + (w.status || 'pending') + '">' + (w.status || 'pending') + '</span></td><td style="max-width:180px;word-break:break-all;">' + escapeHtml((w.account_info || '').substring(0, 80)) + '</td></tr>'; });
            if (withdrawalsForUser.length > 50) html += '<tr><td colspan="5" style="color: var(--text-muted);">... and ' + (withdrawalsForUser.length - 50) + ' more</td></tr>';
            html += '</tbody></table></div>';
        }
        html += '</div>';
    }

    // Game deposit requests
    html += '<div class="admin-user-profile-section">';
    html += '<h4><i class="fas fa-gamepad"></i> Game Deposit Requests (' + gameDepositsForUser.length + ')</h4>';
    if (gameDepositsForUser.length === 0) {
        html += '<p style="color: var(--text-muted); font-size: 13px;">None</p>';
    } else {
        html += '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Game</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
        gameDepositsForUser.sort((a,b) => new Date(b.date) - new Date(a.date));
        gameDepositsForUser.slice(0, 30).forEach(r => {
            html += '<tr><td>' + formatDate(r.date) + '</td><td>' + escapeHtml(r.game) + '</td><td>$' + parseFloat(r.amount).toFixed(2) + '</td><td><span class="badge badge-' + (r.status || 'pending') + '">' + (r.status || 'pending') + '</span></td></tr>';
        });
        if (gameDepositsForUser.length > 30) html += '<tr><td colspan="4" style="color: var(--text-muted);">... and ' + (gameDepositsForUser.length - 30) + ' more</td></tr>';
        html += '</tbody></table></div>';
    }
    html += '</div>';

    // Game withdrawal requests
    html += '<div class="admin-user-profile-section">';
    html += '<h4><i class="fas fa-exchange-alt"></i> Game Withdrawal Requests (' + gameWithdrawalsForUser.length + ')</h4>';
    if (gameWithdrawalsForUser.length === 0) {
        html += '<p style="color: var(--text-muted); font-size: 13px;">None</p>';
    } else {
        html += '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Game</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
        gameWithdrawalsForUser.sort((a,b) => new Date(b.date) - new Date(a.date));
        gameWithdrawalsForUser.slice(0, 30).forEach(r => {
            html += '<tr><td>' + formatDate(r.date) + '</td><td>' + escapeHtml(r.game) + '</td><td>$' + parseFloat(r.amount).toFixed(2) + '</td><td><span class="badge badge-' + (r.status || 'pending') + '">' + (r.status || 'pending') + '</span></td></tr>';
        });
        if (gameWithdrawalsForUser.length > 30) html += '<tr><td colspan="4" style="color: var(--text-muted);">... and ' + (gameWithdrawalsForUser.length - 30) + ' more</td></tr>';
        html += '</tbody></table></div>';
    }
    html += '</div>';

    // Mines game history
    const minesGamesForUser = allMinesGames.filter(g => g.user_id === userId);
    html += '<div class="admin-user-profile-section">';
    html += '<h4><i class="fas fa-bomb"></i> Mines Game History (' + minesGamesForUser.length + ')</h4>';
    if (minesGamesForUser.length === 0) {
        html += '<p style="color: var(--text-muted); font-size: 13px;">No Mines games played</p>';
    } else {
        html += '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Game ID</th><th>Bet</th><th>Mines</th><th>Result</th><th>Profit</th><th>Tiles / Mult.</th></tr></thead><tbody>';
        minesGamesForUser.slice(0, 50).forEach(g => {
            const resultClass = (g.result === 'win') ? 'approved' : 'rejected';
            const profitStr = (g.profit >= 0 ? '+' : '') + '$' + parseFloat(g.profit).toFixed(2);
            const extra = g.multiplier != null ? g.multiplier.toFixed(2) + '×' : (g.tiles_revealed || '-');
            html += '<tr><td>' + formatDate(g.created_at) + '</td><td style="font-size:11px;word-break:break-all;">' + escapeHtml((g.game_id || '').substring(0, 16)) + '…</td><td>$' + parseFloat(g.bet).toFixed(2) + '</td><td>' + (g.mines || '-') + '</td><td><span class="badge badge-' + resultClass + '">' + (g.result || '-') + '</span></td><td style="color: var(--' + resultClass + ');">' + profitStr + '</td><td>' + extra + '</td></tr>';
        });
        if (minesGamesForUser.length > 50) html += '<tr><td colspan="7" style="color: var(--text-muted);">... and ' + (minesGamesForUser.length - 50) + ' more</td></tr>';
        html += '</tbody></table></div>';
    }
    html += '</div>';

    // Game accounts
    html += '<div class="admin-user-profile-section">';
    html += '<h4><i class="fas fa-user-cog"></i> Game Accounts (' + (user.game_accounts || []).length + ')</h4>';
    if (!user.game_accounts || user.game_accounts.length === 0) {
        html += '<p style="color: var(--text-muted); font-size: 13px;">No game accounts</p>';
    } else {
        html += '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Game</th><th>Username</th><th>Password</th>' + (canEditGameCredentials ? '<th>Actions</th>' : '') + '</tr></thead><tbody>';
        user.game_accounts.forEach(function(acc, idx) {
            html += '<tr><td>' + escapeHtml(acc.game) + '</td><td>' + escapeHtml(acc.username) + '</td><td>' + escapeHtml(acc.password) + '</td>';
            if (canEditGameCredentials) {
                html += '<td><button type="button" class="btn btn-sm edit-game-acc-btn" data-user-id="' + escapeAttr(user.id) + '" data-game="' + escapeAttr(acc.game) + '" data-username="' + escapeAttr(acc.username) + '" data-password="' + escapeAttr(acc.password) + '">Edit</button></td>';
            }
            html += '</tr>';
        });
        html += '</tbody></table></div>';
    }
    html += '</div>';

    document.getElementById('userModalBody').innerHTML = html;
    document.getElementById('userModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    if (canEditGameCredentials) {
        document.querySelectorAll('#userModalBody .edit-game-acc-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openEditGameAccountModal(
                    btn.getAttribute('data-user-id'),
                    btn.getAttribute('data-game'),
                    btn.getAttribute('data-username'),
                    btn.getAttribute('data-password')
                );
            });
        });
    }
}

function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
    document.body.style.overflow = '';
}

function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escapeAttr(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function openEditGameAccountModal(userId, game, username, password) {
    document.getElementById('editGameUserId').value = userId;
    document.getElementById('editGameName').value = game;
    document.getElementById('editGameNameDisplay').value = game;
    document.getElementById('editGameUsername').value = username || '';
    document.getElementById('editGamePassword').value = password || '';
    document.getElementById('editGameAccountModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEditGameAccountModal() {
    document.getElementById('editGameAccountModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUserModal();
        closeEditGameAccountModal();
    }
});

// AJAX form submission for real-time UX (no page refresh)
function adminSubmitForm(form, action, done) {
    var btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.setAttribute('data-loading', 'true'); btn.disabled = true; }
    var fd = new FormData(form);
    fd.set('action', action);
    fetch('/api/admin_update_user.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) { btn.removeAttribute('data-loading'); btn.disabled = false; }
            if (data.success) {
                if (window.JamesToasts) window.JamesToasts.success(data.message || 'Saved');
                if (done) done(data);
            } else {
                if (window.JamesToasts) window.JamesToasts.error(data.error || 'Failed');
            }
        })
        .catch(function() {
            if (btn) { btn.removeAttribute('data-loading'); btn.disabled = false; }
            if (window.JamesToasts) window.JamesToasts.error('Network error');
        });
}

document.getElementById('userModalBody')?.addEventListener('submit', function(e) {
    var form = e.target;
    if (!form.classList.contains('admin-ajax-form')) return;
    e.preventDefault();
    var action = form.getAttribute('data-action');
    if (!action) return;
    adminSubmitForm(form, action, function(data) {
        var uid = form.querySelector('input[name="user_id"]')?.value;
        if (action === 'update_balance' && data.balance !== undefined && uid) {
            var u = allUsersData.find(function(x) { return x.id === uid; });
            if (u) u.balance = data.balance;
            var balanceCell = document.querySelector('td.admin-user-balance-cell[data-user-id="' + uid + '"]');
            if (balanceCell) balanceCell.innerHTML = '$' + parseFloat(data.balance).toFixed(2);
            var modalBalance = document.getElementById('modalUserBalance');
            if (modalBalance) modalBalance.textContent = '$' + parseFloat(data.balance).toFixed(2);
        }
    });
});

document.getElementById('editGameAccountForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    adminSubmitForm(this, 'update_game_account', function() {
        closeEditGameAccountModal();
        if (window.JamesToasts) window.JamesToasts.info('Reopen user to see updated game account.');
    });
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>
