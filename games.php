<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (empty(trim($user['email'] ?? ''))) {
    header('Location: /profile.php?add_email=1');
    exit;
}
$allGames = getGamesConfig();
$ourGames = [];
$providerGames = [];
foreach ($allGames as $game) {
    $arr = is_array($game) ? $game : ['name' => $game, 'slug' => strtolower(str_replace(' ', '', $game)), 'our_game' => false];
    if (!empty($arr['our_game'])) {
        $ourGames[] = $arr;
    } else {
        $providerGames[] = $arr;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Games - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/realtime.css">
    <link rel="stylesheet" href="assets/css/user-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="user-dashboard">
    <div class="ud-container">
        <header class="ud-header ud-games-header">
            <h1><i class="fas fa-gamepad"></i> Games</h1>
            <p class="ud-greeting">Choose a game to play</p>
        </header>

        <?php if (!empty($ourGames)): ?>
        <section class="ud-card ud-games-only">
            <h2 class="ud-games-section-title"><i class="fas fa-star"></i> Our Games</h2>
            <div class="ud-games-grid">
                <?php foreach ($ourGames as $game):
                    $gameName = $game['name'] ?? '';
                    $gameSlug = $game['slug'] ?? strtolower(str_replace(' ', '', $gameName));
                    $gameAccount = null;
                    foreach ($user['game_accounts'] ?? [] as $acc) {
                        if (($acc['game'] ?? '') === $gameName) { $gameAccount = $acc; break; }
                    }
                    $logoPath = getGameLogo($game);
                ?>
                    <a href="/games/play.php?g=<?= htmlspecialchars($gameSlug) ?>" class="ud-game-card">
                        <div class="ud-game-icon">
                            <?php if ($logoPath): ?>
                                <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($gameName) ?>">
                            <?php else: ?>
                                <i class="fas fa-gamepad" style="font-size: 28px; color: var(--text-muted);"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ud-game-name"><?= htmlspecialchars($gameName) ?></div>
                        <?php if ($gameAccount): ?><div class="ud-game-badge"><i class="fas fa-check-circle"></i> Ready</div><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($providerGames)): ?>
        <section class="ud-card ud-games-only">
            <h2 class="ud-games-section-title"><i class="fas fa-puzzle-piece"></i> Provider Games</h2>
            <div class="ud-games-grid">
                <?php foreach ($providerGames as $game):
                    $gameName = $game['name'] ?? '';
                    $gameSlug = $game['slug'] ?? strtolower(str_replace(' ', '', $gameName));
                    $gameAccount = null;
                    foreach ($user['game_accounts'] ?? [] as $acc) {
                        if (($acc['game'] ?? '') === $gameName) { $gameAccount = $acc; break; }
                    }
                    $logoPath = getGameLogo($game);
                ?>
                    <a href="/games/play.php?g=<?= htmlspecialchars($gameSlug) ?>" class="ud-game-card">
                        <div class="ud-game-icon">
                            <?php if ($logoPath): ?>
                                <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($gameName) ?>">
                            <?php else: ?>
                                <i class="fas fa-gamepad" style="font-size: 28px; color: var(--text-muted);"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ud-game-name"><?= htmlspecialchars($gameName) ?></div>
                        <?php if ($gameAccount): ?><div class="ud-game-badge"><i class="fas fa-check-circle"></i> Ready</div><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <nav class="ud-nav">
        <a href="/dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="/deposit.php"><i class="fas fa-wallet"></i> Deposit</a>
        <a href="/games.php" class="active"><i class="fas fa-gamepad"></i> Games</a>
        <a href="/leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="/profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="/support.php"><i class="fas fa-headset"></i> Support</a>
    </nav>

    <script src="assets/js/toasts.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
