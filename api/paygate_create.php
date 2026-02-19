<?php
/**
 * Create a PayGate.to payment link – matches the working HTML flow.
 * Uses GET to https://api.paygate.to/control/wallet.php (not POST to payment/create),
 * then builds checkout link: https://checkout.paygate.to/pay.php?address=...&amount=...&provider=hosted&email=...&currency=USD
 */
@ini_set('display_errors', 0);
header('X-Content-Type-Options: nosniff');

$redirectMode = ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['redirect'], $_GET['amount']));
if (!$redirectMode) {
    header('Content-Type: application/json');
}

function paygateJsonResponse($data) {
    echo json_encode($data);
    exit;
}

try {
session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$redirectMode) {
    http_response_code(405);
    paygateJsonResponse(['success' => false, 'error' => 'Method not allowed']);
}

requireLogin();
$user = getCurrentUser();
if (!$user) {
    if ($redirectMode) { header('Location: /deposit.php?error=login'); exit; }
    http_response_code(403);
    paygateJsonResponse(['success' => false, 'error' => 'Not logged in']);
}

$amount = $redirectMode ? (float) $_GET['amount'] : (isset($_POST['amount']) ? (float) $_POST['amount'] : 0);
if ($amount < 1) {
    if ($redirectMode) { header('Location: /deposit.php?error=amount'); exit; }
    paygateJsonResponse(['success' => false, 'error' => 'Minimum amount is $1']);
}

$methods = readJSON(PAYMENT_METHODS_FILE);
$pg = $methods['paygate'] ?? [];
if (empty($pg['enabled']) || empty(trim($pg['payout_address'] ?? ''))) {
    if ($redirectMode) { header('Location: /deposit.php?error=' . urlencode('PayGate is disabled or wallet not set in Admin.')); exit; }
    paygateJsonResponse(['success' => false, 'error' => 'PayGate.to is disabled or USDC Polygon wallet not set in Admin → Payment Methods']);
}

$merchantWallet = trim($pg['payout_address']);
if (strlen($merchantWallet) < 42 || strpos($merchantWallet, '0x') !== 0) {
    if ($redirectMode) { header('Location: /deposit.php?error=' . urlencode('Invalid PayGate wallet in Admin.')); exit; }
    paygateJsonResponse(['success' => false, 'error' => 'Invalid USDC Polygon wallet. In Admin → Payment Methods, set a valid Polygon wallet address (starts with 0x, 42 characters). Create one e.g. in Trust Wallet.']);
}
// Pass address exactly as in example.html – no normalization (PayGate may expect original format)
$orderId = 'pg_' . $user['id'] . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 8);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$callbackUrl = $baseUrl . '/api/paygate_callback.php';
$successUrl = !empty(trim($pg['success_url'] ?? '')) ? trim($pg['success_url']) : $baseUrl . '/deposit.php?paygate=success&order_id=' . urlencode($orderId);
$cancelUrl = !empty(trim($pg['cancel_url'] ?? '')) ? trim($pg['cancel_url']) : $baseUrl . '/deposit.php?paygate=cancel';

// Save payment as pending before calling gateway
$payments = readJSON(PAYMENTS_FILE);
$payment = [
    'id' => generateId(),
    'user_id' => $user['id'],
    'amount' => $amount,
    'method' => 'paygate',
    'status' => 'pending',
    'date' => date('Y-m-d H:i:s'),
    'payment_info' => $orderId,
    'paygate_order_id' => $orderId
];
$payments[] = $payment;
writeJSON(PAYMENTS_FILE, $payments);

$username = isset($user['username']) && trim($user['username']) !== '' ? trim($user['username']) : 'user' . $user['id'];
$userEmail = preg_replace('/[^a-zA-Z0-9._-]/', '', $username) . '@mailss.com';
$provider = 'hosted';
$currency = 'USD';

// 1. Callback URL for PayGate (matches HTML: payment={Timestamp}_{Random})
$timestamp = (string) (time() * 1000);
$random = (string) mt_rand(1000000, 9999999);
$payoutTrackingId = 'https://paygate.to/payment-link/invoice.php?payment=' . $timestamp . '_' . $random;
$callbackEncoded = urlencode($payoutTrackingId);

// 2. GET wallet.php (same as HTML – not POST to payment/create)
$walletApiUrl = !empty(trim($pg['wallet_api_url'] ?? '')) ? trim($pg['wallet_api_url']) : 'https://api.paygate.to/control/wallet.php';
$walletApiUrl = preg_replace('#\s+#', '', $walletApiUrl);
if (preg_match('#^http://#i', $walletApiUrl)) {
    $walletApiUrl = 'https' . substr($walletApiUrl, 4);
}
$apiUrl = $walletApiUrl . '?address=' . urlencode($merchantWallet) . '&callback=' . $callbackEncoded;

$response = '';
$responseCode = 0;

// Mimic a real browser so PayGate does not flag the session as "bot" (avoids "Provided wallet address is not allowed" on checkout)
$userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    $responseCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($response === false && $curlErr !== '') {
        if ($redirectMode) { header('Location: /deposit.php?error=' . urlencode('Could not reach PayGate.')); exit; }
        paygateJsonResponse(['success' => false, 'error' => 'Could not reach PayGate.to: ' . $curlErr]);
    }
} else {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => "Accept: application/json\r\nUser-Agent: {$userAgent}\r\n"
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
    ]);
    $response = @file_get_contents($apiUrl, false, $ctx);
    if (isset($http_response_header) && !empty($http_response_header)) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $m);
        $responseCode = (int) ($m[1] ?? 0);
    }
    if ($response === false) {
        if ($redirectMode) { header('Location: /deposit.php?error=' . urlencode('Could not reach PayGate.')); exit; }
        paygateJsonResponse(['success' => false, 'error' => 'Could not reach PayGate.to. Enable cURL or allow_url_fopen for HTTPS.']);
    }
}

if ($response === false || $response === '') {
    $response = '{}';
}

$data = is_string($response) ? json_decode($response, true) : [];
$data = is_array($data) ? $data : [];

function paygateExtractError($data, $rawResponse = '') {
    if (!empty($data) && is_array($data)) {
        $msg = $data['message'] ?? $data['msg'] ?? $data['detail'] ?? $data['reason'] ?? $data['description'] ?? '';
        if ($msg !== '' && is_string($msg)) return $msg;
        $err = $data['error'] ?? null;
        if (is_string($err) && $err !== '') return $err;
        if (is_array($err)) {
            $m = $err['message'] ?? $err['msg'] ?? $err['detail'] ?? '';
            if ($m !== '' && is_string($m)) return $m;
        }
    }
    if ($rawResponse !== '' && is_string($rawResponse)) {
        $clean = preg_replace('/<[^>]+>/', '', $rawResponse);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));
        if (strlen($clean) > 200) $clean = substr($clean, 0, 197) . '...';
        if ($clean !== '') return 'PayGate.to: ' . $clean;
    }
    return '';
}

if ($responseCode < 200 || $responseCode >= 300) {
    $gatewayMsg = paygateExtractError($data, is_string($response) ? $response : '');
    $msg = $gatewayMsg !== '' ? $gatewayMsg : 'PayGate wallet API error (HTTP ' . $responseCode . '). Check your USDC Polygon wallet in Admin → Payment Methods.';
    if ($redirectMode) { header('Location: /deposit.php?error=' . urlencode($msg)); exit; }
    paygateJsonResponse(['success' => false, 'error' => $msg]);
}

if (empty($data['address_in'])) {
    $gatewayMsg = paygateExtractError($data, is_string($response) ? $response : '');
    $msg = $gatewayMsg !== '' ? $gatewayMsg : 'Invalid wallet or PayGate API unavailable. Use a USDC (Polygon) compatible wallet.';
    if ($redirectMode) { header('Location: /deposit.php?error=' . urlencode($msg)); exit; }
    paygateJsonResponse(['success' => false, 'error' => $msg]);
}

// 3. Build final payment link (same as HTML: checkout.paygate.to/pay.php for hosted)
$addressIn = $data['address_in'];
$amountStr = (string) round($amount, 2);
$baseUrl = ($provider === 'hosted') ? 'https://checkout.paygate.to/pay.php' : 'https://checkout.paygate.to/process-payment.php';
$paymentUrl = $baseUrl . '?address=' . urlencode($addressIn) . '&amount=' . urlencode($amountStr) . '&provider=' . urlencode($provider) . '&email=' . urlencode($userEmail) . '&currency=' . urlencode($currency);

if ($redirectMode) { header('Location: ' . $paymentUrl); exit; }
paygateJsonResponse([
    'success' => true,
    'payment_url' => $paymentUrl,
    'payment_link' => $paymentUrl,
    'order_id' => $orderId
]);

} catch (Throwable $e) {
    if (!empty($redirectMode)) { header('Location: /deposit.php?error=' . urlencode('Server error. Please try again.')); exit; }
    paygateJsonResponse([
        'success' => false,
        'error' => 'Server error. Please try again or contact support.'
    ]);
}
