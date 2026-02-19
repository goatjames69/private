<?php
/**
 * Lightweight real-time poll – single request, no long connection.
 * Returns new events for this session since last_id. Use this instead of SSE to avoid server load.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

session_start();
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() && !isAdmin() && !isStaff()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized', 'events' => [], 'last_id' => 0]);
    exit;
}

$clientUserId = null;
$clientRole = 'user';
if (isAdmin()) {
    $clientRole = 'admin';
} elseif (isStaff()) {
    $clientRole = 'staff';
} elseif (isLoggedIn()) {
    $user = getCurrentUser();
    $clientUserId = $user ? $user['id'] : null;
}

$lastId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;

function realtimeEventMatchesClient($event, $payload, $clientUserId, $clientRole) {
    switch ($event) {
        case 'user_balance_updated':
            return $clientUserId && ($payload['user_id'] ?? '') === $clientUserId
                || in_array($clientRole, ['admin', 'staff'], true);
        case 'user_withdrawal_requested':
            return $clientUserId && ($payload['user_id'] ?? '') === $clientUserId
                || in_array($clientRole, ['admin', 'staff'], true);
        case 'support_message':
            return $clientUserId && ($payload['user_id'] ?? '') === $clientUserId
                || in_array($clientRole, ['admin', 'staff'], true);
        case 'support_chat_status':
            return $clientUserId && ($payload['user_id'] ?? '') === $clientUserId
                || in_array($clientRole, ['admin', 'staff'], true);
        case 'admin_user_updated':
            return $clientUserId && ($payload['user_id'] ?? '') === $clientUserId
                || in_array($clientRole, ['admin', 'staff'], true);
        case 'notification':
            if (!empty($payload['user_id'])) return $clientUserId && $payload['user_id'] === $clientUserId;
            if (!empty($payload['admin'])) return in_array($clientRole, ['admin', 'staff'], true);
            return true;
        default:
            return true;
    }
}

$queueFile = REALTIME_QUEUE_FILE;
$logFile = REALTIME_EVENT_LOG_FILE;
$maxLogSize = 200;

$fp = @fopen($logFile, 'c+');
if (!$fp) {
    echo json_encode(['events' => [], 'last_id' => $lastId], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    echo json_encode(['events' => [], 'last_id' => $lastId], JSON_UNESCAPED_UNICODE);
    exit;
}

$log = ['max_id' => 0, 'events' => []];
$raw = stream_get_contents($fp);
rewind($fp);
if ($raw) {
    $dec = json_decode($raw, true);
    if (is_array($dec)) $log = $dec;
}

$queue = [];
if (file_exists($queueFile)) {
    $qRaw = @file_get_contents($queueFile);
    if ($qRaw) $queue = json_decode($qRaw, true) ?: [];
}
foreach ($queue as $item) {
    $log['max_id']++;
    $log['events'][] = [
        'id' => $log['max_id'],
        'event' => $item['event'] ?? '',
        'payload' => $item['payload'] ?? [],
        'ts' => $item['ts'] ?? time()
    ];
}
if (!empty($queue)) {
    file_put_contents($queueFile, '[]');
}

if (count($log['events']) > $maxLogSize) {
    $log['events'] = array_slice($log['events'], -$maxLogSize);
}
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($log, JSON_UNESCAPED_UNICODE));
flock($fp, LOCK_UN);
fclose($fp);

// Build response from in-memory log
$out = [];
foreach ($log['events'] as $e) {
    $id = (int) ($e['id'] ?? 0);
    if ($id <= $lastId) continue;
    $ev = $e['event'] ?? '';
    $payload = $e['payload'] ?? [];
    if (realtimeEventMatchesClient($ev, $payload, $clientUserId, $clientRole)) {
        $out[] = ['id' => $id, 'type' => $ev, 'payload' => $payload];
    }
}

$newLastId = $lastId;
if (!empty($out)) {
    $newLastId = (int) end($out)['id'];
}

echo json_encode(['events' => $out, 'last_id' => $newLastId], JSON_UNESCAPED_UNICODE);
