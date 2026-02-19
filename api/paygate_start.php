<?php
/**
 * Save card deposit transaction details only (no redirect, no PayGate URL).
 * Match user by email, save to users.json card_transactions and payments.json.
 * No auto-deposit; admin approves manually if needed.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
if ($amount < 1) {
    echo json_encode(['success' => false, 'error' => 'Minimum amount is $1']);
    exit;
}
if ($email === '') {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

$methods = readJSON(PAYMENT_METHODS_FILE);
$pg = $methods['paygate'] ?? [];
$paymentLink = '';
$walletAddress = '';
if (!empty($pg['enabled']) && !empty(trim($pg['payout_address'] ?? ''))) {
    $addr = trim($pg['payout_address']);
    if (strlen($addr) >= 42 && strpos($addr, '0x') === 0) {
        $walletAddress = $addr;
        $paymentLink = 'https://paygate.to/payment-link/?' . http_build_query([
            'address'  => $addr,
            'amount'   => $amount,
            'email'    => $email,
            'currency' => 'USD',
        ]);
    }
}

$users = readJSON(USERS_FILE);
$matchedUser = null;
foreach ($users as $u) {
    $ue = trim($u['email'] ?? '');
    if ($ue !== '' && strtolower($ue) === strtolower($email)) {
        $matchedUser = $u;
        break;
    }
}

if ($matchedUser === null) {
    echo json_encode(['success' => false, 'error' => 'No account found with this email. Register or use the email linked to your account.']);
    exit;
}

$userId = $matchedUser['id'];
$orderId = 'pg_' . preg_replace('/[^a-zA-Z0-9]/', '_', $userId) . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 8);
$time = date('Y-m-d H:i:s');

$payments = readJSON(PAYMENTS_FILE);
$payments[] = [
    'id' => generateId(),
    'user_id' => $userId,
    'amount' => $amount,
    'method' => 'paygate',
    'status' => 'pending',
    'date' => $time,
    'payment_info' => $orderId,
    'paygate_order_id' => $orderId,
    'payer_email' => $email,
];
writeJSON(PAYMENTS_FILE, $payments);

foreach ($users as &$u) {
    if (($u['id'] ?? '') !== $userId) {
        continue;
    }
    if (!isset($u['card_transactions']) || !is_array($u['card_transactions'])) {
        $u['card_transactions'] = [];
    }
    $u['card_transactions'][] = [
        'transaction_id' => $orderId,
        'email' => $email,
        'amount' => $amount,
        'time' => $time,
        'status' => 'pending',
    ];
    break;
}
unset($u);
writeJSON(USERS_FILE, $users);

echo json_encode([
    'success'         => true,
    'order_id'        => $orderId,
    'payment_link'    => $paymentLink,
    'wallet_address' => $walletAddress,
]);
