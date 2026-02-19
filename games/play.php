<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$slug = isset($_GET['g']) ? trim($_GET['g']) : '';
if ($slug === 'mines') {
    header('Location: /games/mines.php');
    exit;
}
if ($slug === 'pickapath') {
    header('Location: /games/pickpath.php');
    exit;
}
$game = $slug !== '' ? getGameBySlug($slug) : null;

if ($game === null) {
    header('Location: /dashboard.php');
    exit;
}

$gameName = $game['name'] ?? '';
$gameIcon = '🎮';
require __DIR__ . '/_game_page.php';
