<?php
require_once '../config.php';
requireAdmin();

$staffList = readJSON(STAFF_FILE);
$success = '';
$error = '';

$defaultPermissions = [
    'payments' => true,
    'withdrawals' => true,
    'game_accounts' => true,
    'payment_methods' => true
];

// Create staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $permissions = [
        'payments' => isset($_POST['perm_payments']),
        'withdrawals' => isset($_POST['perm_withdrawals']),
        'game_accounts' => isset($_POST['perm_game_accounts']),
        'payment_methods' => isset($_POST['perm_payment_methods']),
        'edit_game_credentials' => isset($_POST['perm_edit_game_credentials'])
    ];
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters';
    } else {
        foreach ($staffList as $s) {
            if (($s['username'] ?? '') === $username) {
                $error = 'Username already exists';
                break;
            }
        }
        if (empty($error)) {
            $staffList[] = [
                'id' => generateId(),
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'full_name' => $full_name,
                'permissions' => $permissions,
                'created_at' => date('Y-m-d H:i:s')
            ];
            writeJSON(STAFF_FILE, $staffList);
            $success = 'Staff account created successfully';
        }
    }
}

// Update staff permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $staffId = $_POST['staff_id'] ?? '';
    $permissions = [
        'payments' => isset($_POST['perm_payments']),
        'withdrawals' => isset($_POST['perm_withdrawals']),
        'game_accounts' => isset($_POST['perm_game_accounts']),
        'payment_methods' => isset($_POST['perm_payment_methods']),
        'edit_game_credentials' => isset($_POST['perm_edit_game_credentials'])
    ];
    foreach ($staffList as &$s) {
        if (($s['id'] ?? '') === $staffId) {
            $s['permissions'] = $permissions;
            $s['full_name'] = trim($_POST['full_name'] ?? $s['full_name']);
            writeJSON(STAFF_FILE, $staffList);
            $success = 'Staff permissions updated';
            break;
        }
    }
    unset($s);
}

// Reset staff password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_staff_password'])) {
    $staffId = $_POST['staff_id'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    if (strlen($newPassword) < 4) {
        $error = 'Password must be at least 4 characters';
    } else {
        foreach ($staffList as &$s) {
            if (($s['id'] ?? '') === $staffId) {
                $s['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                writeJSON(STAFF_FILE, $staffList);
                $success = 'Staff password reset successfully';
                break;
            }
        }
        unset($s);
    }
}

$pendingCounts = ['payments' => 0, 'withdrawals' => 0, 'game_requests' => 0, 'account_requests' => 0];
$adminPageTitle = 'Staff Accounts';
$adminCurrentPage = 'staff';
$adminPageSubtitle = 'Create and manage staff permissions';
require __DIR__ . '/_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-user-plus"></i> Create Staff Account</h3>
    <form method="POST" action="">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" class="form-control" required minlength="4">
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group" style="margin-top: 20px;">
            <label style="margin-bottom: 10px; display: block;">Permissions</label>
            <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="perm_payments" value="1" <?= ($_POST['perm_payments'] ?? true) ? 'checked' : '' ?>>
                    <span>Payments</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="perm_withdrawals" value="1" <?= ($_POST['perm_withdrawals'] ?? true) ? 'checked' : '' ?>>
                    <span>Withdrawals</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="perm_game_accounts" value="1" <?= ($_POST['perm_game_accounts'] ?? true) ? 'checked' : '' ?>>
                    <span>Game Accounts</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="perm_payment_methods" value="1" <?= ($_POST['perm_payment_methods'] ?? true) ? 'checked' : '' ?>>
                    <span>Payment Methods</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="perm_edit_game_credentials" value="1" <?= ($_POST['perm_edit_game_credentials'] ?? false) ? 'checked' : '' ?>>
                    <span>Edit Game Username/Password</span>
                </label>
            </div>
            <small style="color: var(--text-muted); display: block; margin-top: 8px;">Staff can only see username and game info — no full customer details. "Edit Game Username/Password" allows editing clients' game account credentials from the Users page.</small>
        </div>
        <button type="submit" name="create_staff" class="btn btn-block" style="margin-top: 20px;">Create Staff Account</button>
    </form>
</div>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-user-tie"></i> Staff List (<?= count($staffList) ?>)</h3>
    <?php if (empty($staffList)): ?>
        <div class="admin-empty"><i class="fas fa-users-slash"></i><p>No staff accounts yet. Create one above.</p></div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Permissions</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffList as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['username']) ?></strong></td>
                            <td><?= htmlspecialchars($s['full_name'] ?? '—') ?></td>
                            <td>
                                <?php
                                $perms = $s['permissions'] ?? [];
                                $labels = [];
                                if (!empty($perms['payments'])) $labels[] = 'Payments';
                                if (!empty($perms['withdrawals'])) $labels[] = 'Withdrawals';
                                if (!empty($perms['game_accounts'])) $labels[] = 'Game Accounts';
                                if (!empty($perms['payment_methods'])) $labels[] = 'Payment Methods';
                                if (!empty($perms['edit_game_credentials'])) $labels[] = 'Edit Game Credentials';
                                echo implode(', ', $labels) ?: '—';
                                ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($s['created_at'] ?? '')) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm" onclick="openEditModal('<?= htmlspecialchars($s['id'], ENT_QUOTES) ?>')">Edit Permissions</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Staff Modal -->
<div id="editStaffModal" class="admin-modal-overlay" style="display: none;" onclick="if(event.target===this) closeEditModal()">
    <div class="admin-modal" onclick="event.stopPropagation()">
        <div class="admin-modal-header">
            <h2><i class="fas fa-user-edit"></i> Edit Staff Permissions</h2>
            <button type="button" class="admin-modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="admin-modal-body" id="editStaffModalBody">
            <!-- Filled by JS -->
        </div>
    </div>
</div>

<script>
const staffData = <?= json_encode($staffList) ?>;

function openEditModal(staffId) {
    const staff = staffData.find(s => s.id === staffId);
    if (!staff) return;
    const perms = staff.permissions || {};
    let html = '<form method="POST" action="">';
    html += '<input type="hidden" name="staff_id" value="' + staff.id + '">';
    html += '<div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="' + (staff.full_name || '').replace(/"/g, '&quot;') + '"></div>';
    html += '<div class="form-group"><label>Permissions</label><div style="display: flex; flex-wrap: wrap; gap: 16px;">';
    html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="checkbox" name="perm_payments" value="1" ' + (perms.payments ? 'checked' : '') + '> Payments</label>';
    html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="checkbox" name="perm_withdrawals" value="1" ' + (perms.withdrawals ? 'checked' : '') + '> Withdrawals</label>';
    html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="checkbox" name="perm_game_accounts" value="1" ' + (perms.game_accounts ? 'checked' : '') + '> Game Accounts</label>';
    html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="checkbox" name="perm_payment_methods" value="1" ' + (perms.payment_methods ? 'checked' : '') + '> Payment Methods</label>';
    html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="checkbox" name="perm_edit_game_credentials" value="1" ' + (perms.edit_game_credentials ? 'checked' : '') + '> Edit Game Username/Password</label>';
    html += '</div></div>';
    html += '<button type="submit" name="update_staff" class="btn btn-block">Save Permissions</button>';
    html += '</form>';
    html += '<hr style="margin: 24px 0; border-color: var(--border-color);">';
    html += '<h4 style="margin-bottom: 12px;">Reset Password</h4>';
    html += '<form method="POST" action=""><input type="hidden" name="staff_id" value="' + staff.id + '">';
    html += '<div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" minlength="4" required></div>';
    html += '<button type="submit" name="reset_staff_password" class="btn btn-warning btn-block">Reset Password</button></form>';
    document.getElementById('editStaffModalBody').innerHTML = html;
    document.getElementById('editStaffModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editStaffModal').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeEditModal(); });
</script>

<?php require __DIR__ . '/_footer.php'; ?>
