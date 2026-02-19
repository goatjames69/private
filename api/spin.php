<?php
/**
 * Spin Wheel API
 * POST: spin=1. Optional paid=1&cost=1 or paid=1&cost=5 for paid spin.
 * Free: 1 spin per day. Paid: $1 or $5 per spin, no daily limit.
 * Always returns reward_index (0-based) so the client lands on the correct segment.
 */
http_response_code(200);
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed', 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in', 'message' => 'Not logged in']);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found', 'message' => 'User not found']);
    exit;
}

$isPaid = !empty($_POST['paid']);
$cost = isset($_POST['cost']) ? (int) $_POST['cost'] : 1;
if ($cost !== 1 && $cost !== 5) {
    $cost = 1;
}
$spinCost = $isPaid ? (float) $cost : 0;

if ($isPaid) {
    $balance = (float)($user['balance'] ?? 0);
    if ($balance < $cost) {
        echo json_encode([
            'success' => false,
            'error' => 'insufficient_balance',
            'message' => 'You need at least $' . number_format($cost, 0) . '.00 balance to spin.'
        ]);
        exit;
    }
} else {
    if (!canUserSpinToday($user)) {
        echo json_encode([
            'success' => false,
            'error' => 'already_spun',
            'message' => 'You already used your free spin today. Come back tomorrow or spin for $1!'
        ]);
        exit;
    }
}

$rewards = getSpinRewardsConfig($cost);
$index = getWeightedSpinIndex($cost);
$reward = $rewards[$index];

$today = date('Y-m-d');
$lastSpin = $user['last_spin_date'] ?? null;
$yesterday = date('Y-m-d', strtotime('-1 day'));

$newStreak = $isPaid ? (int)($user['spin_streak'] ?? 0) : 1;
if (!$isPaid) {
    if ($lastSpin === $yesterday) {
        $newStreak = (int)($user['spin_streak'] ?? 0) + 1;
    } elseif ($lastSpin !== null && $lastSpin !== $today) {
        $newStreak = 1;
    } else {
        $newStreak = (int)($user['spin_streak'] ?? 1);
    }
}

$users = readJSON(USERS_FILE);
foreach ($users as &$u) {
    if (($u['id'] ?? '') === $user['id']) {
        if ($isPaid) {
            $u['balance'] = (float)($u['balance'] ?? 0) - $spinCost;
        } else {
            $u['last_spin_date'] = $today;
            $u['spin_streak'] = $newStreak;
        }
        $u['total_spins'] = (int)($u['total_spins'] ?? 0) + 1;

        if (($reward['type'] ?? '') === 'balance' && isset($reward['value'])) {
            $u['balance'] = (float)($u['balance'] ?? 0) + (float)$reward['value'];
        }
        if (($reward['type'] ?? '') === 'bonus') {
            $u['balance'] = (float)($u['balance'] ?? 0) + 0.50;
            $reward['label'] = '$0.50 Bonus';
            $reward['value'] = 0.50;
        }
        break;
    }
}
unset($u);
writeJSON(USERS_FILE, $users);

$newBalance = null;
foreach ($users as $u) {
    if (($u['id'] ?? '') === $user['id']) {
        $newBalance = (float)$u['balance'];
        break;
    }
}

// Log spin for admin/staff dashboard
$spinLogs = file_exists(SPIN_LOGS_FILE) ? json_decode(file_get_contents(SPIN_LOGS_FILE), true) : [];
if (!is_array($spinLogs)) $spinLogs = [];
array_unshift($spinLogs, [
    'user_id' => $user['id'],
    'username' => $user['username'] ?? '',
    'full_name' => $user['full_name'] ?? '',
    'date' => date('Y-m-d H:i:s'),
    'reward_label' => $reward['label'] ?? 'Bonus',
    'reward_value' => (float)($reward['value'] ?? 0),
    'paid' => $isPaid,
    'new_balance' => $newBalance,
]);
file_put_contents(SPIN_LOGS_FILE, json_encode(array_slice($spinLogs, 0, 2000), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'success' => true,
    'reward' => [
        'type' => $reward['type'] ?? 'bonus',
        'label' => $reward['label'] ?? 'Bonus',
        'value' => $reward['value'] ?? 0
    ],
    'reward_index' => $index,
    'paid' => $isPaid,
    'cost' => $cost,
    'streak' => $newStreak,
    'total_spins' => (int)($user['total_spins'] ?? 0) + 1,
    'new_balance' => $newBalance
]);
exit;
