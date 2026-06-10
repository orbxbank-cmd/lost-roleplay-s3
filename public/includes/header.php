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
            <img src="assets/images/logo.png" alt="Lost RoLePLay S03" style="height: 32px; width: auto;">
            <span class="gta-logo-text">
                <span class="san">Lost</span> <span class="andreas">Roleplay</span>
            </span>
        </a>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <ul class="navbar-links">
            <li><a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="shop.php" class="<?= $currentPage === 'shop.php' ? 'active' : '' ?>"><i class="fas fa-store"></i> Shop</a></li>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<nav class="mobile-sidebar" id="mobileSidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-brand">
            <img src="assets/images/logo.png" alt="Lost RoLePLay S03" style="height:32px;">
            <span class="gta-logo-text"><span class="san">Lost</span> <span class="andreas">Roleplay</span></span>
        </a>
        <button class="sidebar-close" id="sidebarClose">&times;</button>
    </div>
    <?php if (\Core\Auth::isLoggedIn()):
        $u = \Core\Auth::user(); ?>
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><i class="fas fa-user-circle"></i></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars(\Core\Auth::username()) ?></div>
                <div class="sidebar-user-coins"><i class="fas fa-coins" style="color:var(--accent);"></i> <?= number_format($u['coins'] ?? 0) ?> coins</div>
            </div>
        </div>
    <?php endif; ?>
    <ul class="sidebar-links">
        <li><a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="shop.php" class="<?= $currentPage === 'shop.php' ? 'active' : '' ?>"><i class="fas fa-store"></i> Shop</a></li>
        <li><a href="games.php" class="<?= $currentPage === 'games.php' ? 'active' : '' ?>"><i class="fas fa-gamepad"></i> Games</a></li>
        <li><a href="badges.php" class="<?= $currentPage === 'badges.php' ? 'active' : '' ?>"><i class="fas fa-medal"></i> Badges</a></li>
        <li><a href="referral.php" class="<?= $currentPage === 'referral.php' ? 'active' : '' ?>"><i class="fas fa-gift"></i> Referral</a></li>
        <li><a href="cart.php" class="<?= $currentPage === 'cart.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Cart</a></li>
        <?php if (\Core\Auth::isLoggedIn()): ?>
            <li><a href="sendcoins.php" class="<?= $currentPage === 'sendcoins.php' ? 'active' : '' ?>"><i class="fas fa-paper-plane"></i> Send Coins</a></li>
            <li><a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="vip.php" class="<?= $currentPage === 'vip.php' ? 'active' : '' ?>"><i class="fas fa-crown"></i> VIP</a></li>
            <li><a href="security.php" class="<?= $currentPage === 'security.php' ? 'active' : '' ?>"><i class="fas fa-shield-alt"></i> Security</a></li>
            <li><a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> Orders</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        <?php else: ?>
            <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
        <?php endif; ?>
    </ul>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var hamburger = document.getElementById('hamburger');
    var sidebar = document.getElementById('mobileSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var closeBtn = document.getElementById('sidebarClose');
    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
    hamburger.addEventListener('click', openSidebar);
    closeBtn.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);
});
</script>