<?php
/**
 * Guest PayGate payment – no login. User enters email + amount; payout address = admin wallet from Payment Methods.
 * POST email, amount → GET wallet.php → redirect to PayGate checkout.
 */
@ini_set('display_errors', 0);
header('X-Content-Type-Options: nosniff');

$jsonMode = !empty($_GET['json']);
if (!$jsonMode) {
    header('Content-Type: text/html; charset=utf-8');
}

function paygateGuestOut($data, $jsonMode) {
    if ($jsonMode) {
        header('Content-Type: application/json');
        echo json_encode($data);
    } else {
        $msg = is_array($data) ? ($data['error'] ?? $data['message'] ?? 'Error') : (string) $data;
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body><p>' . htmlspecialchars($msg) . '</p><p><a href="/paygate.html">Back</a></p></body></html>';
    }
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        paygateGuestOut(['success' => false, 'error' => 'Method not allowed'], $jsonMode);
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;

    if ($email === '') {
        paygateGuestOut(['success' => false, 'error' => 'Please enter your email.'], $jsonMode);
    }
    if ($amount < 1) {
        paygateGuestOut(['success' => false, 'error' => 'Minimum amount is $1.'], $jsonMode);
    }

    require_once __DIR__ . '/../config.php';

    $methods = readJSON(PAYMENT_METHODS_FILE);
    $pg = $methods['paygate'] ?? [];
    if (empty($pg['enabled']) || empty(trim($pg['payout_address'] ?? ''))) {
        paygateGuestOut(['success' => false, 'error' => 'PayGate is disabled or wallet not set in Admin → Payment Methods.'], $jsonMode);
    }

    $merchantWallet = trim($pg['payout_address']);
    if (strlen($merchantWallet) < 42 || strpos($merchantWallet, '0x') !== 0) {
        paygateGuestOut(['success' => false, 'error' => 'Invalid PayGate wallet in Admin. Use a valid Polygon address (0x...).'], $jsonMode);
    }

    $provider = 'hosted';
    $currency = 'USD';
    $userEmail = preg_replace('/[^a-zA-Z0-9._@+-]/', '', $email);
    if ($userEmail === '') {
        $userEmail = 'customer@mailss.com';
    }

    // Callback URL for PayGate (same format as HTML)
    $timestamp = (string) (time() * 1000);
    $random = (string) mt_rand(1000000, 9999999);
    $payoutTrackingId = 'https://paygate.to/payment-link/invoice.php?payment=' . $timestamp . '_' . $random;
    $callbackEncoded = urlencode($payoutTrackingId);

    $walletApiUrl = !empty(trim($pg['wallet_api_url'] ?? '')) ? trim($pg['wallet_api_url']) : 'https://api.paygate.to/control/wallet.php';
    $walletApiUrl = preg_replace('#\s+#', '', $walletApiUrl);
    if (preg_match('#^http://#i', $walletApiUrl)) {
        $walletApiUrl = 'https' . substr($walletApiUrl, 4);
    }
    $apiUrl = $walletApiUrl . '?address=' . urlencode($merchantWallet) . '&callback=' . $callbackEncoded;

    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    $response = '';
    $responseCode = 0;

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
            paygateGuestOut(['success' => false, 'error' => 'Could not reach PayGate: ' . $curlErr], $jsonMode);
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
            paygateGuestOut(['success' => false, 'error' => 'Could not reach PayGate. Enable cURL or allow_url_fopen.'], $jsonMode);
        }
    }

    $data = is_string($response) ? json_decode($response, true) : [];
    $data = is_array($data) ? $data : [];

    if ($responseCode < 200 || $responseCode >= 300) {
        $msg = $data['message'] ?? $data['error'] ?? $data['msg'] ?? 'PayGate wallet API error (HTTP ' . $responseCode . ').';
        paygateGuestOut(['success' => false, 'error' => $msg], $jsonMode);
    }

    if (empty($data['address_in'])) {
        $msg = $data['message'] ?? $data['error'] ?? 'Invalid wallet or PayGate API unavailable. Use a USDC (Polygon) wallet in Admin.';
        paygateGuestOut(['success' => false, 'error' => $msg], $jsonMode);
    }

    $addressIn = $data['address_in'];
    $amountStr = (string) round($amount, 2);
    $baseUrl = ($provider === 'hosted') ? 'https://checkout.paygate.to/pay.php' : 'https://checkout.paygate.to/process-payment.php';
    $paymentUrl = $baseUrl . '?address=' . urlencode($addressIn) . '&amount=' . urlencode($amountStr) . '&provider=' . urlencode($provider) . '&email=' . urlencode($userEmail) . '&currency=' . urlencode($currency);

    if ($jsonMode) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'payment_url' => $paymentUrl, 'payment_link' => $paymentUrl]);
        exit;
    }

    header('Location: ' . $paymentUrl);
    exit;

} catch (Throwable $e) {
    paygateGuestOut(['success' => false, 'error' => 'Server error. Please try again.'], $jsonMode);
}
