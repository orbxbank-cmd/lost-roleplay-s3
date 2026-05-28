<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();

$db = \Core\Database::getInstance();
$loggedIn = \Core\Auth::isLoggedIn();
$user = $loggedIn ? \Core\Auth::user() : null;

$error = '';
$reward = null;
$claimed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim']) && $user) {
    $now = date('Y-m-d H:i:s');
    $last = $user['last_daily_reward'];

    if ($last) {
        $next = date('Y-m-d H:i:s', strtotime($last . ' +24 hours'));
        if ($now < $next) {
            $remaining = strtotime($next) - strtotime($now);
            $h = floor($remaining / 3600);
            $m = floor(($remaining % 3600) / 60);
            $error = "Wait {$h}h {$m}m for your next reward!";
        }
    }

    if (!$error) {
        $amount = mt_rand(1, 15);
        $reward = $amount;

        $db->query("UPDATE shop_users SET coins = coins + ?, last_daily_reward = ? WHERE id = ?", [$amount, $now, $user['id']]);
        $db->insert('coin_transactions', [
            'user_id' => $user['id'],
            'amount' => $amount,
            'type' => 'bonus',
            'description' => 'Daily Reward - ' . $amount . ' coins',
        ]);
        \Core\Logger::info('Daily reward claimed', ['user_id' => $user['id'], 'coins' => $amount]);

        $user['last_daily_reward'] = $now;
        $user['coins'] += $amount;
        $claimed = true;
    }
}

$canClaim = false;
$nextTime = null;
if ($user && $user['last_daily_reward']) {
    $nextTime = strtotime($user['last_daily_reward'] . ' +24 hours');
    $canClaim = time() >= $nextTime;
} elseif ($user) {
    $canClaim = true;
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width: 600px;">
        <h2 class="section-title"><i class="fas fa-calendar-day title-accent"></i> Daily Reward</h2>
        <p class="section-subtitle">Claim free coins every 24 hours!</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-clock"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($claimed): ?>
            <div class="alert alert-success" style="text-align:center;font-size:1.3rem;padding:1.5rem;">
                <i class="fas fa-gift"></i> You claimed <strong style="color:var(--accent);font-size:1.8rem;">+<?= $reward ?> Coins</strong>!
            </div>
        <?php endif; ?>

        <?php if (!$loggedIn): ?>
            <div style="text-align:center;padding:2rem;">
                <div style="font-size:4rem;color:var(--accent);margin-bottom:1rem;"><i class="fas fa-gift"></i></div>
                <p style="color:var(--text-secondary);margin-bottom:0.5rem;">Claim <strong style="color:var(--accent);font-size:1.5rem;">1 - 15</strong> free coins every 24 hours!</p>
                <p style="color:var(--text-muted);margin-bottom:1.5rem;font-size:0.85rem;">Login and come back daily to collect your reward.</p>
                <div style="display:grid;grid-template-columns:repeat(8,1fr);gap:0.3rem;max-width:400px;margin:0 auto 1.5rem auto;">
                    <?php for ($i = 1; $i <= 15; $i++): ?>
                        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:4px;padding:0.3rem;text-align:center;font-size:0.7rem;color:var(--text-muted);"><?= $i ?></div>
                    <?php endfor; ?>
                </div>
                <a href="login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt"></i> Login to Claim</a>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:2rem 0;">
                <div style="font-size:4rem;color:var(--accent);margin-bottom:1rem;"><i class="fas fa-gift"></i></div>
                <div style="font-size:3rem;font-weight:800;color:var(--accent);"><?= number_format($user['coins']) ?></div>
                <div style="color:var(--text-muted);margin-bottom:1.5rem;">Your Coins</div>

                <?php if ($canClaim): ?>
                    <form method="POST">
                        <input type="hidden" name="claim" value="1">
                        <button type="submit" class="btn btn-primary btn-lg" style="font-size:1.2rem;padding:1rem 3rem;">
                            <i class="fas fa-hand-paper"></i> Claim Now!
                        </button>
                    </form>
                    <div style="margin-top:1rem;display:grid;grid-template-columns:repeat(8,1fr);gap:0.3rem;max-width:400px;margin-left:auto;margin-right:auto;">
                        <?php for ($i = 1; $i <= 15; $i++): ?>
                            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:4px;padding:0.3rem;text-align:center;font-size:0.7rem;color:var(--text-muted);"><?= $i ?></div>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <button class="btn btn-secondary btn-lg" disabled style="font-size:1.2rem;padding:1rem 3rem;">
                        <i class="fas fa-clock"></i> Next in <span id="countdown"></span>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
<?php if (!$canClaim && $nextTime): ?>
function updateCountdown() {
    const now = new Date().getTime();
    const target = <?= $nextTime * 1000 ?>;
    const diff = target - now;
    if (diff <= 0) { location.reload(); return; }
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    document.getElementById('countdown').textContent = h + 'h ' + m + 'm ' + s + 's';
}
setInterval(updateCountdown, 1000);
updateCountdown();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
