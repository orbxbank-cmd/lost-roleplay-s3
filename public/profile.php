<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();
\Core\Auth::requireAuth();

$db = \Core\Database::getInstance();
$config = require __DIR__ . '/../config/app.php';
$user = \Core\Auth::user();
$error = '';
$success = '';

// Avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $error = 'Invalid file type. Allowed: jpg, png, gif, webp';
    } elseif ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
        $error = 'File too large. Max 2MB';
    } else {
        $filename = 'user_' . $user['id'] . '_' . time() . '.' . $ext;
        $dest = __DIR__ . '/../uploads/avatars/' . $filename;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            if ($user['avatar']) {
                $oldFile = __DIR__ . '/../uploads/avatars/' . $user['avatar'];
                if (file_exists($oldFile)) @unlink($oldFile);
            }
            $db->update('users', ['avatar' => $filename], 'id = :id', ['id' => $user['id']]);
            $user['avatar'] = $filename;
            $success = 'Avatar updated successfully';
        } else {
            $error = 'Upload failed';
        }
    }
}

// Coin purchase with proof
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_coins'])) {
    $coins = (int)$_POST['coin_amount'];
    $amountMad = (float)$_POST['price_mad'];
    $paymentMethod = trim($_POST['payment_method'] ?? '');

    $validPackage = false;
    foreach ($config['coin_packages'] as $pkg) {
        if ((int)$pkg['coins'] === $coins && (float)$pkg['price'] === $amountMad) {
            $validPackage = true;
            break;
        }
    }

    if (!$validPackage || empty($paymentMethod)) {
        $error = 'Invalid package or payment method';
    } elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a payment proof screenshot';
    } else {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $error = 'Invalid file type. Allowed: jpg, png, gif, webp, pdf';
        } elseif ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
            $error = 'File too large. Max 5MB';
        } else {
            $filename = 'proof_' . $user['id'] . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../uploads/proofs/' . $filename;
            if (move_uploaded_file($_FILES['proof']['tmp_name'], $dest)) {
                $db->insert('coin_purchases', [
                    'user_id' => $user['id'],
                    'coins' => $coins,
                    'amount_mad' => $amountMad,
                    'payment_method' => $paymentMethod,
                    'proof_file' => $filename,
                    'status' => 'pending',
                ]);
                \Core\Logger::info('Coin purchase submitted', ['user_id' => $user['id'], 'coins' => $coins]);
                $success = 'Purchase submitted! Coins will be added after confirmation.';
            } else {
                $error = 'Upload failed. Please try again.';
            }
        }
    }
}

// Remove avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
    if ($user['avatar']) {
        $oldFile = __DIR__ . '/../uploads/avatars/' . $user['avatar'];
        if (file_exists($oldFile)) @unlink($oldFile);
        $db->update('users', ['avatar' => null], 'id = :id', ['id' => $user['id']]);
        $user['avatar'] = null;
        $success = 'Avatar removed';
    }
}

$transactions = $db->fetchAll(
    "SELECT * FROM shop_coin_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
    [$user['id']]
);

$purchases = $db->fetchAll(
    "SELECT * FROM shop_coin_purchases WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
    [$user['id']]
);

// Get referral info
$referralCode = $user['referral_code'] ?? '';
$referralCount = $db->fetch("SELECT COUNT(*) as cnt FROM shop_users WHERE referred_by = ?", [$user['id']])['cnt'] ?? 0;
$referralEarnings = $user['total_referral_earnings'] ?? 0;
$referralTransactions = $db->fetchAll(
    "SELECT * FROM shop_referral_transactions WHERE referrer_id = ? ORDER BY created_at DESC LIMIT 10",
    [$user['id']]
);

// Fetch game stats from game DB
$gameStats = null;
$gameConfigPath = __DIR__ . '/../config/game_database.php';
if (file_exists($gameConfigPath)) {
    $cfg = require $gameConfigPath;
    if (!empty($cfg['host'])) {
        try {
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
            $gameDb = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $stmt = $gameDb->prepare("SELECT * FROM shop_users WHERE username = ? LIMIT 1");
            $stmt->execute([$user['username']]);
            $gameUser = $stmt->fetch();
            if ($gameUser) {
                $userCols = $gameDb->query("SHOW COLUMNS FROM shop_users")->fetchAll(\PDO::FETCH_COLUMN, 0);
                $statKeys = ['level', 'adminlevel', 'hours', 'cash', 'bank', 'job', 'kills', 'deaths', 'wanted', 'respect', 'score'];
                $gameStats = [];
                foreach ($statKeys as $k) {
                    if (in_array($k, $userCols) && isset($gameUser[$k]) && $gameUser[$k] !== '' && $gameUser[$k] !== null) {
                        $gameStats[$k] = $gameUser[$k];
                    }
                }
            }
        } catch (\Exception $e) {
            $gameStats = null;
        }
    }
}

$avatarUrl = $user['avatar'] ? '../uploads/avatars/' . $user['avatar'] : null;

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width: 900px;">
        <h2 class="section-title"><i class="fas fa-user-circle title-accent"></i> My Profile</h2>
        <p class="section-subtitle">Manage your account and coins</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="profile-layout">
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <div class="avatar-wrapper">
                        <?php if ($avatarUrl): ?>
                            <img src="<?= $avatarUrl ?>" alt="Avatar" id="avatar-preview">
                        <?php else: ?>
                            <div class="avatar-placeholder"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" style="margin-top: 0.8rem;">
                        <label class="btn btn-sm btn-secondary" style="cursor:pointer;width:100%;">
                            <i class="fas fa-camera"></i> Change Photo
                            <input type="file" name="avatar" accept="image/*" style="display:none;" onchange="this.form.submit()">
                        </label>
                    </form>
                    <?php if ($user['avatar']): ?>
                    <form method="POST" style="margin-top: 0.4rem;">
                        <button type="submit" name="remove_avatar" class="btn btn-sm btn-danger" style="width:100%;">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="profile-info">
                    <h3>
                        <?= htmlspecialchars($user['username']) ?>
                        <?php if (!empty($user['is_youtuber'])): ?>
                            <span style="display:inline-block;background:#ff4444;color:#fff;font-size:0.7rem;padding:2px 8px;border-radius:4px;font-weight:700;vertical-align:middle;"><i class="fab fa-youtube"></i> YouTuber</span>
                        <?php endif; ?>
                    </h3>
                    <?php if ($user['ingame_name']): ?>
                        <p class="text-muted"><i class="fas fa-gamepad"></i> <?= htmlspecialchars($user['ingame_name']) ?></p>
                    <?php endif; ?>
                    <?php if ($user['email']): ?>
                        <p class="text-muted"><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                    <?php endif; ?>
                    <?php if ($user['phone']): ?>
                        <p class="text-muted"><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></p>
                    <?php endif; ?>
                    <p class="text-muted"><i class="fas fa-calendar"></i> Joined: <?= date('Y-m-d', strtotime($user['created_at'] ?? 'now')) ?></p>
                </div>
            </div>

            <div class="profile-main">
                <div class="coins-card">
                    <div class="coins-icon"><i class="fas fa-coins"></i></div>
                    <div class="coins-balance">
                        <span class="coins-amount"><?= number_format($user['coins']) ?></span>
                        <span class="coins-label">Coins</span>
                    </div>
                </div>

                <?php if ($gameStats): ?>
                <div class="profile-game-section">
                    <h3 class="section-subtitle"><i class="fas fa-gamepad"></i> Game Stats</h3>
                    <div class="stats-grid">
                        <?php
                        $statConfig = [
                            'level' => ['icon' => 'fa-star', 'color' => 'var(--accent)'],
                            'adminlevel' => ['icon' => 'fa-shield-halved', 'color' => 'var(--danger)'],
                            'hours' => ['icon' => 'fa-clock', 'color' => 'var(--info)'],
                            'cash' => ['icon' => 'fa-money-bill', 'color' => 'var(--success)'],
                            'bank' => ['icon' => 'fa-building-columns', 'color' => 'var(--success)'],
                            'job' => ['icon' => 'fa-briefcase', 'color' => 'var(--primary)'],
                            'kills' => ['icon' => 'fa-crosshairs', 'color' => 'var(--danger)'],
                            'deaths' => ['icon' => 'fa-skull', 'color' => 'var(--text-muted)'],
                            'wanted' => ['icon' => 'fa-handcuffs', 'color' => 'var(--warning)'],
                            'respect' => ['icon' => 'fa-thumbs-up', 'color' => 'var(--success)'],
                            'score' => ['icon' => 'fa-trophy', 'color' => 'var(--accent)'],
                        ];
                        foreach ($gameStats as $key => $val):
                            $cfg = $statConfig[$key] ?? ['icon' => 'fa-circle', 'color' => 'var(--primary)'];
                            $icon = $cfg['icon'];
                            $color = $cfg['color'];
                            $label = ucfirst($key);
                            if ($key === 'adminlevel') $label = 'Admin Level';
                            $display = is_numeric($val) ? number_format((int)$val) : htmlspecialchars($val);
                            if ($key === 'hours') $display = number_format((float)$val, 1);
                            if (in_array($key, ['cash', 'bank'])) $display = '$' . $display;
                        ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="color: <?= $color ?>;"><i class="fas <?= $icon ?>"></i></div>
                            <div class="stat-value" style="color: <?= $color ?>;"><?= $display ?></div>
                            <div class="stat-label"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($referralCode): ?>
                <div style="margin-top: 1.5rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
                    <h3 style="font-family: var(--header-font); margin-bottom: 0.8rem;"><i class="fas fa-gift" style="color: var(--info);"></i> Referral Program</h3>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        Share your code with content creators & friends. Earn coins and badges when they sign up!
                    </p>
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.8rem;">
                        <input type="text" id="referral-code-input" class="form-control" value="<?= htmlspecialchars($referralCode) ?>" readonly style="width: 150px; text-align: center; font-weight: 800; font-size: 1.1rem; letter-spacing: 2px;">
                        <button class="btn btn-primary btn-sm" onclick="copyReferralCode()" style="white-space: nowrap;"><i class="fas fa-copy"></i> Copy</button>
                        <a href="https://wa.me/?text=Join%20Lost%20Roleplay%20S03!%20Use%20my%20referral%20code%3A%20<?= urlencode($referralCode) ?>%20at%20http://localhost/lost-roleplay-shop/public/login.php" target="_blank" class="btn btn-success btn-sm" style="white-space: nowrap;"><i class="fab fa-whatsapp"></i> Share</a>
                    </div>
                    <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem; padding-top: 0.8rem; border-top: 1px solid var(--border);">
                        <div style="text-align: center;">
                            <div style="font-size: 1.3rem; font-weight: 800; color: var(--accent);"><?= $referralCount ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">People Referred</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.3rem; font-weight: 800; color: var(--success);"><i class="fas fa-coins"></i> <?= number_format($referralEarnings) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Coins Earned</div>
                        </div>
                    </div>
                    <?php
                    $tier = '';
                    $tierColor = '';
                    $tierIcon = '';
                    if ($referralCount >= 10) { $tier = 'Top Referrer'; $tierColor = '#0d6efd'; $tierIcon = 'crown'; }
                    elseif ($referralCount >= 5) { $tier = 'Star Referrer'; $tierColor = '#0dcaf0'; $tierIcon = 'star'; }
                    elseif ($referralCount >= 1) { $tier = 'Referrer'; $tierColor = '#198754'; $tierIcon = 'user-plus'; }
                    if ($tier): ?>
                    <div style="margin-top: 0.5rem; text-align: center; padding: 0.5rem; background: <?= $tierColor ?>15; border: 1px solid <?= $tierColor ?>40; border-radius: var(--radius);">
                        <span style="display:inline-block;background:<?= $tierColor ?>;color:#000;padding:2px 12px;border-radius:4px;font-weight:700;font-size:0.85rem;"><i class="fas fa-<?= $tierIcon ?>"></i> <?= $tier ?></span>
                        <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:0.5rem;">(<?= $referralCount ?> referrals)</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($referralCount < 10): ?>
                    <div style="margin-top: 0.5rem; font-size:0.78rem;color:var(--text-muted);text-align:center;">
                        Next badge at <?= $referralCount < 5 ? '5' : '10' ?> referrals
                        <div style="background:var(--bg-input);height:4px;border-radius:2px;margin-top:3px;">
                            <div style="background:var(--accent);height:4px;border-radius:2px;width:<?= min(100, ($referralCount / ($referralCount < 5 ? 5 : 10)) * 100) ?>%;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($referralTransactions)): ?>
                        <div style="margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid var(--border);">
                            <div style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Recent Activity</div>
                            <?php foreach (array_slice($referralTransactions, 0, 5) as $rt): ?>
                                <div style="font-size: 0.78rem; color: var(--text-secondary); display: flex; justify-content: space-between; padding: 0.2rem 0;">
                                    <span><i class="fas fa-user-plus"></i> <?= htmlspecialchars($rt['referred_username']) ?></span>
                                    <span style="color: var(--success);">+<?= $rt['coins'] ?> coins</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top: 2rem;">
                    <h3 class="section-subtitle" style="text-align:left;margin-bottom:1rem;"><i class="fas fa-cart-plus"></i> Add Funds</h3>
                    <div class="coin-packages">
                        <?php foreach ($config['coin_packages'] as $pkg): ?>
                            <div class="coin-package">
                                <div class="cp-coins"><?= number_format($pkg['coins']) ?></div>
                                <div class="cp-price"><?= number_format($pkg['price']) ?> MAD</div>
                                <button class="btn btn-primary btn-sm buy-coins"
                                        data-amount="<?= $pkg['coins'] ?>"
                                        data-price="<?= $pkg['price'] ?>">
                                    <i class="fas fa-shopping-cart"></i> Buy
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="payment-instructions" style="margin-top: 1.5rem;">
                        <h4><i class="fas fa-upload"></i> Purchase Coins</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="buy_coins" value="1">
                            <input type="hidden" name="coin_amount" id="coin-amount-input">
                            <input type="hidden" name="price_mad" id="price-mad-input">
                            <div class="form-group">
                                <label>Selected Package</label>
                                <div id="selected-pkg-display" style="background:var(--bg-dark);padding:0.5rem 0.8rem;border-radius:6px;font-weight:700;">Choose a package above</div>
                            </div>
                            <div class="form-group">
                                <label>Payment Method *</label>
                                <select name="payment_method" class="form-control" required>
                                    <option value="">-- Select --</option>
                                    <option value="inwi">Inwi</option>
                                    <option value="cashplus">Cash Plus</option>
                                    <option value="wafacash">Wafacash</option>
                                    <option value="cih">CIH Bank</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Payment Proof (screenshot) *</label>
                                <input type="file" name="proof" class="form-control" accept="image/*,.pdf" required>
                                <small style="color:var(--text-muted);">Max 5MB. Accepted: jpg, png, gif, webp, pdf</small>
                            </div>
                            <div class="form-group">
                                <label>Send amount to Inwi: <strong style="color:var(--info);font-size:1.1rem;"><?= $config['contact_phone'] ?></strong></label>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%;">
                                <i class="fas fa-paper-plane"></i> Submit Purchase
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($purchases)): ?>
                <div style="margin-top: 2rem;">
                    <h3 class="section-subtitle" style="text-align:left;margin-bottom:1rem;"><i class="fas fa-clock"></i> Purchase History</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Coins</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $p): ?>
                                    <tr>
                                        <td><?= date('Y-m-d H:i', strtotime($p['created_at'])) ?></td>
                                        <td><strong><?= number_format($p['coins']) ?></strong></td>
                                        <td><?= number_format($p['amount_mad'], 0) ?> MAD</td>
                                        <td><?= htmlspecialchars($p['payment_method']) ?></td>
                                        <td>
                                            <span class="status status-<?= $p['status'] === 'confirmed' ? 'confirmed' : ($p['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                                                <?= ucfirst($p['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <div style="margin-top: 2rem;">
                    <h3 class="section-subtitle" style="text-align:left;margin-bottom:1rem;"><i class="fas fa-history"></i> Coin History</h3>
                    <?php if (empty($transactions)): ?>
                        <div class="empty-state" style="padding:2rem;">
                            <div class="icon"><i class="fas fa-coins"></i></div>
                            <h3>No transactions</h3>
                            <p>Your coin transactions will appear here</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td><?= date('Y-m-d H:i', strtotime($tx['created_at'])) ?></td>
                                            <td>
                                                <?php
                                                    $typeLabels = ['bonus' => 'Bonus', 'purchase' => 'Purchase', 'payment' => 'Payment', 'admin' => 'Admin'];
                                                    $typeColors = ['bonus' => 'var(--success)', 'purchase' => 'var(--info)', 'payment' => 'var(--accent)', 'admin' => 'var(--warning)'];
                                                ?>
                                                <span style="color:<?= $typeColors[$tx['type']] ?? 'var(--text-muted)' ?>;font-weight:600;">
                                                    <?= $typeLabels[$tx['type']] ?? ucfirst($tx['type']) ?>
                                                </span>
                                            </td>
                                            <td style="color: <?= $tx['amount'] > 0 ? 'var(--success)' : 'var(--danger)' ?>; font-weight:700;">
                                                <?= $tx['amount'] > 0 ? '+' : '' ?><?= $tx['amount'] ?>
                                            </td>
                                            <td><?= htmlspecialchars($tx['description'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function copyReferralCode() {
    const input = document.getElementById('referral-code-input');
    input.select();
    document.execCommand('copy');
    const btn = input.nextElementSibling;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(() => btn.innerHTML = orig, 2000);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.buy-coins').forEach(btn => {
        btn.addEventListener('click', function() {
            const coins = this.dataset.amount;
            const price = this.dataset.price;
            document.getElementById('coin-amount-input').value = coins;
            document.getElementById('price-mad-input').value = price;
            document.getElementById('selected-pkg-display').textContent = coins + ' Coins — ' + price + ' MAD';
            document.getElementById('selected-pkg-display').style.borderColor = 'var(--accent)';
            document.querySelector('[name="payment_method"]').focus();
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
