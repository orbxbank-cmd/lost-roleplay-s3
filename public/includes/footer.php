<?php
$app = require __DIR__ . '/../../config/app.php';
?>
<footer class="footer">
    <div class="container">
        <p class="gta-logo-text"><span class="san">Lost</span> <span class="andreas">Roleplay</span> S03 &copy; <?= date('Y') ?> - All rights reserved</p>
        <div class="contact">
            <i class="fas fa-phone"></i> <?= $app['contact_phone'] ?> &nbsp;|&nbsp; <i class="fas fa-server"></i> <?= $app['server_ip'] ?>
        </div>
    </div>
</footer>
<script src="assets/js/app.js"></script>
</body>
</html>
