<?php
/**
 * Log card deposit request (email, amount, tracking). Called by cards_deposit.html via fetch.
 * No auth required; same-origin only. Appends to json/cards_deposit_log.json.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
http_response_code(200);

$input = file_get_contents('php://input');
$data = [];
if ($input && preg_match('/^application\/json/i', $_SERVER['CONTENT_TYPE'] ?? '')) {
    $data = json_decode($input, true) ?: [];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $data = $_POST;
}
if (!is_array($data)) $data = [];

$email = isset($data['email']) ? trim((string) $data['email']) : '';
$amount = isset($data['amount']) ? trim((string) $data['amount']) : '';
$tracking = isset($data['tracking']) ? trim((string) $data['tracking']) : '';

$logFile = __DIR__ . '/../json/cards_deposit_log.json';
$log = [];
if (file_exists($logFile)) {
    $raw = file_get_contents($logFile);
    $log = json_decode($raw, true);
}
if (!is_array($log)) $log = [];

$log[] = [
    'email' => $email,
    'amount' => $amount,
    'tracking' => $tracking,
    'created_at' => date('Y-m-d H:i:s'),
];

file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true]);
exit;
