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
    SELECT uv.*, vp.name as plan_name, vp.level, vp.daily_coins, vp.color 
    FROM shop_user_vip uv 
    JOIN shop_vip_plans vp ON uv.plan_id = vp.id 
    WHERE uv.user_id = ? AND uv.is_active = 1 AND uv.end_date > NOW() 
    ORDER BY uv.end_date DESC LIMIT 1
", [$user['id']]);

// Claim daily VIP coins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_daily'])) {
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
            $success = 'Claimed ' . $userVip['daily_coins'] . ' daily VIP coins!';
        } else {
            $error = 'You already claimed today\'s VIP coins. Come back tomorrow!';
        }
    }
}

// Buy VIP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_vip'])) {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $plan = $db->fetch("SELECT * FROM shop_vip_plans WHERE id = ? AND is_active = 1", [$planId]);
    if (!$plan) {
        $error = 'Invalid VIP plan';
    } elseif ($user['coins'] < $plan['price_coins']) {
        $error = 'Not enough coins. You need ' . $plan['price_coins'] . ' coins.';
    } else {
        // Check if already has active VIP
        $existing = $db->fetch("SELECT id FROM shop_user_vip WHERE user_id = ? AND is_active = 1 AND end_date > NOW()", [$user['id']]);
        if ($existing) {
            // Extend existing VIP
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
        $success = 'VIP ' . $plan['name'] . ' activated! Check your profile for benefits.';
        // Refresh VIP data
        $userVip = $db->fetch("
            SELECT uv.*, vp.name as plan_name, vp.level, vp.daily_coins, vp.color 
            FROM shop_user_vip uv 
            JOIN shop_vip_plans vp ON uv.plan_id = vp.id 
            WHERE uv.user_id = ? AND uv.is_active = 1 AND uv.end_date > NOW() 
            ORDER BY uv.end_date DESC LIMIT 1
        ", [$user['id']]);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width:800px;margin-top:2rem;margin-bottom:2rem;">
    <div style="text-align:center;margin-bottom:2rem;">
        <i class="fas fa-crown" style="font-size:2.5rem;color:var(--accent);"></i>
        <h1 style="font-family:var(--header-font);text-transform:uppercase;letter-spacing:1px;font-size:1.8rem;margin-top:0.5rem;">VIP Membership</h1>
        <p style="color:var(--text-secondary);">Get exclusive rewards, daily coins, and more!</p>
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
            <p style="font-size:0.85rem;color:var(--text-secondary);"><?= number_format($userVip['daily_coins']) ?> coins / day</p>
            <form method="POST" style="margin-top:0.8rem;">
                <button type="submit" name="claim_daily" class="btn btn-gold" <?= $userVip['last_daily'] === date('Y-m-d') ? 'disabled' : '' ?>>
                    <i class="fas fa-gift"></i> <?= $userVip['last_daily'] === date('Y-m-d') ? 'Claimed Today' : 'Claim ' . $userVip['daily_coins'] . ' Daily Coins' ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div style="display:grid;gap:1rem;">
        <?php foreach ($plans as $plan): ?>
            <div style="background:var(--bg-card);border:1px solid <?= $plan['color'] ?>44;border-radius:var(--radius);padding:1.5rem;display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;">
                <div style="flex-shrink:0;width:50px;height:50px;border-radius:50%;background:<?= $plan['color'] ?>22;display:flex;align-items:center;justify-content:center;border:2px solid <?= $plan['color'] ?>;">
                    <i class="fas fa-crown" style="font-size:1.3rem;color:<?= $plan['color'] ?>;"></i>
                </div>
                <div style="flex:1;min-width:150px;">
                    <h3 style="font-family:var(--header-font);color:<?= $plan['color'] ?>;font-size:1.1rem;text-transform:uppercase;"><?= htmlspecialchars($plan['name']) ?></h3>
                    <p style="font-size:0.8rem;color:var(--text-secondary);margin-top:0.2rem;"><?= htmlspecialchars($plan['description']) ?></p>
                    <div style="display:flex;gap:0.8rem;margin-top:0.4rem;flex-wrap:wrap;">
                        <span style="font-size:0.75rem;background:<?= $plan['color'] ?>22;color:<?= $plan['color'] ?>;padding:2px 8px;border-radius:4px;"><i class="fas fa-coins"></i> <?= $plan['daily_coins'] ?>/day</span>
                        <span style="font-size:0.75rem;background:var(--bg-dark);color:var(--text-muted);padding:2px 8px;border-radius:4px;"><i class="fas fa-calendar"></i> <?= $plan['duration_days'] ?> days</span>
                    </div>
                </div>
                <div style="text-align:center;flex-shrink:0;">
                    <div style="font-size:1.5rem;font-weight:800;color:var(--accent);font-family:var(--header-font);"><?= $plan['price_coins'] ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">coins</div>
                    <form method="POST" style="margin-top:0.5rem;">
                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                        <button type="submit" name="buy_vip" class="btn btn-sm <?= $plan['level'] === 3 ? 'btn-gold' : 'btn-primary' ?>" <?= $user['coins'] < $plan['price_coins'] ? 'disabled' : '' ?>><?= $user['coins'] < $plan['price_coins'] ? 'Need ' . ($plan['price_coins'] - $user['coins']) . ' more' : 'Buy' ?></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:2rem;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;">
        <h4 style="font-family:var(--header-font);font-size:0.9rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.6rem;"><i class="fas fa-star" style="color:var(--accent);"></i> VIP Benefits</h4>
        <ul style="font-size:0.82rem;color:var(--text-secondary);line-height:2;padding-right:1.2rem;">
            <li>Daily free coins — claim every 24h</li>
            <li>Special VIP role in-game</li>
            <li>Priority support for orders</li>
            <li>Exclusive VIP-only products (coming soon)</li>
            <li>Your VIP level shows on your profile</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
