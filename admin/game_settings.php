<?php
require_once __DIR__ . '/../config.php';
requireStaffOrAdmin();
if (!isAdmin()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $current = getGameSettings();
    $minWithdrawal = isset($_POST['min_game_withdrawal']) ? (float) $_POST['min_game_withdrawal'] : ($current['min_game_withdrawal'] ?? 60);
    $rolloverMultiplier = isset($_POST['game_rollover_multiplier']) ? (float) $_POST['game_rollover_multiplier'] : ($current['game_rollover_multiplier'] ?? 4);
    $bonusPercent = isset($_POST['game_deposit_bonus_percent']) ? (float) $_POST['game_deposit_bonus_percent'] : ($current['game_deposit_bonus_percent'] ?? 50);
    $minMainWithdrawal = isset($_POST['min_main_withdrawal']) ? (float) $_POST['min_main_withdrawal'] : ($current['min_main_withdrawal'] ?? 100);
    $maxMainWithdrawal = isset($_POST['max_main_withdrawal']) ? (float) $_POST['max_main_withdrawal'] : ($current['max_main_withdrawal'] ?? 500);

    if ($minWithdrawal < 0) $minWithdrawal = 60;
    if ($rolloverMultiplier < 1) $rolloverMultiplier = 4;
    if ($bonusPercent < 0 || $bonusPercent > 100) $bonusPercent = 50;
    if ($minMainWithdrawal < 0) $minMainWithdrawal = 100;
    if ($maxMainWithdrawal < $minMainWithdrawal) $maxMainWithdrawal = $minMainWithdrawal;

    saveGameSettings(array_merge($current, [
        'min_game_withdrawal' => $minWithdrawal,
        'game_rollover_multiplier' => $rolloverMultiplier,
        'game_deposit_bonus_percent' => $bonusPercent,
        'min_main_withdrawal' => $minMainWithdrawal,
        'max_main_withdrawal' => $maxMainWithdrawal
    ]));
    $success = 'Game settings saved.';
}

$settings = getGameSettings();

$adminPageTitle = 'Game Settings';
$adminCurrentPage = 'game_settings';
$adminPageSubtitle = 'Game rules, main balance withdrawal limits, and deposit bonus.';
if (!isset($pendingCounts)) $pendingCounts = [];
require __DIR__ . '/_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-card">
    <h2 class="admin-card-title"><i class="fas fa-cog"></i> Game Rules (Users)</h2>
    <form method="POST" action="">
        <div class="form-group">
            <label>Minimum game withdrawal ($)</label>
            <input type="number" name="min_game_withdrawal" class="form-control" step="1" min="0"
                value="<?= (int) ($settings['min_game_withdrawal'] ?? 60) ?>">
            <small class="form-text text-muted">Users must withdraw at least this amount from a game.</small>
        </div>
        <div class="form-group">
            <label>Rollover multiplier (×)</label>
            <input type="number" name="game_rollover_multiplier" class="form-control" step="0.5" min="1"
                value="<?= (float) ($settings['game_rollover_multiplier'] ?? 4) ?>">
            <small class="form-text text-muted">Users can withdraw from game only up to (deposits + bonus) × this value.</small>
        </div>
        <div class="form-group">
            <label>Game deposit bonus (%)</label>
            <input type="number" name="game_deposit_bonus_percent" class="form-control" step="0.5" min="0" max="100"
                value="<?= (float) ($settings['game_deposit_bonus_percent'] ?? 50) ?>">
            <small class="form-text text-muted">When admin approves a game deposit, this % is added as bonus (credited to game).</small>
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary">Save settings</button>
    </form>
</div>

<div class="admin-card" style="margin-top: 24px;">
    <h2 class="admin-card-title"><i class="fas fa-wallet"></i> Main Balance Withdrawal (Chime / PayPal)</h2>
    <form method="POST" action="">
        <div class="form-group">
            <label>Minimum withdrawal ($)</label>
            <input type="number" name="min_main_withdrawal" class="form-control" step="1" min="0"
                value="<?= (int) ($settings['min_main_withdrawal'] ?? 100) ?>">
            <small class="form-text text-muted">Users must request at least this amount when withdrawing to Chime or PayPal.</small>
        </div>
        <div class="form-group">
            <label>Maximum withdrawal ($)</label>
            <input type="number" name="max_main_withdrawal" class="form-control" step="1" min="0"
                value="<?= (int) ($settings['max_main_withdrawal'] ?? 500) ?>">
            <small class="form-text text-muted">Users cannot request more than this amount per withdrawal from main balance.</small>
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary">Save settings</button>
    </form>
</div>
