<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Auth.php';
\Core\Session::start();
$db = \Core\Database::getInstance();

$app = require __DIR__ . '/../../config/app.php';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $app['app_name'] ?> - <?= $app['server_name'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="navbar-brand">
            <img src="assets/images/game.png" alt="GTA" style="height: 32px; width: auto;">
            <img src="assets/images/logo.png" alt="Lost Roleplay" style="height: 32px; width: auto;">
            <span class="gta-logo-text">
                <span class="san">Lost</span> <span class="andreas">Roleplay</span>
            </span>
        </a>
        <ul class="navbar-links">
            <li><a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="shop.php" class="<?= $currentPage === 'shop.php' ? 'active' : '' ?>"><i class="fas fa-store"></i> Shop</a></li>
            <li><a href="dailyreward.php" class="<?= $currentPage === 'dailyreward.php' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Daily</a></li>
            <li><a href="games.php" class="<?= $currentPage === 'games.php' ? 'active' : '' ?>"><i class="fas fa-gamepad"></i> Games</a></li>
            <li><a href="badges.php" class="<?= $currentPage === 'badges.php' ? 'active' : '' ?>"><i class="fas fa-medal"></i> Badges</a></li>
            <li><a href="referral.php" class="<?= $currentPage === 'referral.php' ? 'active' : '' ?>"><i class="fas fa-gift"></i> Referral</a></li>
            <li><a href="cart.php" class="<?= $currentPage === 'cart.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Cart <span class="cart-badge" style="display:none;">0</span></a></li>
            <?php if (\Core\Auth::isLoggedIn()):
                $userInfo = \Core\Auth::user();
                $refCount = $db->fetch("SELECT COUNT(*) as cnt FROM shop_users WHERE referred_by = ?", [$userInfo['id']])['cnt'] ?? 0;
                $orderCount = $db->fetch("SELECT COUNT(*) as cnt FROM shop_orders WHERE user_id = ? AND order_status NOT IN ('cancelled')", [$userInfo['id']])['cnt'] ?? 0; ?>
                <li><a href="sendcoins.php" class="<?= $currentPage === 'sendcoins.php' ? 'active' : '' ?>"><i class="fas fa-paper-plane"></i> Send</a></li>
                <li>
                    <a href="profile.php" class="user-nav <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-circle"></i>
                        <span class="user-nav-name"><?= htmlspecialchars(\Core\Auth::username()) ?></span>
                        <span class="user-nav-coins"><i class="fas fa-coins" style="color:var(--accent);"></i> <?= number_format($userInfo['coins'] ?? 0) ?></span>
                    </a>
                </li>
                <li><a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> Orders</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>