<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isAdmin() && !isStaff()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not allowed']);
    exit;
}

$chatId = trim($_POST['chat_id'] ?? '');
$action = trim($_POST['action'] ?? '');
if ($chatId === '' || !in_array($action, ['close', 'reopen'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$chat = getSupportChatById($chatId);
if (!$chat) {
    echo json_encode(['success' => false, 'error' => 'Chat not found']);
    exit;
}

if ($action === 'close') {
    closeSupportChat($chatId);
    realtimeEmit('support_chat_status', ['chat_id' => $chatId, 'user_id' => $chat['user_id'] ?? '', 'status' => 'closed']);
    echo json_encode(['success' => true, 'status' => 'closed']);
} else {
    reopenSupportChat($chatId);
    realtimeEmit('support_chat_status', ['chat_id' => $chatId, 'user_id' => $chat['user_id'] ?? '', 'status' => 'open']);
    echo json_encode(['success' => true, 'status' => 'open']);
}
exit;
