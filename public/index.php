<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ServerQuery.php';
require_once __DIR__ . '/includes/header.php';

$config = require __DIR__ . '/../config/app.php';
$serverInfo = \Core\ServerQuery::getServerInfo();
$isOnline = $serverInfo !== null;
$players = $serverInfo['players'] ?? 0;
$maxPlayers = $serverInfo['maxplayers'] ?? 1000;
?>
<section class="hero">
    <div class="container">
        <div style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 15px;">
            <img src="assets/images/game.png" alt="GTA" style="height: 70px; width: auto;">
            <img src="assets/images/logo.png" alt="Lost Roleplay" style="height: 70px; width: auto;">
        </div>
        <div class="server-badge">
            <i class="fas fa-shield-alt"></i>
            <?= $config['server_name'] ?> <i class="fas fa-circle" style="color: <?= $isOnline ? 'var(--success)' : 'var(--danger)' ?>; font-size: 0.5rem; vertical-align: middle;"></i> <?= $isOnline ? 'Online' : 'Offline' ?>
        </div>
        <h1>
            <span class="gta-title">Los Santos</span><br>
            <span class="gta-accent">Lost Roleplay</span>
            <span class="gta-gold">S03</span>
        </h1>
        <p>The best San Andreas Roleplay experience with ranks, cars, money and more</p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="shop.php" class="btn btn-primary btn-lg"><i class="fas fa-shopping-bag"></i> Shop</a>
            <a href="apply.php" class="btn btn-gold btn-lg"><i class="fas fa-clipboard-list"></i> Apply Staff</a>
        </div>
        <div class="server-status">
            <span class="status-dot" style="background: <?= $isOnline ? 'var(--success)' : 'var(--danger)' ?>;"></span>
            <i class="fas fa-server"></i> <?= $config['server_ip'] ?>
            <i class="fas fa-users" style="margin-right: 8px;"></i> <?= $players ?>/<?= $maxPlayers ?>
        </div>
    </div>
</section>

<hr class="gta-divider">

<section class="section">
    <div class="container">
        <h2 class="section-title"><span class="title-accent">Lost Roleplay</span> Community</h2>
        <p class="section-subtitle">Stay connected with us</p>

        <div class="features-strip">
            <div class="feature-item">
                <div class="fi-icon"><i class="fab fa-youtube" style="color: #ff0000;"></i></div>
                <h4>Owner</h4>
                <p><a href="https://www.youtube.com/@ZAGTOSTV" target="_blank">@ZAGTOSTV</a></p>
            </div>
            <div class="feature-item">
                <div class="fi-icon"><i class="fab fa-youtube" style="color: #ff0000;"></i></div>
                <h4>Owner 2</h4>
                <p><a href="https://www.youtube.com/@SmoKe_Xvilo" target="_blank">@SmoKe_Xvilo</a></p>
            </div>
            <div class="feature-item">
                <div class="fi-icon"><i class="fab fa-whatsapp" style="color: #25d366;"></i></div>
                <h4>WhatsApp Group</h4>
                <p><a href="https://chat.whatsapp.com/D7NnxUBF9On9G1hQ6HQHaW" target="_blank">Join Server Group</a></p>
            </div>
            <div class="feature-item">
                <div class="fi-icon"><i class="fas fa-server"></i></div>
                <h4>Server IP</h4>
                <p><?= $config['server_ip'] ?></p>
            </div>
        </div>

        <hr class="gta-divider">

        <div style="text-align: center; padding: 2rem 0;">
            <h2 class="section-title" style="margin-bottom: 1rem;"><span class="title-accent">Apply</span> for Staff</h2>
            <p class="section-subtitle">Want to join our team? Submit your application</p>
            <a href="apply.php" class="btn btn-gold btn-lg"><i class="fas fa-clipboard-list"></i> Apply Now</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
