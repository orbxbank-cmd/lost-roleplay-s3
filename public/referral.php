<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();

$db = \Core\Database::getInstance();
$loggedIn = \Core\Auth::isLoggedIn();
$user = $loggedIn ? \Core\Auth::user() : null;

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width: 800px;">
        <h2 class="section-title"><i class="fas fa-gift title-accent"></i> Content Creator Program</h2>
        <p class="section-subtitle">Earn coins by referring players to Lost Roleplay S03</p>

        <div style="display: grid; gap: 1.5rem;">
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem; text-align: center;">
                <div style="font-size: 2.5rem; color: var(--accent); margin-bottom: 1rem;"><i class="fas fa-trophy"></i></div>
                <h3 style="font-family: var(--header-font); margin-bottom: 0.5rem;">Become a Content Creator</h3>
                <p style="color: var(--text-secondary); max-width: 500px; margin: 0 auto 1.5rem;">
                    YouTubers, streamers & content creators! Share your referral code, earn coins when your audience joins.
                </p>
                <?php if ($loggedIn && $user): ?>
                    <?php
                    $referralCode = $user['referral_code'] ?? '';
                    $referralCount = $db->fetch("SELECT COUNT(*) as cnt FROM shop_users WHERE referred_by = ?", [$user['id']])['cnt'] ?? 0;
                    $referralEarnings = $user['total_referral_earnings'] ?? 0;
                    ?>
                    <div style="display: flex; gap: 0.5rem; justify-content: center; align-items: center; margin-bottom: 1rem;">
                        <input type="text" id="ref-code" class="form-control" value="<?= htmlspecialchars($referralCode) ?>" readonly style="width: 180px; text-align: center; font-size: 1.3rem; font-weight: 800; letter-spacing: 3px;">
                        <button class="btn btn-primary" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                    <div style="display: flex; gap: 2rem; justify-content: center; margin-bottom: 1.5rem;">
                        <div>
                            <div style="font-size: 1.5rem; font-weight: 800; color: var(--accent);"><?= $referralCount ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Players Referred</div>
                        </div>
                        <div>
                            <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);"><i class="fas fa-coins"></i> <?= number_format($referralEarnings) ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Coins Earned</div>
                        </div>
                    </div>
                                        <?php
                    $tier = ''; $tierColor = ''; $tierIcon = '';
                    if ($referralCount >= 10) { $tier = 'Top Referrer'; $tierColor = '#ffc107'; $tierIcon = 'crown'; }
                    elseif ($referralCount >= 5) { $tier = 'Star Referrer'; $tierColor = '#0dcaf0'; $tierIcon = 'star'; }
                    elseif ($referralCount >= 1) { $tier = 'Referrer'; $tierColor = '#198754'; $tierIcon = 'user-plus'; }
                    if ($tier): ?>
                    <div style="margin-bottom: 1rem; text-align: center; padding: 0.5rem; background: <?= $tierColor ?>15; border: 1px solid <?= $tierColor ?>40; border-radius: var(--radius);">
                        <span style="display:inline-block;background:<?= $tierColor ?>;color:#000;padding:2px 12px;border-radius:4px;font-weight:700;font-size:0.85rem;"><i class="fas fa-<?= $tierIcon ?>"></i> <?= $tier ?></span>
                    </div>
                    <?php endif; ?>
                    <a href="https://wa.me/?text=Join%20Lost%20Roleplay%20S03!%20Use%20my%20referral%20code%3A%20<?= urlencode($referralCode) ?>%20at%20https://lost-roleplay-s3.onrender.com" target="_blank" class="btn btn-success" style="margin-right: 0.5rem;"><i class="fab fa-whatsapp"></i> Share on WhatsApp</a>
                    <a href="profile.php" class="btn btn-secondary"><i class="fas fa-user"></i> My Profile</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Login & Get Your Code</a>
                    <div style="margin-top: 0.8rem;">
                        <a href="login.php" style="color: var(--text-muted); font-size: 0.85rem;">Already have an account? Login</a>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; text-align: center;">
                    <div style="font-size: 2rem; color: var(--info); margin-bottom: 0.5rem;"><i class="fas fa-user-plus"></i></div>
                    <h4 style="font-size: 0.9rem;">1. Get Your Code</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted);">Register & get a unique referral code</p>
                </div>
                <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; text-align: center;">
                    <div style="font-size: 2rem; color: var(--accent); margin-bottom: 0.5rem;"><i class="fas fa-share-alt"></i></div>
                    <h4 style="font-size: 0.9rem;">2. Share It</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted);">Share your code on YouTube, WhatsApp, Discord</p>
                </div>
                <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; text-align: center;">
                    <div style="font-size: 2rem; color: var(--success); margin-bottom: 0.5rem;"><i class="fas fa-coins"></i></div>
                    <h4 style="font-size: 0.9rem;">3. Earn Coins</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted);">Get 50 coins per signup + commission on purchases</p>
                </div>
            </div>

            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
                <h3 style="font-family: var(--header-font); margin-bottom: 1rem;"><i class="fas fa-star"></i> Rewards</h3>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 0.5rem 0;"><i class="fas fa-user-plus" style="color: var(--info);"></i> Each new player who uses your code</td>
                        <td style="text-align: right; font-weight: 700; color: var(--success);">+50 Coins</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5rem 0; border-top: 1px solid var(--border);"><i class="fas fa-shopping-cart" style="color: var(--accent);"></i> When they buy with coins</td>
                        <td style="text-align: right; font-weight: 700; color: var(--success); border-top: 1px solid var(--border);">+10% Commission</td>
                    </tr>
                </table>
            </div>

            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
                <h3 style="font-family: var(--header-font); margin-bottom: 1rem;"><i class="fas fa-question-circle"></i> How It Works</h3>
                <ol style="color: var(--text-secondary); line-height: 2; padding-left: 1.2rem;">
                    <li>Login with your in-game account on Lost Roleplay Shop</li>
                    <li>Get your unique referral code from your profile</li>
                    <li>Share your code in your videos, streams, or social media</li>
                    <li>When someone logs in with your code, you earn <strong style="color: var(--success);">50 coins</strong></li>
                    <li>When they buy items using coins, you earn <strong style="color: var(--accent);">10% of their purchase</strong></li>
                    <li>Use your earned coins to buy items from the shop!</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<script>
function copyRef() {
    const input = document.getElementById('ref-code');
    input.select();
    document.execCommand('copy');
    const btn = input.nextElementSibling;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(() => btn.innerHTML = orig, 2000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
