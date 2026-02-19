<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    http_response_code(403);
    echo json_encode(['error' => 'User not found']);
    exit;
}

echo json_encode([
    'user_id' => $user['id'],
    'balance' => (float) ($user['balance'] ?? 0)
]);
