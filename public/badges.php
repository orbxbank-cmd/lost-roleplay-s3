<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();

$db = \Core\Database::getInstance();
$loggedIn = \Core\Auth::isLoggedIn();
$user = $loggedIn ? \Core\Auth::user() : null;

$refCount = 0;
$orderCount = 0;
if ($user) {
    $refCount = $db->fetch("SELECT COUNT(*) as cnt FROM shop_users WHERE referred_by = ?", [$user['id']])['cnt'] ?? 0;
    $orderCount = $db->fetch("SELECT COUNT(*) as cnt FROM shop_orders WHERE user_id = ? AND order_status NOT IN ('cancelled')", [$user['id']])['cnt'] ?? 0;
}

$badges = [
    [
        'id' => 'client',
        'name' => 'Client',
        'desc' => 'Buy your first product',
        'icon' => 'fa-check',
        'color' => '#6f42c1',
        'current' => $orderCount,
        'tiers' => [
            ['name' => 'Client', 'need' => 1, 'icon' => 'fa-check'],
            ['name' => 'Regular Client', 'need' => 5, 'icon' => 'fa-shopping-bag'],
            ['name' => 'VIP Client', 'need' => 10, 'icon' => 'fa-crown'],
        ],
    ],
    [
        'id' => 'referrer',
        'name' => 'Referrer',
        'desc' => 'Refer players to the server',
        'icon' => 'fa-user-plus',
        'color' => '#198754',
        'current' => $refCount,
        'tiers' => [
            ['name' => 'Referrer', 'need' => 1, 'icon' => 'fa-user-plus'],
            ['name' => 'Star Referrer', 'need' => 5, 'icon' => 'fa-star'],
            ['name' => 'Top Referrer', 'need' => 10, 'icon' => 'fa-crown'],
        ],
    ],
    [
        'id' => 'youtuber',
        'name' => 'YouTuber',
        'desc' => 'Content creator badge (assigned by admin)',
        'icon' => 'fab fa-youtube',
        'color' => '#ff4444',
        'current' => $user['is_youtuber'] ?? 0,
        'unlocked' => !empty($user['is_youtuber']),
        'tiers' => [
            ['name' => 'YouTuber', 'need' => 1, 'icon' => 'fab fa-youtube'],
        ],
    ],
];

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width: 800px;">
        <h2 class="section-title"><i class="fas fa-medal title-accent"></i> Badges</h2>
        <p class="section-subtitle">Complete achievements to unlock badges & show them off</p>

        <div style="display: grid; gap: 1.5rem;">
            <?php foreach ($badges as $badge):
                $unlocked = $badge['unlocked'] ?? false;
                $currentTier = null;
                $nextTier = null;
                foreach ($badge['tiers'] as $i => $tier) {
                    if ($badge['current'] >= $tier['need']) {
                        $currentTier = $tier;
                        $unlocked = true;
                    } else {
                        $nextTier = $tier;
                        break;
                    }
                }
                $progress = 0;
                $progressTarget = 1;
                if ($nextTier) {
                    $prevNeed = $currentTier ? $currentTier['need'] : 0;
                    $progress = $badge['current'] - $prevNeed;
                    $progressTarget = $nextTier['need'] - $prevNeed;
                } else {
                    $progress = 1;
                    $progressTarget = 1;
                }
                $pct = min(100, max(0, ($progress / $progressTarget) * 100));
            ?>
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; <?= $unlocked ? '' : 'opacity: 0.7;' ?>">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 50px; height: 50px; border-radius: 50%; background: <?= $badge['color'] ?>20; display: flex; align-items: center; justify-content: center; border: 2px solid <?= $badge['color'] ?>;">
                        <i class="<?= $badge['icon'] ?>" style="font-size: 1.3rem; color: <?= $badge['color'] ?>;"></i>
                    </div>
                    <div style="flex: 1;">
                        <h3 style="font-family: var(--header-font); display: flex; align-items: center; gap: 0.5rem;">
                            <?= $badge['name'] ?>
                            <?php if ($currentTier): ?>
                                <span style="font-size: 0.75rem; background: <?= $badge['color'] ?>; color: #fff; padding: 1px 8px; border-radius: 4px;"><i class="<?= $currentTier['icon'] ?>"></i> <?= $currentTier['name'] ?></span>
                            <?php endif; ?>
                        </h3>
                        <p style="color: var(--text-secondary); font-size: 0.85rem;"><?= $badge['desc'] ?></p>
                    </div>
                </div>

                <?php if ($badge['id'] !== 'youtuber'): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.8rem;">
                        <?php foreach ($badge['tiers'] as $tier):
                            $tierUnlocked = $badge['current'] >= $tier['need'];
                        ?>
                            <div style="flex: 1; min-width: 100px; text-align: center; padding: 0.5rem; background: <?= $tierUnlocked ? $badge['color'] . '20' : 'var(--bg-input)' ?>; border: 1px solid <?= $tierUnlocked ? $badge['color'] : 'var(--border)' ?>; border-radius: 6px;">
                                <div style="font-size: 1rem; color: <?= $tierUnlocked ? $badge['color'] : 'var(--text-muted)' ?>;"><i class="<?= $tier['icon'] ?>"></i></div>
                                <div style="font-size: 0.7rem; font-weight: 600; margin-top: 2px; color: <?= $tierUnlocked ? '#fff' : 'var(--text-muted)' ?>;"><?= $tier['name'] ?></div>
                                <div style="font-size: 0.65rem; color: var(--text-muted);"><?= $tier['need'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 3px;">
                        <span>Progress: <?= $badge['current'] ?> / <?= $nextTier ? $nextTier['need'] : $badge['tiers'][count($badge['tiers'])-1]['need'] ?></span>
                        <span><?= round($pct) ?>%</span>
                    </div>
                    <div style="background: var(--bg-input); height: 6px; border-radius: 3px;">
                        <div style="background: <?= $badge['color'] ?>; height: 6px; border-radius: 3px; width: <?= $pct ?>%; transition: width 0.5s;"></div>
                    </div>
                    <?php if ($nextTier): ?>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">
                            Next: <?= $nextTier['name'] ?> (<?= $nextTier['need'] - $badge['current'] ?> more)
                        </div>
                    <?php else: ?>
                        <div style="font-size: 0.7rem; color: var(--success); margin-top: 4px;">
                            <i class="fas fa-check-circle"></i> All tiers unlocked!
                        </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); text-align: center;">
                    <?php if ($unlocked): ?>
                        <span style="display:inline-block;background:<?= $badge['color'] ?>;color:#fff;padding:4px 16px;border-radius:4px;font-weight:700;"><i class="fab fa-youtube"></i> YouTuber Badge Earned</span>
                    <?php else: ?>
                        <span style="color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-lock"></i> Contact admin to get this badge</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (!$loggedIn): ?>
            <div style="text-align: center; padding: 2rem;">
                <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Login to unlock badges!</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
