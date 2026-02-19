<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JAMES GAMEROOM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-dice"></i> JAMES GAMEROOM</h1>
        </div>

        <div class="card" style="text-align: center; max-width: 600px; margin: 50px auto;">
            <h2 style="font-size: 32px; margin-bottom: 20px; background: linear-gradient(90deg, var(--accent-neon), var(--accent-purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Welcome to JAMES GAMEROOM
            </h2>
            <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 18px;">
                Your gateway to premium casino gaming experience
            </p>

            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="/auth/login.php" class="btn">Login</a>
                <a href="/auth/register.php" class="btn btn-secondary">Register</a>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title"><i class="fas fa-gamepad"></i> Available Games</h3>
            <p style="text-align: center; color: var(--text-secondary); margin-top: 20px;">
                Please <a href="/auth/login.php" style="color: var(--accent-neon);">login</a> to access games
            </p>
        </div>
    </div>
</body>
</html>
