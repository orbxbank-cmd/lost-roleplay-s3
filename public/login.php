<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();

$app = require __DIR__ . '/../config/app.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    $password = trim($_POST['password'] ?? '');
    $referralCode = trim($_POST['referral_code'] ?? '');
    if (\Core\Auth::attempt($username, $password ?: null, $referralCode ?: null)) {
        if (\Core\Auth::isAdmin()) {
            header('Location: ../api/admin/index.php');
        } else {
            header('Location: index.php');
        }
        exit;
    } else {
        $error = 'Invalid username or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= $app['app_name'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div style="text-align: center; margin-bottom: 1rem;">
            <img src="assets/images/game.png" alt="GTA" style="height: 50px;">
            <img src="assets/images/logo.png" alt="Lost Roleplay" style="height: 50px;">
        </div>
        <h1>Lost Roleplay</h1>
        <p class="subtitle">Login with your in-game username</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> In-Game Username</label>
                <input type="text" name="username" class="form-control" required placeholder="Enter your server username" autocomplete="off">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Server Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Your in-game server password">
            </div>
            <div class="form-group">
                <label><i class="fas fa-gift"></i> Referral Code (optional)</label>
                <input type="text" name="referral_code" class="form-control" placeholder="Enter referral code if you have one">
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>

        <div style="text-align: center; margin-top: 1rem;">
            <p style="color: var(--text-muted); font-size: 0.75rem;">Don't have a server account yet?<br>Join the server first, then come back!</p>
            <a href="index.php" style="color: var(--text-muted); font-size: 0.8rem;"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</div>
</body>
</html>
