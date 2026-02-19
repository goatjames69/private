<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

http_response_code(200);
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$reason = trim($_POST['reason'] ?? '');
$message = trim($_POST['message'] ?? '');
$reasons = getSupportReasons();
if ($reason === '' || !in_array($reason, $reasons)) {
    echo json_encode(['success' => false, 'error' => 'Please select a valid reason.']);
    exit;
}

$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($file['type'], $allowed)) {
        if (!file_exists(SUPPORT_UPLOADS_DIR)) mkdir(SUPPORT_UPLOADS_DIR, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $name = 'sup_' . $user['id'] . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], SUPPORT_UPLOADS_DIR . '/' . $name)) {
            $imagePath = 'uploads/support/' . $name;
        }
    }
}

$chat = createSupportChat($user['id'], $reason, $message, $imagePath);
echo json_encode(['success' => true, 'chat' => $chat]);
exit;
