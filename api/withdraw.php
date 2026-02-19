<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

requireLogin();
$user = getCurrentUser();
if (!$user) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$userQrDir = __DIR__ . '/uploads/user_qr_codes';
if (!file_exists($userQrDir)) mkdir($userQrDir, 0755, true);

$mainWithdrawLimits = getGameSettings();
$minMainWithdrawal = (float) ($mainWithdrawLimits['min_main_withdrawal'] ?? 100);
$maxMainWithdrawal = (float) ($mainWithdrawLimits['max_main_withdrawal'] ?? 500);

$amount = floatval($_POST['amount'] ?? 0);
$method = trim($_POST['method'] ?? '');
$account_info = trim($_POST['account_info'] ?? '');
$qr_code_path = null;

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Amount must be greater than 0']);
    exit;
}
if ($amount < $minMainWithdrawal) {
    echo json_encode(['success' => false, 'error' => 'Minimum withdrawal from main balance is $' . number_format($minMainWithdrawal, 2) . '.']);
    exit;
}
if ($amount > $maxMainWithdrawal) {
    echo json_encode(['success' => false, 'error' => 'Maximum withdrawal from main balance is $' . number_format($maxMainWithdrawal, 2) . '.']);
    exit;
}
if ($amount > $user['balance']) {
    echo json_encode(['success' => false, 'error' => 'Insufficient balance']);
    exit;
}
if (!in_array($method, ['chime', 'paypal'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid withdrawal method']);
    exit;
}
if (empty($account_info)) {
    echo json_encode(['success' => false, 'error' => 'Account information is required']);
    exit;
}

if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['qr_code'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($file['type'], $allowedTypes)) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'withdrawal_' . $user['id'] . '_' . time() . '.' . $extension;
        $filepath = $userQrDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $qr_code_path = 'uploads/user_qr_codes/' . $filename;
        }
    }
}

$withdrawals = readJSON(WITHDRAWAL_REQUESTS_FILE);
$withdrawal = [
    'id' => generateId(),
    'user_id' => $user['id'],
    'amount' => $amount,
    'method' => $method,
    'account_info' => $account_info,
    'status' => 'pending',
    'date' => date('Y-m-d H:i:s'),
    'qr_code' => $qr_code_path
];
$withdrawals[] = $withdrawal;
writeJSON(WITHDRAWAL_REQUESTS_FILE, $withdrawals);

realtimeEmit('user_withdrawal_requested', [
    'user_id' => $user['id'],
    'amount' => $amount,
    'request_id' => $withdrawal['id']
]);
realtimeEmit('notification', ['admin' => true, 'title' => 'New withdrawal request', 'body' => 'User requested $' . number_format($amount, 2) . ' (' . $method . ')']);

echo json_encode([
    'success' => true,
    'message' => 'Withdrawal request submitted successfully! Admin will review and process it.',
    'balance' => $user['balance']
]);
