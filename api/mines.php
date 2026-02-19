<?php
/**
 * Mines game API – provably fair 5x5 grid, 1–24 mines.
 * Actions: start, reveal, cashout, verify.
 * Server validates every move; balance deducted on start, credited on cashout/win.
 */
error_reporting(0);
ini_set('display_errors', 0);
session_start();

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin === '' && isset($_SERVER['HTTP_REFERER'])) {
    $ref = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME) . '://' . parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    if ($ref !== '://') $origin = $ref;
}
$allowOrigin = ($origin !== '' && preg_match('#^https?://[a-zA-Z0-9.-]+(:\d+)?$#', $origin)) ? $origin : '*';
header('Access-Control-Allow-Origin: ' . $allowOrigin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($allowOrigin !== '*') header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function minesJsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../config.php';
} catch (Throwable $e) {
    minesJsonResponse(['success' => false, 'error' => 'Server config error']);
}

try {
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$input = file_get_contents('php://input');
$data = [];
$ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($ct, 'application/x-www-form-urlencoded') !== false && $input !== false && $input !== '') {
    parse_str($input, $data);
}
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}
if (empty($data) && $input) {
    $dec = json_decode($input, true);
    if (is_array($dec)) $data = $dec;
}
if (!is_array($data)) $data = [];

$action = isset($data['action']) ? trim((string) $data['action']) : '';

$settings = getGameSettings();
$minBet = (float) ($settings['mines_min_bet'] ?? 0.10);
$maxBet = (float) ($settings['mines_max_bet'] ?? 500);
$totalTiles = 25;

function getActiveGames() {
    if (!file_exists(MINES_ACTIVE_FILE)) return [];
    $raw = @file_get_contents(MINES_ACTIVE_FILE);
    if ($raw === false) return [];
    $dec = json_decode($raw, true);
    return is_array($dec) ? $dec : [];
}

function saveActiveGames($games) {
    @file_put_contents(MINES_ACTIVE_FILE, json_encode($games, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getGamesHistory() {
    if (!file_exists(MINES_GAMES_FILE)) return [];
    $raw = @file_get_contents(MINES_GAMES_FILE);
    if ($raw === false) return [];
    $dec = json_decode($raw, true);
    return is_array($dec) ? $dec : [];
}

function saveGamesHistory($history) {
    $trimmed = array_slice($history, 0, 5000);
    @file_put_contents(MINES_GAMES_FILE, json_encode($trimmed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
        if (($u['id'] ?? '') !== $userId) continue;
        $u['balance'] = (float) ($u['balance'] ?? 0) + $amount;
        break;
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

switch ($action) {
    case 'start': {
        $bet = isset($data['bet']) ? (float) $data['bet'] : 0;
        $minesCount = isset($data['mines']) ? (int) $data['mines'] : 3;
        $clientSeed = isset($data['client_seed']) ? trim($data['client_seed']) : '';
        $nonce = isset($data['nonce']) ? (int) $data['nonce'] : 0;

        if ($bet < $minBet || $bet > $maxBet) {
            echo json_encode(['success' => false, 'error' => 'Bet must be between $' . number_format($minBet, 2) . ' and $' . number_format($maxBet, 2)]);
            exit;
        }
        if ($minesCount < 1 || $minesCount > 24) {
            echo json_encode(['success' => false, 'error' => 'Mines must be between 1 and 24']);
            exit;
        }
        if ($clientSeed === '') $clientSeed = bin2hex(random_bytes(8));

        if (!deductBalance($user['id'], $bet)) {
            echo json_encode(['success' => false, 'error' => 'Insufficient balance']);
            exit;
        }

        $serverSeed = minesGenerateServerSeed();
        $minePositions = minesGetMinePositions($serverSeed, $clientSeed, $nonce, $totalTiles, $minesCount);
        $gameId = generateId();
        $active = getActiveGames();
        $active[$gameId] = [
            'game_id' => $gameId,
            'user_id' => $user['id'],
            'bet' => $bet,
            'mines' => $minesCount,
            'server_seed' => $serverSeed,
            'server_seed_hash' => hash('sha256', $serverSeed),
            'client_seed' => $clientSeed,
            'nonce' => $nonce,
            'mine_positions' => $minePositions,
            'revealed' => [],
            'revealed_count' => 0,
            'status' => 'playing',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        saveActiveGames($active);

        echo json_encode([
            'success' => true,
            'game_id' => $gameId,
            'server_seed_hash' => $active[$gameId]['server_seed_hash'],
            'client_seed' => $clientSeed,
            'nonce' => $nonce,
            'mines' => $minesCount,
            'bet' => $bet,
            'multiplier' => 1.0,
            'balance' => getBalance($user['id']),
        ]);
        exit;
    }

    case 'reveal': {
        $gameId = isset($data['game_id']) ? trim($data['game_id']) : '';
        $tileIndex = isset($data['tile']) ? (int) $data['tile'] : -1;

        if ($gameId === '' || $tileIndex < 0 || $tileIndex >= $totalTiles) {
            echo json_encode(['success' => false, 'error' => 'Invalid game_id or tile']);
            exit;
        }

        $active = getActiveGames();
        if (!isset($active[$gameId])) {
            echo json_encode(['success' => false, 'error' => 'Game not found or ended']);
            exit;
        }
        $game = &$active[$gameId];
        if ($game['user_id'] !== $user['id']) {
            echo json_encode(['success' => false, 'error' => 'Not your game']);
            exit;
        }
        if ($game['status'] !== 'playing') {
            echo json_encode(['success' => false, 'error' => 'Game not in progress']);
            exit;
        }
        if (in_array($tileIndex, $game['revealed'], true)) {
            echo json_encode(['success' => false, 'error' => 'Tile already revealed']);
            exit;
        }

        $isMine = in_array($tileIndex, $game['mine_positions'], true);
        $game['revealed'][] = $tileIndex;
        $game['revealed_count'] = count($game['revealed']);

        if ($isMine) {
            $game['status'] = 'loss';
            saveActiveGames($active);
            $history = getGamesHistory();
            array_unshift($history, [
                'game_id' => $gameId,
                'user_id' => $user['id'],
                'bet' => $game['bet'],
                'mines' => $game['mines'],
                'result' => 'loss',
                'profit' => -$game['bet'],
                'tiles_revealed' => $game['revealed_count'],
                'server_seed' => $game['server_seed'],
                'client_seed' => $game['client_seed'],
                'nonce' => $game['nonce'],
                'mine_positions' => $game['mine_positions'],
                'created_at' => $game['created_at'],
            ]);
            saveGamesHistory($history);
            unset($active[$gameId]);
            saveActiveGames($active);

            echo json_encode([
                'success' => true,
                'type' => 'mine',
                'tile' => $tileIndex,
                'mine_positions' => $game['mine_positions'],
                'multiplier' => 0,
                'profit' => -$game['bet'],
                'balance' => getBalance($user['id']),
            ]);
            exit;
        }

        $revealedSafe = $game['revealed_count'];
        $multiplier = minesCalculateMultiplier($revealedSafe, $totalTiles, $game['mines']);
        saveActiveGames($active);

        echo json_encode([
            'success' => true,
            'type' => 'gem',
            'tile' => $tileIndex,
            'multiplier' => $multiplier,
            'revealed_safe' => $revealedSafe,
            'profit' => round($game['bet'] * ($multiplier - 1), 2),
            'balance' => getBalance($user['id']),
        ]);
        exit;
    }

    case 'cashout': {
        $gameId = isset($data['game_id']) ? trim($data['game_id']) : '';
        if ($gameId === '') {
            echo json_encode(['success' => false, 'error' => 'Invalid game_id']);
            exit;
        }

        $active = getActiveGames();
        if (!isset($active[$gameId])) {
            echo json_encode(['success' => false, 'error' => 'Game not found or ended']);
            exit;
        }
        $game = $active[$gameId];
        if ($game['user_id'] !== $user['id']) {
            echo json_encode(['success' => false, 'error' => 'Not your game']);
            exit;
        }
        if ($game['status'] !== 'playing') {
            echo json_encode(['success' => false, 'error' => 'Game not in progress']);
            exit;
        }

        $revealedSafe = count($game['revealed']);
        if ($revealedSafe === 0) {
            echo json_encode(['success' => false, 'error' => 'Reveal at least one tile to cash out']);
            exit;
        }

        $multiplier = minesCalculateMultiplier($revealedSafe, $totalTiles, $game['mines']);
        $profit = round($game['bet'] * ($multiplier - 1), 2);
        $payout = $game['bet'] + $profit;
        creditBalance($user['id'], $payout);

        $history = getGamesHistory();
        array_unshift($history, [
            'game_id' => $gameId,
            'user_id' => $user['id'],
            'bet' => $game['bet'],
            'mines' => $game['mines'],
            'result' => 'win',
            'profit' => $profit,
            'payout' => $payout,
            'multiplier' => $multiplier,
            'tiles_revealed' => $revealedSafe,
            'server_seed' => $game['server_seed'],
            'client_seed' => $game['client_seed'],
            'nonce' => $game['nonce'],
            'mine_positions' => $game['mine_positions'],
            'created_at' => $game['created_at'],
        ]);
        saveGamesHistory($history);
        unset($active[$gameId]);
        saveActiveGames($active);

        echo json_encode([
            'success' => true,
            'type' => 'cashout',
            'multiplier' => $multiplier,
            'profit' => $profit,
            'payout' => $payout,
            'balance' => getBalance($user['id']),
        ]);
        exit;
    }

    case 'verify': {
        $gameId = isset($data['game_id']) ? trim($data['game_id']) : (isset($_GET['game_id']) ? trim($_GET['game_id']) : '');
        if ($gameId === '') {
            echo json_encode(['success' => false, 'error' => 'game_id required']);
            exit;
        }
        $history = getGamesHistory();
        foreach ($history as $g) {
            if (($g['game_id'] ?? '') === $gameId && ($g['user_id'] ?? '') === $user['id']) {
                echo json_encode([
                    'success' => true,
                    'game_id' => $gameId,
                    'server_seed' => $g['server_seed'] ?? '',
                    'client_seed' => $g['client_seed'] ?? '',
                    'nonce' => $g['nonce'] ?? 0,
                    'mine_positions' => $g['mine_positions'] ?? [],
                    'result' => $g['result'] ?? '',
                    'bet' => $g['bet'] ?? 0,
                    'profit' => $g['profit'] ?? 0,
                ]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Game not found']);
        exit;
    }

    case 'history': {
        $limit = isset($data['limit']) ? min(100, max(1, (int) $data['limit'])) : 50;
        $history = getGamesHistory();
        $userGames = [];
        foreach ($history as $g) {
            if (($g['user_id'] ?? '') === $user['id']) {
                $userGames[] = [
                    'game_id' => $g['game_id'] ?? '',
                    'bet' => (float) ($g['bet'] ?? 0),
                    'mines' => (int) ($g['mines'] ?? 0),
                    'result' => $g['result'] ?? '',
                    'profit' => (float) ($g['profit'] ?? 0),
                    'tiles_revealed' => (int) ($g['tiles_revealed'] ?? 0),
                    'multiplier' => isset($g['multiplier']) ? (float) $g['multiplier'] : null,
                    'payout' => isset($g['payout']) ? (float) $g['payout'] : null,
                    'created_at' => $g['created_at'] ?? '',
                ];
                if (count($userGames) >= $limit) break;
            }
        }
        echo json_encode(['success' => true, 'games' => $userGames]);
        exit;
    }

    default:
        minesJsonResponse(['success' => false, 'error' => 'Invalid action']);
}
} catch (Throwable $e) {
    minesJsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
