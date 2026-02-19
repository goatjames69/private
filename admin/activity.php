<?php
require_once '../config.php';
requireStaffOrAdmin();

$spinLogs = [];
if (file_exists(SPIN_LOGS_FILE)) {
    $raw = file_get_contents(SPIN_LOGS_FILE);
    $spinLogs = json_decode($raw, true);
    if (!is_array($spinLogs)) $spinLogs = [];
}

$searchUsername = isset($_GET['username']) ? trim($_GET['username']) : '';
if ($searchUsername !== '') {
    $q = mb_strtolower($searchUsername);
    $spinLogs = array_values(array_filter($spinLogs, function ($log) use ($q) {
        $u = mb_strtolower($log['username'] ?? '');
        $f = mb_strtolower($log['full_name'] ?? '');
        return (strpos($u, $q) !== false || strpos($f, $q) !== false);
    }));
}

$adminPageTitle = 'Activity & Spin Logs';
$adminCurrentPage = 'activity';
$adminPageSubtitle = 'Free spins, paid spins, and user activity';
if (!isset($pendingCounts)) $pendingCounts = [];
require __DIR__ . '/_header.php';
?>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-sync-alt"></i> Spin Wheel Logs</h3>
    <p style="color: var(--text-muted); margin-bottom: 16px; font-size: 14px;">All free spins and paid ($1) spins by users. Most recent first.</p>

    <form method="GET" action="" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <label for="spin-search-username" class="sr-only">Search by username</label>
        <input type="text" id="spin-search-username" name="username" class="form-control" style="max-width: 260px;"
            value="<?= htmlspecialchars($searchUsername) ?>"
            placeholder="Search by username or name...">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        <?php if ($searchUsername !== ''): ?>
        <a href="?" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($spinLogs)): ?>
        <div class="empty-state" style="padding: 40px 20px;">
            <p style="color: var(--text-muted);">No spin logs yet.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap" style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Reward</th>
                        <th>Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($spinLogs, 0, 200) as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($log['date'] ?? ''))) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($log['username'] ?? '—') ?></strong>
                                <?php if (!empty($log['full_name'])): ?>
                                    <br><span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($log['full_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['paid'])): ?>
                                    <span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;">Paid $1</span>
                                <?php else: ?>
                                    <span class="badge badge-approved">Free</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($log['reward_label'] ?? '—') ?></strong></td>
                            <td>$<?= number_format((float)($log['new_balance'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
