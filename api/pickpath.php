<?php
/**
 * Pick-a-Path casino game API – session-based, 3 nodes (1 bust, 2 safe).
 * Actions: start, move, cashout.
 * State in $_SESSION; client never receives bomb location before move.
 */
error_reporting(0);
ini_set('display_errors', 0);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function pickpathJson($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../config.php';
} catch (Throwable $e) {
    pickpathJson(['success' => false, 'error' => 'Server config error']);
}

if (!isLoggedIn()) {
    pickpathJson(['success' => false, 'error' => 'Not logged in']);
}
$user = getCurrentUser();
if (!$user) {
    pickpathJson(['success' => false, 'error' => 'User not found']);
}

$input = file_get_contents('php://input');
$data = [];
$ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($ct, 'application/x-www-form-urlencoded') !== false && $input !== false && $input !== '') {
    parse_str($input, $data);
}
if (empty($data) && !empty($_POST)) $data = $_POST;
if (empty($data) && $input) {
    $dec = json_decode($input, true);
    if (is_array($dec)) $data = $dec;
}
if (!is_array($data)) $data = [];

$action = isset($data['action']) ? trim((string) $data['action']) : '';

$settings = getGameSettings();
$minBet = (float) ($settings['pickpath_min_bet'] ?? 0.10);
$maxBet = (float) ($settings['pickpath_max_bet'] ?? 500);

// Stage-based multipliers: inverse of probability minus 3% house edge.
// Stage 1: 1.23x, Stage 2: 1.57x, Stage 3: 2.00x, Stage 4: 2.55x, Stage 5 (Goal): 3.25x
function pickpathMultiplierTable() {
    return [1.0, 1.23, 1.57, 2.00, 2.55, 3.25];
}

function pickpathGetMultiplier($step) {
    $table = pickpathMultiplierTable();
    $idx = min(max(0, (int) $step), count($table) - 1);
    return (float) $table[$idx];
}

function deductBalance($userId, $amount) {
    $users = readJSON(USERS_FILE);
    foreach ($users as &$u) {
        if (($u['id'] ?? '') !== $userId) continue;
        $bal = (float) ($u['balance'] ?? 0);
        if ($bal < $amount) return false;
        $u['balance'] = $bal - $amount;
        break;
    }
    unset($u);
    writeJSON(USERS_FILE, $users);
    return true;
}

function creditBalance($userId, $amount) {
    $users = readJSON(USERS_FILE);
    foreach ($users as &$u) {
        if (($u['id'] ?? '') === $userId) {
            $u['balance'] = (float) ($u['balance'] ?? 0) + $amount;
            break;
        }
    }
    unset($u);
    writeJSON(USERS_FILE, $users);
}

function getBalance($userId) {
    $users = readJSON(USERS_FILE);
    foreach ($users as $u) {
        if (($u['id'] ?? '') === $userId) return (float) ($u['balance'] ?? 0);
    }
    return 0;
}

function pickpathClearSession() {
    unset($_SESSION['pickpath_bet'], $_SESSION['pickpath_initial_bet'], $_SESSION['pickpath_step'],
          $_SESSION['pickpath_current_step'], $_SESSION['pickpath_multiplier'], $_SESSION['pickpath_status'],
          $_SESSION['pickpath_game_id'], $_SESSION['pickpath_user_id'], $_SESSION['pickpath_token']);
}

// Validate session game belongs to current user
function pickpathValidateActive() {
    if (empty($_SESSION['pickpath_status']) || $_SESSION['pickpath_status'] !== 'active') return false;
    if (empty($_SESSION['pickpath_user_id']) || $_SESSION['pickpath_user_id'] !== ($_SESSION['user_id'] ?? '')) return false;
    return true;
}

switch ($action) {
    case 'start': {
        $bet = isset($data['bet']) ? (float) $data['bet'] : 0;
        if ($bet < $minBet || $bet > $maxBet) {
            pickpathJson(['success' => false, 'error' => 'Bet must be between $' . number_format($minBet, 2) . ' and $' . number_format($maxBet, 2)]);
        }
        if (!deductBalance($user['id'], $bet)) {
            pickpathJson(['success' => false, 'error' => 'Insufficient balance']);
        }
        pickpathClearSession();
        $gameId = uniqid('', true);
        $_SESSION['pickpath_user_id'] = $user['id'];
        $_SESSION['pickpath_bet'] = $bet;
        $_SESSION['pickpath_initial_bet'] = $bet;
        $_SESSION['pickpath_step'] = 0;
        $_SESSION['pickpath_current_step'] = 0;
        $_SESSION['pickpath_multiplier'] = 1.0;
        $_SESSION['pickpath_status'] = 'active';
        $_SESSION['pickpath_game_id'] = $gameId;
        $_SESSION['pickpath_token'] = bin2hex(random_bytes(16));

        // Balance deducted here only; credited only on cashout (no double spend).
        pickpathJson([
            'success' => true,
            'game_id' => $gameId,
            'bet' => $bet,
            'multiplier' => 1.0,
            'step' => 0,
            'balance' => getBalance($user['id']),
            'token' => $_SESSION['pickpath_token'],
        ]);
    }

    case 'move': {
        if (!pickpathValidateActive()) {
            pickpathJson(['success' => false, 'error' => 'No active game. Start a new game.']);
        }
        $choice = isset($data['choice']) ? (int) $data['choice'] : 0;
        if ($choice < 1 || $choice > 3) {
            pickpathJson(['success' => false, 'error' => 'Choice must be 1, 2, or 3']);
        }
        $token = isset($data['token']) ? trim((string) $data['token']) : '';
        if ($token !== '' && (!isset($_SESSION['pickpath_token']) || $token !== $_SESSION['pickpath_token'])) {
            pickpathJson(['success' => false, 'error' => 'Invalid request']);
        }

        // Session: use server-side step only (player cannot skip stages).
        $currentStep = (int) ($_SESSION['pickpath_step'] ?? 0);
        $bet = (float) ($_SESSION['pickpath_bet'] ?? 0);
        $initialBet = (float) ($_SESSION['pickpath_initial_bet'] ?? $bet);

        // Reject if client tries to send a different step (no stage skip).
        if (isset($data['expected_step']) && (int) $data['expected_step'] !== $currentStep) {
            pickpathJson(['success' => false, 'error' => 'Invalid stage. Cannot skip stages.']);
        }

        // Outcome generated only after user clicks (not at game start).
        $bomb = random_int(1, 3);
        $step = $currentStep;

        if ($choice === $bomb) {
            $gameIdLog = $_SESSION['pickpath_game_id'] ?? '';
            $initialBetLog = (float) ($_SESSION['pickpath_initial_bet'] ?? $bet);
            $_SESSION['pickpath_status'] = 'lost';
            pickpathClearSession();
            // Log loss
            $history = file_exists(PICKPATH_GAMES_FILE) ? json_decode(file_get_contents(PICKPATH_GAMES_FILE), true) : [];
            if (!is_array($history)) $history = [];
            array_unshift($history, [
                'game_id' => $gameIdLog,
                'user_id' => $user['id'],
                'bet' => $initialBetLog,
                'result' => 'loss',
                'profit' => -$initialBetLog,
                'step' => $step,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            file_put_contents(PICKPATH_GAMES_FILE, json_encode(array_slice($history, 0, 5000), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            pickpathJson([
                'success' => true,
                'result' => 'bust',
                'profit' => -$bet,
                'balance' => getBalance($user['id']),
                'multiplier' => 0,
            ]);
        }

        // Advance exactly one stage (no skip).
        $step++;
        $multiplier = pickpathGetMultiplier($step);
        $_SESSION['pickpath_step'] = $step;
        $_SESSION['pickpath_current_step'] = $step;
        $_SESSION['pickpath_multiplier'] = $multiplier;
        $profit = round($initialBet * ($multiplier - 1), 2);

        // No balance credit here; only on cashout (double-spend prevention).
        pickpathJson([
            'success' => true,
            'result' => 'safe',
            'step' => $step,
            'multiplier' => $multiplier,
            'profit' => $profit,
            'balance' => getBalance($user['id']),
        ]);
    }

    case 'cashout': {
        if (!pickpathValidateActive()) {
            pickpathJson(['success' => false, 'error' => 'No active game. Start a new game.']);
        }
        $initialBet = (float) ($_SESSION['pickpath_initial_bet'] ?? $_SESSION['pickpath_bet'] ?? 0);
        $multiplier = (float) $_SESSION['pickpath_multiplier'];
        $step = (int) $_SESSION['pickpath_step'];
        $payout = round($initialBet * $multiplier, 2);
        $profit = round($payout - $initialBet, 2);
        $gameId = $_SESSION['pickpath_game_id'] ?? '';

        creditBalance($user['id'], $payout);
        pickpathClearSession();

        $history = file_exists(PICKPATH_GAMES_FILE) ? json_decode(file_get_contents(PICKPATH_GAMES_FILE), true) : [];
        if (!is_array($history)) $history = [];
        array_unshift($history, [
            'game_id' => $gameId,
            'user_id' => $user['id'],
            'bet' => $initialBet,
            'result' => 'win',
            'profit' => $profit,
            'payout' => $payout,
            'multiplier' => $multiplier,
            'step' => $step,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        file_put_contents(PICKPATH_GAMES_FILE, json_encode(array_slice($history, 0, 5000), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        pickpathJson([
            'success' => true,
            'result' => 'cashed_out',
            'multiplier' => $multiplier,
            'profit' => $profit,
            'payout' => $payout,
            'balance' => getBalance($user['id']),
        ]);
    }

    default:
        pickpathJson(['success' => false, 'error' => 'Invalid action']);
}
