<?php
/**
 * PayGate.to webhook – called when payment is completed.
 * Verify signature if webhook_secret is set; then approve payment and credit user balance.
 * See: https://documenter.getpostman.com/view/14826208/2sA3Bj9aBi
 */
session_start();
require_once __DIR__ . '/../config.php';

// Accept POST (and optionally GET for some gateways)
$input = file_get_contents('php://input');
$data = $input ? json_decode($input, true) : [];
if (empty($data) && !empty($_GET)) {
    $data = $_GET;
}
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

$orderId = $data['order_id'] ?? $data['orderId'] ?? $data['reference'] ?? $data['id'] ?? '';
$status = strtolower(trim($data['status'] ?? $data['payment_status'] ?? ''));
$amount = isset($data['amount']) ? (float) $data['amount'] : (isset($data['total']) ? (float) $data['total'] : 0);
$txHash = $data['tx_hash'] ?? $data['transaction_id'] ?? $data['txhash'] ?? '';

if (empty($orderId)) {
    http_response_code(400);
    exit('Missing order_id');
}

$methods = readJSON(PAYMENT_METHODS_FILE);
$pg = $methods['paygate'] ?? [];
if (!empty($pg['webhook_secret'])) {
    $sig = $data['signature'] ?? $data['hmac'] ?? $data['hash'] ?? $_SERVER['HTTP_X_PAYGATE_SIGNATURE'] ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
    $expected = hash_hmac('sha256', $input ?: http_build_query($data), $pg['webhook_secret']);
    if (!hash_equals($expected, $sig) && $sig !== $expected) {
        http_response_code(401);
        exit('Invalid signature');
    }
}

$payments = readJSON(PAYMENTS_FILE);
$users = readJSON(USERS_FILE);
$found = false;

function updateUserCardTransactionCallback(&$users, $userId, $orderId, $newStatus, $callbackTime) {
    foreach ($users as &$u) {
        if (($u['id'] ?? '') !== $userId) continue;
        if (!empty($u['card_transactions']) && is_array($u['card_transactions'])) {
            foreach ($u['card_transactions'] as &$tx) {
                if (($tx['transaction_id'] ?? '') === $orderId) {
                    $tx['status'] = $newStatus;
                    $tx['updated_at'] = $callbackTime;
                    break;
                }
            }
            unset($tx);
        }
        break;
    }
    unset($u);
}

foreach ($payments as &$p) {
    if (($p['paygate_order_id'] ?? $p['payment_info'] ?? '') !== $orderId) {
        continue;
    }
    if (($p['status'] ?? '') === 'approved') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => 'Already processed']);
        exit;
    }
    $ok = in_array($status, ['paid', 'completed', 'success', 'approved'], true);
    if (!$ok && $status === '') {
        $ok = !empty($data['paid']) || !empty($data['success']);
    }
    $failed = in_array($status, ['failed', 'cancelled', 'canceled', 'expired', 'rejected'], true);
    $userId = $p['user_id'] ?? '';
    $amt = (float) ($p['amount'] ?? $amount);
    $callbackTime = date('Y-m-d H:i:s');

    if ($failed) {
        $p['status'] = 'rejected';
        $p['paygate_callback_at'] = $callbackTime;
        $p['paygate_failed_reason'] = $status;
        $found = true;
        updateUserCardTransactionCallback($users, $userId, $orderId, 'failed', $callbackTime);
        writeJSON(USERS_FILE, $users);
        writeJSON(PAYMENTS_FILE, $payments);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'processed' => true, 'status' => 'rejected']);
        exit;
    }

    if ($ok) {
        $p['status'] = 'approved';
        $p['paygate_callback_at'] = $callbackTime;
        if ($txHash !== '') {
            $p['paygate_tx_hash'] = $txHash;
        }
        $found = true;
        // Update transaction status in users.json only – no auto-deposit (admin approves manually)
        foreach ($users as &$u) {
            if (($u['id'] ?? '') === $userId) {
                if (!empty($u['card_transactions']) && is_array($u['card_transactions'])) {
                    foreach ($u['card_transactions'] as &$tx) {
                        if (($tx['transaction_id'] ?? '') === $orderId) {
                            $tx['status'] = 'success';
                            $tx['updated_at'] = $callbackTime;
                            break;
                        }
                    }
                    unset($tx);
                }
                break;
            }
        }
        unset($u);
        writeJSON(USERS_FILE, $users);
        break;
    }
}
unset($p);

writeJSON(PAYMENTS_FILE, $payments);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'processed' => $found]);
