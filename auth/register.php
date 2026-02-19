<?php
require_once '../config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $referral_code_input = trim($_POST['referral_code'] ?? '');

    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($phone)) {
        $error = 'All fields are required (including email)';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $users = readJSON(USERS_FILE);

        foreach ($users as $user) {
            if ($user['username'] === $username) {
                $error = 'Username already exists';
                break;
            }
            if (!empty($user['email']) && strtolower($user['email']) === strtolower($email)) {
                $error = 'Email already registered';
                break;
            }
        }

        $referred_by = null;
        if (empty($error) && $referral_code_input !== '') {
            $referrer = findUserByReferralCode($users, $referral_code_input);
            if ($referrer) {
                $referred_by = $referrer['id'];
            }
        }

        if (empty($error)) {
            $newUser = [
                'id' => generateId(),
                'full_name' => $full_name,
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone,
                'balance' => 0,
                'game_accounts' => [],
                'deposit_history' => [],
                'game_deposit_requests' => [],
                'referral_code' => generateReferralCode($users),
                'referred_by' => $referred_by,
                'referral_bonus_paid' => false
            ];

            $users[] = $newUser;
            writeJSON(USERS_FILE, $users);
            $success = 'Registration successful! Please login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/realtime.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎰 JAMES GAMEROOM</h1>
        </div>

        <div class="card" style="max-width: 500px; margin: 50px auto;">
            <h2 class="card-title">Create Account</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <a href="/auth/login.php" class="btn btn-block">Go to Login</a>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com">
                    </div>

                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" class="form-control" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Referral Code <span style="color: var(--text-muted); font-weight: 400;">(optional)</span></label>
                        <input type="text" name="referral_code" class="form-control" value="<?= htmlspecialchars($_POST['referral_code'] ?? '') ?>" placeholder="Enter a friend's referral code" maxlength="20" autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-block">Register</button>
                </form>

                <p style="text-align: center; margin-top: 20px; color: var(--text-secondary);">
                    Already have an account? <a href="/auth/login.php" style="color: var(--accent-neon);">Login here</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/toasts.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
