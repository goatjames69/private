<?php
require_once '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        $users = readJSON(USERS_FILE);
        $found = false;

        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['admin'] = false;
                $found = true;
                $hasEmail = !empty(trim($user['email'] ?? ''));
                header('Location: ' . ($hasEmail ? '/dashboard.php' : '/profile.php?add_email=1'));
                exit;
            }
        }

        if (!$found) {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/realtime.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎰 JAMES GAMEROOM</h1>
        </div>

        <div class="card" style="max-width: 500px; margin: 50px auto;">
            <h2 class="card-title">Login</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-block">Login</button>
            </form>

            <p style="text-align: center; margin-top: 20px; color: var(--text-secondary);">
                Don't have an account? <a href="/auth/register.php" style="color: var(--accent-neon);">Register here</a>
            </p>

           
        </div>
    </div>

    <script src="../assets/js/toasts.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
