<?php
session_start();

// Base path for assets (empty = document root; works when app is in subfolder e.g. /htdocs)
if (!defined('BASE_PATH')) {
    $sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = ($sn === '' || $sn === '/') ? '' : dirname($sn);
    $base = ($base === '/' || $base === '') ? '' : rtrim($base, '/');
    if ($base !== '' && in_array(basename($base), ['admin', 'auth', 'games'], true)) $base = rtrim(dirname($base), '/');
    define('BASE_PATH', $base);
}

// JSON file paths
define('USERS_FILE', __DIR__ . '/json/users.json');
define('PAYMENTS_FILE', __DIR__ . '/json/payments.json');
define('GAME_REQUESTS_FILE', __DIR__ . '/json/game_requests.json');
define('GAME_WITHDRAWALS_FILE', __DIR__ . '/json/game_withdrawals.json');
define('GAME_ACCOUNT_REQUESTS_FILE', __DIR__ . '/json/game_account_requests.json');
define('PASSWORD_RESET_REQUESTS_FILE', __DIR__ . '/json/password_reset_requests.json');
define('WITHDRAWAL_REQUESTS_FILE', __DIR__ . '/json/withdrawal_requests.json');
define('PAYMENT_METHODS_FILE', __DIR__ . '/json/payment_methods.json');
define('STAFF_FILE', __DIR__ . '/json/staff.json');
define('GAMES_CONFIG_FILE', __DIR__ . '/json/games.json');
define('SPIN_LOGS_FILE', __DIR__ . '/json/spin_logs.json');
define('GAME_SETTINGS_FILE', __DIR__ . '/json/game_settings.json');
define('SUPPORT_CHATS_FILE', __DIR__ . '/json/support_chats.json');
define('SUPPORT_UPLOADS_DIR', __DIR__ . '/uploads/support');
define('REALTIME_QUEUE_FILE', __DIR__ . '/json/realtime_queue.json');
define('REALTIME_EVENT_LOG_FILE', __DIR__ . '/json/realtime_event_log.json');
define('MINES_GAMES_FILE', __DIR__ . '/json/mines_games.json');
define('MINES_ACTIVE_FILE', __DIR__ . '/json/mines_active.json');
define('PICKPATH_GAMES_FILE', __DIR__ . '/json/pickpath_games.json');
define('PAYGATETX_FILE', __DIR__ . '/json/paygatetx.json');

// Admin credentials (change in production)
define('ADMIN_USERNAME', 'rajababugamesxd');
define('ADMIN_PASSWORD', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // password: password

// Helper functions
function readJSON($file) {
    if (!file_exists($file)) {
        file_put_contents($file, '[]');
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function writeJSON($file, $data) {
    // Create backup
    if (file_exists($file)) {
        $backup = $file . '.backup.' . date('Y-m-d_H-i-s');
        copy($file, $backup);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function getCurrentStaff() {
    if (!isStaff() || empty($_SESSION['staff_id'])) return null;
    $staff = readJSON(STAFF_FILE);
    foreach ($staff as $s) {
        if (($s['id'] ?? '') === $_SESSION['staff_id']) return $s;
    }
    return null;
}

/** Staff permission keys: payments, withdrawals, game_accounts, payment_methods */
function canAccess($area) {
    if (isAdmin()) return true;
    if (!isStaff()) return false;
    $staff = getCurrentStaff();
    return $staff && !empty($staff['permissions'][$area]);
}

/** Use for admin panel pages: allow both admin and staff (staff see limited data) */
function requireStaffOrAdmin() {
    if (isAdmin() || isStaff()) return;
    header('Location: /admin/login.php');
    exit;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    $users = readJSON(USERS_FILE);
    foreach ($users as $user) {
        if ($user['id'] == $_SESSION['user_id']) {
            return $user;
        }
    }
    return null;
}

function generateId() {
    return uniqid('', true);
}

/** Generate a unique referral code (8 chars, uppercase alphanumeric). */
function generateReferralCode(array $users) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $exists = false;
        foreach ($users as $u) {
            if (strtoupper(trim($u['referral_code'] ?? '')) === $code) {
                $exists = true;
                break;
            }
        }
    } while ($exists);
    return $code;
}

/** Find user by referral code (case-insensitive). Returns user array or null. */
function findUserByReferralCode(array $users, $code) {
    $code = strtoupper(trim($code ?? ''));
    if ($code === '') return null;
    foreach ($users as $user) {
        if (strtoupper(trim($user['referral_code'] ?? '')) === $code) {
            return $user;
        }
    }
    return null;
}

/** Ensure user has a referral code; generate and save if missing. Returns the code. */
function ensureUserReferralCode(&$user, array &$users) {
    $code = trim($user['referral_code'] ?? '');
    if ($code !== '') return $code;
    $code = generateReferralCode($users);
    foreach ($users as &$u) {
        if (($u['id'] ?? '') === ($user['id'] ?? '')) {
            $u['referral_code'] = $code;
            $user['referral_code'] = $code;
            break;
        }
    }
    writeJSON(USERS_FILE, $users);
    return $code;
}

/** Weekly leaderboard: "this week" = last 7 days. Referral score = 50 points per referred user. */
define('LEADERBOARD_REFERRAL_POINTS', 50);

/** Get start of current week (last 7 days) as Y-m-d 00:00:00. */
function getWeeklyLeaderboardStart() {
    return date('Y-m-d 00:00:00', strtotime('-7 days'));
}

/** Get weekly deposit total for a user (approved payments + approved PayGate tx in last 7 days). */
function getWeeklyDepositTotal($userId, array $payments, array $paygateTx, array $users) {
    $start = getWeeklyLeaderboardStart();
    $total = 0;
    foreach ($payments as $p) {
        if (($p['user_id'] ?? '') !== $userId || ($p['status'] ?? '') !== 'approved') continue;
        if (($p['date'] ?? '') >= $start) $total += (float)($p['amount'] ?? 0);
    }
    $userEmail = null;
    foreach ($users as $u) {
        if (($u['id'] ?? '') === $userId) { $userEmail = trim($u['email'] ?? ''); break; }
    }
    if ($userEmail !== null && $userEmail !== '') {
        foreach ($paygateTx as $tx) {
            if (strcasecmp(trim($tx['email'] ?? ''), $userEmail) !== 0 || ($tx['status'] ?? '') !== 'approved') continue;
            if (($tx['server_time'] ?? '') >= $start) $total += (float)($tx['amount'] ?? 0);
        }
    }
    return $total;
}

/** Get number of users referred by this user. */
function getReferralCount($userId, array $users) {
    $count = 0;
    foreach ($users as $u) {
        if (($u['referred_by'] ?? '') === $userId) $count++;
    }
    return $count;
}

/** Get weekly leaderboard: top N by score. Score = weekly_deposit + (referrals * LEADERBOARD_REFERRAL_POINTS). Returns array of [rank, user, score, weekly_deposit, referrals]. */
function getWeeklyLeaderboard(array $users, array $payments, array $paygateTx, $limit = 100) {
    $rows = getWeeklyLeaderboardData($users, $payments, $paygateTx);
    usort($rows, function ($a, $b) { return $b['score'] <=> $a['score']; });
    $rows = array_slice($rows, 0, $limit);
    foreach ($rows as $i => &$r) {
        $r['rank'] = $i + 1;
    }
    return $rows;
}

/** Get all users with weekly stats (no limit). For use on leaderboard page with different sort orders. */
function getWeeklyLeaderboardData(array $users, array $payments, array $paygateTx) {
    $rows = [];
    foreach ($users as $user) {
        $uid = $user['id'] ?? '';
        if ($uid === '') continue;
        $weeklyDeposit = getWeeklyDepositTotal($uid, $payments, $paygateTx, $users);
        $referrals = getReferralCount($uid, $users);
        $score = $weeklyDeposit + ($referrals * LEADERBOARD_REFERRAL_POINTS);
        $rows[] = [
            'user' => $user,
            'score' => $score,
            'weekly_deposit' => $weeklyDeposit,
            'referrals' => $referrals
        ];
    }
    return $rows;
}

/** Get profile photo URL for user (relative path or empty). */
function getProfilePhotoUrl($user) {
    $path = trim($user['profile_photo'] ?? '');
    if ($path === '' || !file_exists(__DIR__ . '/' . $path)) return '';
    return '/' . ltrim($path, '/');
}

/** Spin wheel: 1 free spin per day or $1 paid. Returns rewards config. $cost = 1 or 5. */
function getSpinRewardsConfig($cost = 1) {
    if ($cost == 5) {
        return [
            ['type' => 'balance', 'value' => 100.00, 'label' => '$100'],
            ['type' => 'balance', 'value' => 50.00, 'label' => '$50'],
            ['type' => 'balance', 'value' => 10.00, 'label' => '$10'],
            ['type' => 'balance', 'value' => 7.00, 'label' => '$7'],
            ['type' => 'balance', 'value' => 0, 'label' => '$0'],
            ['type' => 'balance', 'value' => 5.00, 'label' => '$5'],
        ];
    }
    return [
        ['type' => 'balance', 'value' => 0.50, 'label' => '$0.50'],
        ['type' => 'balance', 'value' => 1.00, 'label' => '$1.00'],
        ['type' => 'balance', 'value' => 2.00, 'label' => '$2.00'],
        ['type' => 'balance', 'value' => 0.25, 'label' => '$0.25'],
        ['type' => 'balance', 'value' => 5.00, 'label' => '$5.00'],
        ['type' => 'bonus', 'value' => 0.50, 'label' => '$0.50 Bonus'],
        ['type' => 'balance', 'value' => 1.50, 'label' => '$1.50'],
        ['type' => 'balance', 'value' => 0.75, 'label' => '$0.75'],
    ];
}

/**
 * Spin wheel weights. $cost = 1: $1 wheel. $cost = 5: $5 wheel (1% $100, 2% $50, 7% $10, 15% $7, 40% $0, 35% $5).
 */
function getSpinWheelWeights($cost = 1) {
    if ($cost == 5) {
        return [
            0 => 1,   // $100 - 1%
            1 => 2,   // $50  - 2%
            2 => 7,   // $10  - 7%
            3 => 15,  // $7   - 15%
            4 => 40,  // $0   - 40%
            5 => 35,  // $5   - 35%
        ];
    }
    return [
        0 => 18,  // $0.50
        1 => 8,   // $1.00
        2 => 8,   // $2.00
        3 => 19,  // $0.25
        4 => 1,   // $5.00
        5 => 19,  // $0.50 Bonus
        6 => 8,   // $1.50
        7 => 19,  // $0.75
    ];
}

/** Pick a weighted random reward index (0-based) for the spin wheel. $cost = 1 or 5. */
function getWeightedSpinIndex($cost = 1) {
    $weights = getSpinWheelWeights($cost);
    $total = array_sum($weights);
    $r = mt_rand(1, (int) $total);
    $cum = 0;
    foreach ($weights as $index => $w) {
        $cum += $w;
        if ($r <= $cum) {
            return (int) $index;
        }
    }
    $keys = array_keys($weights);
    return (int) end($keys);
}

/** Check if user can spin today (1 per day). */
function canUserSpinToday($user) {
    $today = date('Y-m-d');
    $last = $user['last_spin_date'] ?? null;
    return $last !== $today;
}

/** Get user spin streak (consecutive days). */
function getUserSpinStreak($user) {
    return (int)($user['spin_streak'] ?? 0);
}

/** Default game list with slug and logo filename (used when games.json is empty). */
function getDefaultGamesConfig() {
    return [
        ['id' => 'orion', 'name' => 'Orion', 'slug' => 'orion', 'logo' => 'orionstars.jpg', 'link' => ''],
        ['id' => 'milkyway', 'name' => 'Milkyway', 'slug' => 'milkyway', 'logo' => 'milkyway.jpg', 'link' => ''],
        ['id' => 'firekirin', 'name' => 'Firekirin', 'slug' => 'firekirin', 'logo' => 'firekirin.jpg', 'link' => ''],
        ['id' => 'gamevault', 'name' => 'Gamevault', 'slug' => 'gamevault', 'logo' => 'gamevault.jpg', 'link' => ''],
        ['id' => 'juwa', 'name' => 'Juwa', 'slug' => 'juwa', 'logo' => 'juwa.jpg', 'link' => ''],
        ['id' => 'vegassweeps', 'name' => 'Vegassweeps', 'slug' => 'vegassweeps', 'logo' => 'vegassweeps.jpg', 'link' => ''],
        ['id' => 'riversweeps', 'name' => 'Riversweeps', 'slug' => 'riversweeps', 'logo' => 'riversweeps.png', 'link' => ''],
        ['id' => 'ultrapanda', 'name' => 'Ultrapanda', 'slug' => 'ultrapanda', 'logo' => 'ultrapanda.jpg', 'link' => ''],
        ['id' => 'vblink', 'name' => 'Vblink', 'slug' => 'vblink', 'logo' => 'vblink.jpg', 'link' => ''],
        ['id' => 'pandamaster', 'name' => 'Pandamaster', 'slug' => 'pandamaster', 'logo' => 'pandamaster.jpg', 'link' => ''],
        ['id' => 'cashmachine', 'name' => 'Cash Machine', 'slug' => 'cashmachine', 'logo' => 'cashmachine.png', 'link' => ''],
        ['id' => 'gameroom', 'name' => 'Gameroom', 'slug' => 'gameroom', 'logo' => 'gameroom.png', 'link' => ''],
    ];
}

/** Get games config from JSON; fallback to default. */
function getGamesConfig() {
    if (!file_exists(GAMES_CONFIG_FILE)) {
        $default = getDefaultGamesConfig();
        writeJSON(GAMES_CONFIG_FILE, $default);
        return $default;
    }
    $games = readJSON(GAMES_CONFIG_FILE);
    if (empty($games)) {
        return getDefaultGamesConfig();
    }
    return $games;
}

/** Save games config to JSON. */
function saveGamesConfig(array $games) {
    writeJSON(GAMES_CONFIG_FILE, $games);
}

/** Get game config by slug (from games config). Returns array with name, slug, logo, link or null. */
function getGameBySlug($slug) {
    $slug = trim((string) $slug);
    if ($slug === '') return null;
    $games = getGamesConfig();
    foreach ($games as $g) {
        $s = is_array($g) ? ($g['slug'] ?? '') : strtolower(str_replace(' ', '', $g));
        if ($s === $slug) return is_array($g) ? $g : ['name' => $g, 'slug' => $s, 'logo' => '', 'link' => ''];
    }
    return null;
}

/** Get external game link for a game by name (from games config). */
function getGameLink($gameName) {
    $games = getGamesConfig();
    foreach ($games as $g) {
        $name = is_array($g) ? ($g['name'] ?? '') : $g;
        if ($name === $gameName) {
            $link = is_array($g) ? ($g['link'] ?? '') : '';
            return is_string($link) && $link !== '' ? $link : null;
        }
    }
    return null;
}

/** Get logo URL for a game (array from config or name string). */
function getGameLogo($gameNameOrArray) {
    if (is_array($gameNameOrArray)) {
        $logo = $gameNameOrArray['logo'] ?? '';
        if ($logo === '') return null;
        if (strpos($logo, '/') !== false || strpos($logo, 'http') === 0) {
            return $logo;
        }
        $path = __DIR__ . '/gameslogo/' . $logo;
        return file_exists($path) ? '/gameslogo/' . $logo : null;
    }
    $logoMap = [
        'Orion' => 'orionstars.jpg', 'Milkyway' => 'milkyway.jpg', 'Firekirin' => 'firekirin.jpg',
        'Gamevault' => 'gamevault.jpg', 'Juwa' => 'juwa.jpg', 'Vegassweeps' => 'vegassweeps.jpg',
        'Riversweeps' => 'riversweeps.png', 'Ultrapanda' => 'ultrapanda.jpg', 'Vblink' => 'vblink.jpg',
        'Pandamaster' => 'pandamaster.jpg', 'Cash Machine' => 'cashmachine.png', 'Gameroom' => 'gameroom.png'
    ];
    $filename = $logoMap[$gameNameOrArray] ?? null;
    if ($filename && file_exists(__DIR__ . '/gameslogo/' . $filename)) {
        return '/gameslogo/' . $filename;
    }
    return null;
}

/** Game rules: min withdrawal, rollover multiplier, deposit bonus %. Main balance: min/max withdrawal. */
function getGameSettings() {
    $default = [
        'min_game_withdrawal' => 60,
        'game_rollover_multiplier' => 4,
        'game_deposit_bonus_percent' => 50,
        'min_main_withdrawal' => 100,
        'max_main_withdrawal' => 500,
        'mines_min_bet' => 0.10,
        'mines_max_bet' => 500,
        'pickpath_min_bet' => 0.10,
        'pickpath_max_bet' => 500
    ];
    if (!file_exists(GAME_SETTINGS_FILE)) {
        writeJSON(GAME_SETTINGS_FILE, $default);
        return $default;
    }
    $data = readJSON(GAME_SETTINGS_FILE);
    return array_merge($default, is_array($data) ? $data : []);
}

function saveGameSettings(array $data) {
    writeJSON(GAME_SETTINGS_FILE, $data);
}

/** Per-user per-game: total base deposit, total bonus, total withdrawn, rollover allowed, max withdrawable. */
function getUserGameRolloverInfo($user, $gameName) {
    $settings = getGameSettings();
    $multiplier = (float) ($settings['game_rollover_multiplier'] ?? 4);
    $userId = $user['id'] ?? '';

    $totalBase = 0;
    $totalBonus = 0;
    $depositLog = [];

    if (!empty($user['game_deposit_log']) && is_array($user['game_deposit_log'])) {
        foreach ($user['game_deposit_log'] as $e) {
            if (($e['game'] ?? '') !== $gameName) continue;
            $totalBase += (float) ($e['base_amount'] ?? 0);
            $totalBonus += (float) ($e['bonus_amount'] ?? 0);
            $depositLog[] = $e;
        }
    }
    if (!empty($user['game_deposit_requests']) && is_array($user['game_deposit_requests'])) {
        foreach ($user['game_deposit_requests'] as $e) {
            if (($e['game'] ?? '') !== $gameName) continue;
            $amt = (float) ($e['amount'] ?? 0);
            $totalBase += $amt;
            $depositLog[] = [
                'date' => $e['date'] ?? '',
                'base_amount' => $amt,
                'bonus_amount' => 0,
                'total_credited' => $amt,
                'request_id' => $e['id'] ?? ''
            ];
        }
    }
    usort($depositLog, function ($a, $b) {
        $da = $a['date'] ?? '';
        $db = $b['date'] ?? '';
        return strcmp($db, $da);
    });

    $totalDepositPlusBonus = $totalBase + $totalBonus;
    $rolloverAllowed = $totalDepositPlusBonus * $multiplier;

    $totalWithdrawn = 0;
    $gameWithdrawals = readJSON(GAME_WITHDRAWALS_FILE);
    foreach ($gameWithdrawals as $w) {
        if (($w['user_id'] ?? '') !== $userId || ($w['game'] ?? '') !== $gameName || ($w['status'] ?? '') !== 'approved') continue;
        $totalWithdrawn += (float) ($w['amount'] ?? 0);
    }

    $maxWithdrawable = max(0, $rolloverAllowed - $totalWithdrawn);

    // Minimum per withdrawal = global setting ($60). Rollover (4×) only caps max — so after losing and depositing again they can still withdraw (min $60 up to max_withdrawable).
    $minWithdrawal = (float) ($settings['min_game_withdrawal'] ?? 60);

    return [
        'min_withdrawal' => $minWithdrawal,
        'rollover_multiplier' => $multiplier,
        'total_base' => $totalBase,
        'total_bonus' => $totalBonus,
        'total_deposit_plus_bonus' => $totalDepositPlusBonus,
        'rollover_allowed' => $rolloverAllowed,
        'total_withdrawn' => $totalWithdrawn,
        'max_withdrawable' => $maxWithdrawable,
        'deposit_log' => $depositLog
    ];
}

/** Support chat reasons. */
function getSupportReasons() {
    return ['Deposit issue', 'Withdrawal issue', 'Game account', 'Spin/Bonus', 'Other'];
}

/** All support chats (for admin). */
function getSupportChats() {
    return readJSON(SUPPORT_CHATS_FILE);
}

/** Get one chat by id. */
function getSupportChatById($id) {
    $chats = readJSON(SUPPORT_CHATS_FILE);
    foreach ($chats as $c) {
        if (($c['id'] ?? '') === $id) return $c;
    }
    return null;
}

/** Chats for a user. */
function getSupportChatsByUserId($userId) {
    $chats = readJSON(SUPPORT_CHATS_FILE);
    return array_values(array_filter($chats, function ($c) use ($userId) {
        return ($c['user_id'] ?? '') === $userId;
    }));
}

/** Create support chat. Returns chat or null. */
function createSupportChat($userId, $reason, $initialText = '', $initialImagePath = null) {
    if (!file_exists(SUPPORT_UPLOADS_DIR)) {
        mkdir(SUPPORT_UPLOADS_DIR, 0755, true);
    }
    $chats = readJSON(SUPPORT_CHATS_FILE);
    $id = 'sup_' . generateId();
    $messages = [];
    if ($initialText !== '' || $initialImagePath !== null) {
        $messages[] = [
            'id' => 'msg_' . generateId(),
            'from' => 'user',
            'sender_id' => $userId,
            'sender_name' => 'User: ' . getUserDisplayNameForSupport($userId),
            'text' => $initialText,
            'image' => $initialImagePath,
            'date' => date('Y-m-d H:i:s')
        ];
    }
    $chat = [
        'id' => $id,
        'user_id' => $userId,
        'reason' => $reason,
        'status' => 'open',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'last_seen_by_user_at' => null,
        'last_seen_by_staff_at' => null,
        'messages' => $messages
    ];
    $chats[] = $chat;
    writeJSON(SUPPORT_CHATS_FILE, $chats);
    return $chat;
}

/** Add message to support chat. Returns new message or null. $senderName = display label e.g. "User: John", "Admin", "Staff: Jane". */
function addSupportMessage($chatId, $from, $senderId, $text, $imagePath = null, $senderName = null) {
    $chats = readJSON(SUPPORT_CHATS_FILE);
    foreach ($chats as &$c) {
        if (($c['id'] ?? '') !== $chatId) continue;
        if ($senderName === null) {
            if ($from === 'user') $senderName = 'User: ' . getUserDisplayNameForSupport($c['user_id'] ?? '');
            elseif ($from === 'admin') $senderName = 'Admin';
            else $senderName = 'Staff: ' . getStaffDisplayNameForSupport($senderId);
        }
        $c['messages'][] = [
            'id' => 'msg_' . generateId(),
            'from' => $from,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'text' => $text,
            'image' => $imagePath,
            'date' => date('Y-m-d H:i:s')
        ];
        $c['updated_at'] = date('Y-m-d H:i:s');
        writeJSON(SUPPORT_CHATS_FILE, $chats);
        return end($c['messages']);
    }
    return null;
}

/** Close support chat. */
function closeSupportChat($chatId) {
    $chats = readJSON(SUPPORT_CHATS_FILE);
    foreach ($chats as &$c) {
        if (($c['id'] ?? '') === $chatId) {
            $c['status'] = 'closed';
            $c['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    writeJSON(SUPPORT_CHATS_FILE, $chats);
}

/** Reopen support chat. */
function reopenSupportChat($chatId) {
    $chats = readJSON(SUPPORT_CHATS_FILE);
    foreach ($chats as &$c) {
        if (($c['id'] ?? '') === $chatId) {
            $c['status'] = 'open';
            $c['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    writeJSON(SUPPORT_CHATS_FILE, $chats);
}

/** Get user display name for support (username or full_name). */
function getUserDisplayNameForSupport($userId) {
    $users = readJSON(USERS_FILE);
    foreach ($users as $u) {
        if (($u['id'] ?? '') === $userId) {
            return $u['full_name'] ?? $u['username'] ?? 'User';
        }
    }
    return 'User';
}

/** Get staff display name for support. */
function getStaffDisplayNameForSupport($staffId) {
    if (empty($staffId) || $staffId === 'admin') return 'Admin';
    $staffList = readJSON(STAFF_FILE);
    foreach ($staffList as $s) {
        if (($s['id'] ?? '') === $staffId) {
            return $s['name'] ?? $s['username'] ?? 'Staff';
        }
    }
    return 'Staff';
}

/** Get sender label for a support message (User: Name, Admin, Staff: Name). */
function getSupportMessageSenderLabel($m, $chat) {
    $from = $m['from'] ?? '';
    if (isset($m['sender_name']) && $m['sender_name'] !== '') return $m['sender_name'];
    if ($from === 'user') return 'User: ' . getUserDisplayNameForSupport($chat['user_id'] ?? '');
    if ($from === 'admin') return 'Admin';
    if ($from === 'staff') return 'Staff: ' . getStaffDisplayNameForSupport($m['sender_id'] ?? '');
    return 'Support';
}

/** Mark chat as seen by user (when user opens chat). */
function markSupportChatSeenByUser($chatId) {
    $chats = readJSON(SUPPORT_CHATS_FILE);
    $now = date('Y-m-d H:i:s');
    foreach ($chats as &$c) {
        if (($c['id'] ?? '') === $chatId) {
            $c['last_seen_by_user_at'] = $now;
            break;
        }
    }
    writeJSON(SUPPORT_CHATS_FILE, $chats);
}

/** Mark chat as seen by staff/admin (when admin/staff opens chat). */
function markSupportChatSeenByStaff($chatId) {
    $chats = readJSON(SUPPORT_CHATS_FILE);
    $now = date('Y-m-d H:i:s');
    foreach ($chats as &$c) {
        if (($c['id'] ?? '') === $chatId) {
            $c['last_seen_by_staff_at'] = $now;
            break;
        }
    }
    writeJSON(SUPPORT_CHATS_FILE, $chats);
}

/** Check if a message is seen by the other party. */
function isSupportMessageSeenByOther($m, $chat) {
    $from = $m['from'] ?? '';
    $msgDate = $m['date'] ?? '';
    if ($from === 'user') {
        $seenAt = $chat['last_seen_by_staff_at'] ?? null;
        return $seenAt !== null && $msgDate !== '' && strtotime($seenAt) >= strtotime($msgDate);
    }
    $seenAt = $chat['last_seen_by_user_at'] ?? null;
    return $seenAt !== null && $msgDate !== '' && strtotime($seenAt) >= strtotime($msgDate);
}

/** Push event to real-time queue (consumed by WebSocket server). */
function realtimeEmit($event, $payload = []) {
    $queue = file_exists(REALTIME_QUEUE_FILE) ? (json_decode(file_get_contents(REALTIME_QUEUE_FILE), true) ?: []) : [];
    $queue[] = [
        'event' => $event,
        'payload' => $payload,
        'ts' => time()
    ];
    file_put_contents(REALTIME_QUEUE_FILE, json_encode($queue, JSON_UNESCAPED_UNICODE));
}

/** Mines game: generate cryptographically random server seed (hex). */
function minesGenerateServerSeed() {
    return bin2hex(random_bytes(32));
}

/**
 * Mines game: provably fair mine positions from server_seed + client_seed + nonce.
 * Uses HMAC-SHA256 to derive a byte stream, then Fisher-Yates to pick mineCount positions from 0..totalTiles-1.
 * @return int[] Zero-based indices of mines (e.g. 0–24 for 25 tiles).
 */
function minesGetMinePositions($serverSeed, $clientSeed, $nonce, $totalTiles, $mineCount) {
    $input = $clientSeed . '_' . $nonce;
    $bytes = '';
    $round = 0;
    while (strlen($bytes) < $totalTiles * 4) {
        $bytes .= hash_hmac('sha256', $input . '_' . $round, $serverSeed, true);
        $round++;
    }
    $indices = range(0, $totalTiles - 1);
    for ($i = 0; $i < $mineCount; $i++) {
        $byteIndex = $i * 4;
        $n = ord($bytes[$byteIndex]) | (ord($bytes[$byteIndex + 1]) << 8) | (ord($bytes[$byteIndex + 2]) << 16) | (ord($bytes[$byteIndex + 3]) << 24);
        $n = $n & 0x7FFFFFFF;
        $j = $i + ($n % ($totalTiles - $i));
        $tmp = $indices[$i];
        $indices[$i] = $indices[$j];
        $indices[$j] = $tmp;
    }
    return array_slice($indices, 0, $mineCount);
}

/**
 * Mines game: multiplier after revealing n safe tiles (0-based).
 * totalTiles=25, mineCount=mines. mult = product (25-i)/(25-mines-i) for i=0..n-1.
 */
function minesCalculateMultiplier($revealedSafe, $totalTiles, $mineCount) {
    if ($revealedSafe <= 0) return 1.0;
    $mult = 1.0;
    for ($i = 0; $i < $revealedSafe; $i++) {
        $mult *= ($totalTiles - $i) / ($totalTiles - $mineCount - $i);
    }
    return round($mult, 2);
}
?>
