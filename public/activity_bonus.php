<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();
\Core\Auth::requireAuth();

$db = \Core\Database::getInstance();
$app = require __DIR__ . '/../config/app.php';
$user = \Core\Auth::user();

$error = '';
$success = '';

// Get game DB
$gameDb = null;
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
        } catch (\Exception $e) {}
    }
}

$currentHours = 0;
if ($gameDb && $user['game_uid']) {
    try {
        $stmt = $gameDb->prepare("SELECT hours FROM players WHERE uid = ? LIMIT 1");
        $stmt->execute([$user['game_uid']]);
        $stats = $stmt->fetch();
        if ($stats) $currentHours = (float)($stats['hours'] ?? 0);
    } catch (\Exception $e) {}
}

$lastClaimedHours = (float)($user['last_activity_hours'] ?? 0);
$hoursSinceClaim = $currentHours - $lastClaimedHours;
$hoursNeeded = 5; // Claim every 5 hours
$canClaim = $hoursSinceClaim >= $hoursNeeded;
$progress = min(100, ($hoursSinceClaim / $hoursNeeded) * 100);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim'])) {
    if ($canClaim && $currentHours > 0) {
        $reward = floor($hoursSinceClaim / $hoursNeeded) * 10;
        if ($reward > 0) {
            $db->query("UPDATE shop_users SET coins = coins + ?, last_activity_hours = ? WHERE id = ?", [$reward, $currentHours, $user['id']]);
            $db->insert('coin_transactions', [
                'user_id' => $user['id'],
                'amount' => $reward,
                'type' => 'reward',
                'description' => "Activity bonus: $hoursSinceClaim hours played",
            ]);
            \Core\Logger::info("Activity bonus claimed", ['username' => $user['username'], 'coins' => $reward]);
            $success = "Bonus claimed! You earned $reward coins for your activity.";
            // Refresh
            $user = \Core\Auth::user();
            $lastClaimedHours = $currentHours;
            $hoursSinceClaim = 0;
            $canClaim = false;
            $progress = 0;
        }
    } else {
        $error = 'Play more hours to claim your bonus.';
    }
}

$history = $db->fetchAll("SELECT * FROM shop_coin_transactions WHERE user_id = ? AND type = 'reward' ORDER BY created_at DESC LIMIT 10", [$user['id']]);

require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
    <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-clock"></i> Activity Bonus</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Play on the server and earn free coins</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div style="max-width: 600px;">
        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <div style="font-size: 0.9rem; color: var(--text-muted);">Your Play Time</div>
                    <div style="font-size: 2rem; font-weight: 800;"><?= number_format($currentHours, 1) ?></div>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">hours</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.9rem; color: var(--text-muted);">Your Coins</div>
                    <div style="font-size: 2rem; font-weight: 800; color: var(--accent);"><?= number_format($user['coins']) ?></div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                    <span>Progress to next bonus (every <?= $hoursNeeded ?> hours)</span>
                    <span><?= number_format($hoursSinceClaim, 1) ?> / <?= $hoursNeeded ?> hours</span>
                </div>
                <div style="background: var(--bg-dark); border-radius: 50px; height: 12px; overflow: hidden;">
                    <div style="background: linear-gradient(90deg, var(--primary), var(--accent)); height: 100%; width: <?= $progress ?>%; border-radius: 50px; transition: width 0.5s;"></div>
                </div>
                <div style="text-align: center; margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
                    <i class="fas fa-coins" style="color: var(--accent);"></i> Reward: <strong>10 coins</strong> per <?= $hoursNeeded ?> hours
                </div>
            </div>

            <form method="POST">
                <button type="submit" name="claim" value="1" class="btn btn-lg" style="width: 100%; background: linear-gradient(135deg, var(--primary), var(--accent)); color: #000; <?= !$canClaim ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>" <?= !$canClaim ? 'disabled' : '' ?>>
                    <i class="fas fa-gift"></i> <?= $canClaim ? 'Claim Bonus!' : 'Keep playing to unlock bonus' ?>
                </button>
            </form>
        </div>

        <?php if ($history): ?>
            <h3 style="margin-top: 2rem;">Bonus History</h3>
            <div class="table-container" style="margin-top: 1rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td style="color: var(--accent); font-weight: 700;">+<?= $h['amount'] ?> coins</td>
                                <td><?= htmlspecialchars($h['description']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($h['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
