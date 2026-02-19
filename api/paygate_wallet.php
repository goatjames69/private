<?php
/**
 * Return PayGate payout wallet address only. Used by cards_deposit.html to get the wallet
 * in a separate client-side request so the actual wallet.php call to PayGate is 100% client-side.
 * No server-side call to PayGate is made here.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

$methods = readJSON(PAYMENT_METHODS_FILE);
$pg = $methods['paygate'] ?? [];
$wallet = '';
if (!empty($pg['enabled']) && !empty(trim($pg['payout_address'] ?? ''))) {
    $w = trim($pg['payout_address']);
    if (strlen($w) >= 42 && strpos($w, '0x') === 0) {
        $wallet = $w;
    }
}

echo json_encode(['success' => !empty($wallet), 'wallet' => $wallet]);
