<?php
http_response_code(200);
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

$chatId = trim($_GET['chat_id'] ?? '');
if ($chatId === '') {
    echo json_encode(['success' => false, 'error' => 'Missing chat id']);
    exit;
}

$chat = getSupportChatById($chatId);
if (!$chat) {
    echo json_encode(['success' => false, 'error' => 'Chat not found']);
    exit;
}

$allowed = false;
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user && $chat['user_id'] === $user['id']) $allowed = true;
}
if (!$allowed && (isAdmin() || isStaff())) $allowed = true;

if (!$allowed) {
    echo json_encode(['success' => false, 'error' => 'Not allowed']);
    exit;
}

$after = trim($_GET['after'] ?? '');
$messages = $chat['messages'] ?? [];
if ($after !== '') {
    $found = false;
    $messages = array_values(array_filter($messages, function ($m) use ($after, &$found) {
        if (($m['id'] ?? '') === $after) $found = true;
        return $found && ($m['id'] ?? '') !== $after;
    }));
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'status' => $chat['status'] ?? 'open',
    'last_seen_by_user_at' => $chat['last_seen_by_user_at'] ?? null,
    'last_seen_by_staff_at' => $chat['last_seen_by_staff_at'] ?? null
]);
exit;
