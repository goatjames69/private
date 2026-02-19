<?php
http_response_code(200);
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$chatId = trim($_POST['chat_id'] ?? '');
$text = trim($_POST['message'] ?? '');
if ($chatId === '') {
    echo json_encode(['success' => false, 'error' => 'Missing chat id']);
    exit;
}

$chat = getSupportChatById($chatId);
if (!$chat) {
    echo json_encode(['success' => false, 'error' => 'Chat not found']);
    exit;
}

$from = null;
$senderId = null;
$senderContext = trim($_POST['sender_context'] ?? '');

// Use sender_context so: user page = user identity, admin page = admin/staff identity (avoids wrong identity when session has both)
if ($senderContext === 'admin' && (isAdmin() || isStaff())) {
    $from = isAdmin() ? 'admin' : 'staff';
    $senderId = isAdmin() ? 'admin' : ($_SESSION['staff_id'] ?? 'staff');
} elseif (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user && $chat['user_id'] === $user['id']) {
        $from = 'user';
        $senderId = $user['id'];
    }
}
if ($from === null && (isAdmin() || isStaff())) {
    $from = isAdmin() ? 'admin' : 'staff';
    $senderId = isAdmin() ? 'admin' : ($_SESSION['staff_id'] ?? 'staff');
}

http_response_code(200);
if ($from === null) {
    echo json_encode(['success' => false, 'error' => 'Not allowed']);
    exit;
}

if (($chat['status'] ?? '') === 'closed' && $from === 'user') {
    echo json_encode(['success' => false, 'error' => 'This chat is closed.']);
    exit;
}

$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($file['type'], $allowed)) {
        if (!file_exists(SUPPORT_UPLOADS_DIR)) mkdir(SUPPORT_UPLOADS_DIR, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $uid = $from === 'user' ? $chat['user_id'] : $senderId;
        $name = 'msg_' . $uid . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], SUPPORT_UPLOADS_DIR . '/' . $name)) {
            $imagePath = 'uploads/support/' . $name;
        }
    }
}

if ($text === '' && $imagePath === null) {
    echo json_encode(['success' => false, 'error' => 'Enter a message or attach an image.']);
    exit;
}

$msg = addSupportMessage($chatId, $from, $senderId, $text, $imagePath);
if ($msg) {
    realtimeEmit('support_message', [
        'chat_id' => $chatId,
        'user_id' => $chat['user_id'],
        'message' => $msg,
        'from' => $from
    ]);
}
echo json_encode(['success' => true, 'message' => $msg]);
exit;
