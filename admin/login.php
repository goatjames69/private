<?php
require_once '../config.php';

$error = '';

if (isAdmin() || isStaff()) {
    header('Location: /admin/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD)) {
        $_SESSION['admin'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['role'] = 'admin';
        header('Location: /admin/dashboard.php');
        exit;
    }

    $staffList = readJSON(STAFF_FILE);
    foreach ($staffList as $staff) {
        if (($staff['username'] ?? '') === $username && password_verify($password, $staff['password_hash'] ?? '')) {
            $_SESSION['admin'] = false;
            $_SESSION['role'] = 'staff';
            $_SESSION['staff_id'] = $staff['id'];
            $_SESSION['staff_username'] = $staff['username'];
            $_SESSION['admin_username'] = $staff['username'];
            header('Location: /admin/dashboard.php');
            exit;
        }
    }

    $error = 'Invalid username or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Admin / Staff Login</h1>
        </div>

        <div class="card" style="max-width: 500px; margin: 50px auto;">
            <h2 class="card-title">Admin or Staff Access</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-block">Login</button>
            </form>

            <p style="text-align: center; margin-top: 20px; color: var(--text-secondary);">
                <a href="/auth/login.php" style="color: var(--accent-neon);">← Back to User Login</a>
            </p>
        </div>
    </div>

    <script src="../assets/js/toasts.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
