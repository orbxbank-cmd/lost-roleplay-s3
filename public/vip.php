<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();
\Core\Auth::requireAuth();

$db = \Core\Database::getInstance();
$user = \Core\Auth::user();
$error = '';
$success = '';

// Get active VIP plans
$plans = $db->fetchAll("SELECT * FROM shop_vip_plans WHERE is_active = 1 ORDER BY level");

// Get user's current VIP
$userVip = $db->fetch("
    SELECT uv.*, vp.name as plan_name, vp.level, vp.daily_coins, vp.price_coins, vp.color 
    FROM shop_user_vip uv 
    JOIN shop_vip_plans vp ON uv.plan_id = vp.id 
    WHERE uv.user_id = ? AND uv.is_active = 1 AND uv.end_date > NOW() 
    ORDER BY uv.end_date DESC LIMIT 1
", [$user['id']]);

// Auto-claim daily VIP coins
if ($userVip) {
    $today = date('Y-m-d');
    if ($userVip['last_daily'] !== $today) {
        $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$userVip['daily_coins'], $user['id']]);
        $db->query("UPDATE shop_user_vip SET last_daily = ? WHERE id = ?", [$today, $userVip['id']]);
        $db->insert('coin_transactions', [
            'user_id' => $user['id'],
            'amount' => $userVip['daily_coins'],
            'type' => 'bonus',
            'description' => 'VIP Daily Coins (' . $userVip['plan_name'] . ')'
        ]);
        $user['coins'] += $userVip['daily_coins'];
        $success = 'Received ' . $userVip['daily_coins'] . ' VIP daily coins!';
    }
}

// Buy or Upgrade VIP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_vip'])) {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $plan = $db->fetch("SELECT * FROM shop_vip_plans WHERE id = ? AND is_active = 1", [$planId]);
    if (!$plan) {
        $error = 'Invalid VIP plan';
    } elseif ($userVip && $plan['level'] <= $userVip['level']) {
        $error = 'You already have ' . $userVip['plan_name'] . ' or higher. Choose a higher plan to upgrade.';
    } elseif ($userVip) {
        // Upgrade: pay difference
        $diff = $plan['price_coins'] - $userVip['price_coins'];
        if ($user['coins'] < $diff) {
            $error = 'Not enough coins. You need ' . $diff . ' more coins to upgrade to ' . $plan['name'] . '.';
        } else {
            $db->query("UPDATE shop_users SET coins = coins - ? WHERE id = ?", [$diff, $user['id']]);
            $db->query("UPDATE shop_user_vip SET plan_id = ?, end_date = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?", [$planId, $plan['duration_days'], $userVip['id']]);
            $db->insert('coin_transactions', [
                'user_id' => $user['id'],
                'amount' => -$diff,
                'type' => 'payment',
                'description' => 'VIP Upgrade: ' . $userVip['plan_name'] . ' → ' . $plan['name']
            ]);
            $user['coins'] -= $diff;
            $success = 'Upgraded to ' . $plan['name'] . '!';
            $userVip = $db->fetch("
                SELECT uv.*, vp.name as plan_name, vp.level, vp.daily_coins, vp.price_coins, vp.color 
                FROM shop_user_vip uv 
                JOIN shop_vip_plans vp ON uv.plan_id = vp.id 
                WHERE uv.user_id = ? AND uv.is_active = 1 AND uv.end_date > NOW() 
                ORDER BY uv.end_date DESC LIMIT 1
            ", [$user['id']]);
        }
    } else {
        // New purchase
        if ($user['coins'] < $plan['price_coins']) {
            $error = 'Not enough coins. You need ' . $plan['price_coins'] . ' coins.';
        } else {
            $existing = $db->fetch("SELECT id FROM shop_user_vip WHERE user_id = ? AND is_active = 1 AND end_date > NOW()", [$user['id']]);
            if ($existing) {
                $db->query("UPDATE shop_user_vip SET end_date = DATE_ADD(end_date, INTERVAL ? DAY) WHERE id = ?", [$plan['duration_days'], $existing['id']]);
            } else {
                $db->query("INSERT INTO shop_user_vip (user_id, plan_id, end_date) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))", [$user['id'], $planId, $plan['duration_days']]);
            }
            $db->query("UPDATE shop_users SET coins = coins - ? WHERE id = ?", [$plan['price_coins'], $user['id']]);
            $db->insert('coin_transactions', [
                'user_id' => $user['id'],
                'amount' => -$plan['price_coins'],
                'type' => 'payment',
                'description' => 'VIP ' . $plan['name'] . ' (' . $plan['duration_days'] . ' days)'
            ]);
            $user['coins'] -= $plan['price_coins'];
            $success = 'VIP ' . $plan['name'] . ' activated!';
            $userVip = $db->fetch("
                SELECT uv.*, vp.name as plan_name, vp.level, vp.daily_coins, vp.price_coins, vp.color 
                FROM shop_user_vip uv 
                JOIN shop_vip_plans vp ON uv.plan_id = vp.id 
                WHERE uv.user_id = ? AND uv.is_active = 1 AND uv.end_date > NOW() 
                ORDER BY uv.end_date DESC LIMIT 1
            ", [$user['id']]);
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width:800px;margin-top:2rem;margin-bottom:2rem;">
    <div style="text-align:center;margin-bottom:2rem;">
        <i class="fas fa-crown" style="font-size:2.5rem;color:var(--accent);"></i>
        <h1 style="font-family:var(--header-font);text-transform:uppercase;letter-spacing:1px;font-size:1.8rem;margin-top:0.5rem;">VIP Subscription</h1>
        <p style="color:var(--text-secondary);">Auto-renew. Daily coins credited automatically. Upgrade anytime.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($userVip): ?>
        <div style="background:linear-gradient(135deg,<?= $userVip['color'] ?>22,transparent);border:1px solid <?= $userVip['color'] ?>44;border-radius:var(--radius);padding:1.5rem;margin-bottom:2rem;text-align:center;">
            <div style="font-size:2rem;color:<?= $userVip['color'] ?>;"><i class="fas fa-crown"></i></div>
            <h3 style="font-family:var(--header-font);color:<?= $userVip['color'] ?>;text-transform:uppercase;"><?= htmlspecialchars($userVip['plan_name']) ?></h3>
            <p style="font-size:0.85rem;color:var(--text-secondary);">Expires: <?= date('Y-m-d H:i', strtotime($userVip['end_date'])) ?></p>
            <p style="font-size:0.85rem;color:var(--text-secondary);"><?= number_format($userVip['daily_coins']) ?> coins / day (auto-credited)</p>
            <p style="font-size:0.75rem;color:var(--text-secondary);margin-top:0.3rem;"><i class="fas fa-sync"></i> Auto-renews upon expiry if balance is sufficient</p>
        </div>
    <?php endif; ?>

    <div style="display:grid;gap:1rem;">
        <?php foreach ($plans as $plan): ?>
            <?php 
                $owned = $userVip && $userVip['plan_id'] == $plan['id'];
                $upgrade = $userVip && $plan['level'] > $userVip['level'];
                $downgrade = $userVip && $plan['level'] <= $userVip['level'];
                $diff = $userVip ? ($plan['price_coins'] - $userVip['price_coins']) : $plan['price_coins'];
                $canBuy = !$downgrade && $user['coins'] >= ($userVip ? $diff : $plan['price_coins']);
            ?>
            <div style="background:var(--bg-card);border:1px solid <?= $plan['color'] ?>44;border-radius:var(--radius);padding:1.5rem;display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap; <?= $owned ? 'border-width:2px;' : '' ?>">
                <div style="flex-shrink:0;width:50px;height:50px;border-radius:50%;background:<?= $plan['color'] ?>22;display:flex;align-items:center;justify-content:center;border:2px solid <?= $plan['color'] ?>;">
                    <i class="fas fa-crown" style="font-size:1.3rem;color:<?= $plan['color'] ?>;"></i>
                </div>
                <div style="flex:1;min-width:150px;">
                    <h3 style="font-family:var(--header-font);color:<?= $plan['color'] ?>;font-size:1.1rem;text-transform:uppercase;">
                        <?= htmlspecialchars($plan['name']) ?>
                        <?php if ($owned): ?><span style="font-size:0.6rem;background:var(--success);color:#fff;padding:2px 6px;border-radius:3px;vertical-align:middle;margin-right:0.3rem;">ACTIVE</span><?php endif; ?>
                    </h3>
                    <p style="font-size:0.8rem;color:var(--text-secondary);margin-top:0.2rem;"><?= htmlspecialchars($plan['description']) ?></p>
                    <div style="display:flex;gap:0.8rem;margin-top:0.4rem;flex-wrap:wrap;">
                        <span style="font-size:0.75rem;background:<?= $plan['color'] ?>22;color:<?= $plan['color'] ?>;padding:2px 8px;border-radius:4px;"><i class="fas fa-coins"></i> <?= $plan['daily_coins'] ?>/day</span>
                        <span style="font-size:0.75rem;background:var(--bg-dark);color:var(--text-muted);padding:2px 8px;border-radius:4px;"><i class="fas fa-calendar"></i> <?= $plan['duration_days'] ?> days</span>
                        <span style="font-size:0.75rem;background:var(--bg-dark);color:var(--text-muted);padding:2px 8px;border-radius:4px;"><i class="fas fa-sync"></i> Auto-renew</span>
                    </div>
                </div>
                <div style="text-align:center;flex-shrink:0;">
                    <div style="font-size:1.5rem;font-weight:800;color:var(--accent);font-family:var(--header-font);">
                        <?php if ($upgrade): ?>
                            +<?= $diff ?>
                        <?php else: ?>
                            <?= $plan['price_coins'] ?>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">
                        <?php if ($owned): ?>current<?php elseif ($upgrade): ?>upgrade<?php else: ?>coins<?php endif; ?>
                    </div>
                    <form method="POST" style="margin-top:0.5rem;">
                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                        <?php if ($owned): ?>
                            <button type="button" class="btn btn-sm btn-secondary" disabled style="opacity:0.7;"><i class="fas fa-check"></i> Active</button>
                        <?php elseif ($downgrade): ?>
                            <button type="button" class="btn btn-sm btn-secondary" disabled style="opacity:0.4;">Downgrade</button>
                        <?php elseif ($upgrade): ?>
                            <button type="submit" name="buy_vip" class="btn btn-sm btn-warning" <?= !$canBuy ? 'disabled' : '' ?>>
                                <?= !$canBuy ? 'Need ' . ($diff - $user['coins']) . ' more' : 'Upgrade <i class="fas fa-arrow-up"></i>' ?>
                            </button>
                        <?php else: ?>
                            <button type="submit" name="buy_vip" class="btn btn-sm <?= $plan['level'] === 3 ? 'btn-gold' : 'btn-primary' ?>" <?= !$canBuy ? 'disabled' : '' ?>>
                                <?= !$canBuy ? 'Need ' . ($plan['price_coins'] - $user['coins']) . ' more' : 'Subscribe' ?>
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:2rem;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;">
        <h4 style="font-family:var(--header-font);font-size:0.9rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.6rem;"><i class="fas fa-star" style="color:var(--accent);"></i> VIP Benefits</h4>
        <ul style="font-size:0.82rem;color:var(--text-secondary);line-height:2;padding-right:1.2rem;">
            <li>Daily coins credited automatically — no manual claim needed</li>
            <li>Auto-renews when period ends (if balance is sufficient)</li>
            <li>Upgrade anytime — pay only the price difference</li>
            <li>Special VIP role in-game</li>
            <li>Priority support for orders</li>
            <li>Your VIP level shows on your profile</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
