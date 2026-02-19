<?php
if (!defined('ADMIN_LAYOUT_LOADED')) {
    define('ADMIN_LAYOUT_LOADED', true);
}
$adminPageTitle = $adminPageTitle ?? 'Admin';
$adminCurrentPage = $adminCurrentPage ?? '';
if (!isset($pendingCounts)) $pendingCounts = [];
$isStaff = isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle) ?> - JAMES GAMEROOM <?= $isStaff ? 'Staff' : 'Admin' ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/realtime.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <span class="admin-sidebar-logo">🎰</span>
            <span class="admin-sidebar-title">JAMES GAMEROOM</span>
            <span class="admin-sidebar-badge"><?= $isStaff ? 'Staff' : 'Admin' ?></span>
        </div>
        <nav class="admin-sidebar-nav">
            <a href="/admin/dashboard.php" class="admin-nav-item <?= $adminCurrentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
                <?php if (isset($pendingCounts['payments']) && $pendingCounts['payments'] > 0): ?>
                    <span class="admin-nav-badge"><?= (int)$pendingCounts['payments'] ?></span>
                <?php endif; ?>
            </a>
            <a href="/admin/users.php" class="admin-nav-item <?= $adminCurrentPage === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="/admin/activity.php" class="admin-nav-item <?= $adminCurrentPage === 'activity' ? 'active' : '' ?>">
                <i class="fas fa-sync-alt"></i>
                <span>Activity & Spin Logs</span>
            </a>
            <a href="/admin/support.php" class="admin-nav-item <?= $adminCurrentPage === 'support' ? 'active' : '' ?>">
                <i class="fas fa-headset"></i>
                <span>Support Chat</span>
            </a>
            <?php if (canAccess('payments')): ?>
            <a href="/admin/payments.php" class="admin-nav-item <?= $adminCurrentPage === 'payments' ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>Payments</span>
                <?php if (isset($pendingCounts['withdrawals']) && $pendingCounts['withdrawals'] > 0): ?>
                    <span class="admin-nav-badge"><?= (int)$pendingCounts['withdrawals'] ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <?php if (canAccess('game_accounts')): ?>
            <a href="/admin/games.php" class="admin-nav-item <?= $adminCurrentPage === 'games' ? 'active' : '' ?>">
                <i class="fas fa-gamepad"></i>
                <span>Game Accounts</span>
                <?php if (isset($pendingCounts['game_requests']) && ($pendingCounts['game_requests'] + $pendingCounts['account_requests']) > 0): ?>
                    <span class="admin-nav-badge"><?= (int)($pendingCounts['game_requests'] + $pendingCounts['account_requests']) ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <?php if (canAccess('payment_methods')): ?>
            <a href="/admin/payment_methods.php" class="admin-nav-item <?= $adminCurrentPage === 'payment_methods' ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payment Methods</span>
            </a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a href="/admin/game_catalog.php" class="admin-nav-item <?= $adminCurrentPage === 'game_catalog' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span>Game Catalog</span>
            </a>
            <a href="/admin/game_settings.php" class="admin-nav-item <?= $adminCurrentPage === 'game_settings' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>Game Settings</span>
            </a>
            <a href="/admin/staff.php" class="admin-nav-item <?= $adminCurrentPage === 'staff' ? 'active' : '' ?>">
                <i class="fas fa-user-tie"></i>
                <span>Staff Accounts</span>
            </a>
            <?php endif; ?>
        </nav>
        <div class="admin-sidebar-footer">
            <a href="/admin/logout.php" class="admin-nav-item admin-nav-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="admin-main-wrap">
        <header class="admin-topbar">
            <button type="button" class="admin-sidebar-toggle" id="adminSidebarToggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="admin-topbar-title">
                <h1><?= htmlspecialchars($adminPageTitle) ?></h1>
                <?php if (!empty($adminPageSubtitle)): ?>
                    <p class="admin-topbar-subtitle"><?= htmlspecialchars($adminPageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="admin-topbar-user" style="display:flex;align-items:center;gap:12px;">
                <button type="button" id="james-notification-trigger" class="james-notification-trigger" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="james-nc-badge" id="james-nc-badge" style="display:none;">0</span>
                </button>
                <span><i class="fas fa-<?= $isStaff ? 'user' : 'user-shield' ?>"></i> <?= htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['staff_username'] ?? 'Admin') ?></span>
            </div>
        </header>

        <main class="admin-content">
