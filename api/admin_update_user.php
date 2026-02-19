<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

requireStaffOrAdmin();
$allUsers = readJSON(USERS_FILE);
$action = trim($_POST['action'] ?? '');

if ($action === 'update_balance' && isAdmin()) {
    $userId = trim($_POST['user_id'] ?? '');
    $newBalance = floatval($_POST['balance'] ?? 0);
    if ($userId === '') {
        echo json_encode(['success' => false, 'error' => 'Missing user id']);
        exit;
    }
    foreach ($allUsers as &$u) {
        if ($u['id'] === $userId) {
            $u['balance'] = $newBalance;
            writeJSON(USERS_FILE, $allUsers);
            realtimeEmit('user_balance_updated', ['user_id' => $userId, 'balance' => $newBalance]);
            realtimeEmit('admin_user_updated', ['user_id' => $userId, 'balance' => $newBalance]);
            realtimeEmit('notification', ['user_id' => $userId, 'title' => 'Balance updated', 'body' => 'Your balance has been updated to $' . number_format($newBalance, 2)]);
            echo json_encode(['success' => true, 'message' => 'Balance updated successfully', 'balance' => $newBalance]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

if ($action === 'update_game_account' && (isAdmin() || canAccess('edit_game_credentials') || canAccess('game_accounts'))) {
    $userId = trim($_POST['user_id'] ?? '');
    $game = trim($_POST['game'] ?? '');
    $username = trim($_POST['game_username'] ?? '');
    $password = trim($_POST['game_password'] ?? '');
    if (empty($userId) || empty($game) || empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }
    foreach ($allUsers as &$u) {
        if ($u['id'] === $userId && !empty($u['game_accounts'])) {
            foreach ($u['game_accounts'] as &$acc) {
                if (($acc['game'] ?? '') === $game) {
                    $acc['username'] = $username;
                    $acc['password'] = $password;
                    writeJSON(USERS_FILE, $allUsers);
                    realtimeEmit('admin_user_updated', ['user_id' => $userId, 'game' => $game]);
                    echo json_encode(['success' => true, 'message' => 'Game account updated successfully']);
                    exit;
                }
            }
        }
    }
    echo json_encode(['success' => false, 'error' => 'User or game account not found']);
    exit;
}

if ($action === 'reset_password' && isAdmin()) {
    $userId = trim($_POST['user_id'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    if ($userId === '') {
        echo json_encode(['success' => false, 'error' => 'Missing user id']);
        exit;
    }
    if (strlen($newPassword) < 4) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters']);
        exit;
    }
    foreach ($allUsers as &$u) {
        if ($u['id'] === $userId) {
            $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            writeJSON(USERS_FILE, $allUsers);
            realtimeEmit('admin_user_updated', ['user_id' => $userId]);
            echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
