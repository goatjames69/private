<?php
/**
 * Admin: approve or reject a PayGate/card deposit tx.
 * POST: id (tx id), action (approve|reject).
 * On approve: updates status and adds amount to user balance (find user by email).
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

requireStaffOrAdmin();
if (!canAccess('payments')) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$id = trim($_POST['id'] ?? '');
$action = strtolower(trim($_POST['action'] ?? ''));

if ($id === '' || !in_array($action, ['approve', 'reject'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid id or action']);
    exit;
}

if (!defined('PAYGATETX_FILE') || !file_exists(PAYGATETX_FILE)) {
    echo json_encode(['success' => false, 'error' => 'No PayGate log file']);
    exit;
}

$list = json_decode(file_get_contents(PAYGATETX_FILE), true);
if (!is_array($list)) $list = [];

// Backfill id for old entries that don't have one
$changed = false;
foreach ($list as $i => $tx) {
    if (empty($tx['id'])) {
        $list[$i]['id'] = 'pg_' . $i . '_' . substr(md5(($tx['email'] ?? '') . ($tx['server_time'] ?? '')), 0, 8);
        $list[$i]['status'] = $tx['status'] ?? 'pending';
        $changed = true;
    }
}
if ($changed) {
    writeJSON(PAYGATETX_FILE, $list);
}

$idx = null;
foreach ($list as $i => $tx) {
    if (($tx['id'] ?? '') === $id) {
        $idx = $i;
        break;
    }
}

if ($idx === null) {
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

$tx = &$list[$idx];
$currentStatus = $tx['status'] ?? 'pending';
if ($currentStatus !== 'pending') {
    echo json_encode(['success' => false, 'error' => 'Already ' . $currentStatus]);
    exit;
}

if ($action === 'reject') {
    $tx['status'] = 'rejected';
    writeJSON(PAYGATETX_FILE, $list);
    echo json_encode(['success' => true, 'status' => 'rejected']);
    exit;
}

// approve: update status and credit user balance
$tx['status'] = 'approved';
$amount = (float)($tx['amount'] ?? 0);
$email = trim($tx['email'] ?? '');

if ($amount <= 0 || $email === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid amount or email']);
    exit;
}

$users = readJSON(USERS_FILE);
$userIdx = null;
foreach ($users as $i => $u) {
    if (strcasecmp(trim($u['email'] ?? ''), $email) === 0) {
        $userIdx = $i;
        break;
    }
}

if ($userIdx === null) {
    echo json_encode(['success' => false, 'error' => 'User not found for email: ' . $email]);
    exit;
}

$users[$userIdx]['balance'] = (float)($users[$userIdx]['balance'] ?? 0) + $amount;
// Referral: first deposit -> 50% bonus to referrer (referred user gets no bonus)
$depositingUser = &$users[$userIdx];
if (!empty($depositingUser['referred_by']) && empty($depositingUser['referral_bonus_paid'])) {
    $depositingUser['referral_bonus_paid'] = true;
    $bonus = $amount * 0.5;
    $referrerId = $depositingUser['referred_by'];
    $referredUsername = $depositingUser['username'] ?? '';
    $referredEmail = trim($depositingUser['email'] ?? '');
    foreach ($users as &$refUser) {
        if (($refUser['id'] ?? '') === $referrerId) {
            $refUser['balance'] = (float)($refUser['balance'] ?? 0) + $bonus;
            if (!isset($refUser['referral_bonus_history']) || !is_array($refUser['referral_bonus_history'])) {
                $refUser['referral_bonus_history'] = [];
            }
            $refUser['referral_bonus_history'][] = [
                'amount' => $bonus,
                'date' => date('Y-m-d H:i:s'),
                'referred_username' => $referredUsername,
                'referred_email' => $referredEmail,
                'source' => 'paygate'
            ];
            break;
        }
    }
}
writeJSON(USERS_FILE, $users);
writeJSON(PAYGATETX_FILE, $list);

echo json_encode(['success' => true, 'status' => 'approved']);
