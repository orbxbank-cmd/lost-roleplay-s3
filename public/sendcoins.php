<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();
$db = \Core\Database::getInstance();
$loggedIn = \Core\Auth::isLoggedIn();
$user = $loggedIn ? \Core\Auth::user() : null;

if (!$loggedIn) {
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container" style="max-width: 600px;">
            <h2 class="section-title"><i class="fas fa-paper-plane title-accent"></i> Send Coins</h2>
            <p class="section-subtitle">Transfer coins to another player</p>
            <div style="text-align:center;padding:2rem;">
                <div style="font-size:4rem;color:var(--accent);margin-bottom:1rem;"><i class="fas fa-paper-plane"></i></div>
                <p style="color:var(--text-secondary);margin-bottom:0.5rem;">Send coins to your friends and server mates instantly!</p>
                <p style="color:var(--text-muted);margin-bottom:1.5rem;font-size:0.85rem;">Just enter their username and amount — they'll receive it immediately.</p>
                <a href="login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt"></i> Login to Send Coins</a>
            </div>
        </div>
    </section>
    <?php require_once __DIR__ . '/includes/footer.php'; exit;
}

$error = '';
$success = '';
$gameConfig = require __DIR__ . '/../config/game_database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientUsername = trim($_POST['recipient'] ?? '');
    $amount = (int) ($_POST['amount'] ?? 0);

    if (empty($recipientUsername)) {
        $error = 'Enter the recipient username';
    } elseif ($amount < 1) {
        $error = 'Enter a valid amount';
    } elseif ($user['coins'] < $amount) {
        $error = "You don't have enough coins";
    } elseif (strtolower($recipientUsername) === strtolower($user['username'])) {
        $error = "You can't send coins to yourself";
    } else {
        $dsn = "mysql:host={$gameConfig['host']};port={$gameConfig['port']};dbname={$gameConfig['dbname']};charset={$gameConfig['charset']}";
        $gameDb = new \PDO($dsn, $gameConfig['username'], $gameConfig['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $stmt = $gameDb->prepare("SELECT uid, username FROM shop_users WHERE username = ? LIMIT 1");
        $stmt->execute([$recipientUsername]);
        $gameUser = $stmt->fetch();

        if (!$gameUser) {
            $error = 'Username not found in the server';
        } else {
            $recipient = $db->fetch("SELECT id FROM shop_users WHERE game_uid = ?", [$gameUser['uid']]);
            if (!$recipient) {
                $code = strtoupper(substr($gameUser['username'], 0, 8));
                $db->query("INSERT INTO shop_users (username, game_uid, coins, referral_code, created_at) VALUES (?, ?, 0, ?, NOW())", [$gameUser['username'], $gameUser['uid'], $code]);
                $recipient = $db->fetch("SELECT id FROM shop_users WHERE game_uid = ?", [$gameUser['uid']]);
            }

            if (!$recipient) {
                $error = 'Could not create account for recipient';
            } else {
                $pdo = $db->getPdo();
                $pdo->beginTransaction();
                try {
                    $stmt = $db->query("UPDATE shop_users SET coins = coins - ? WHERE id = ? AND coins >= ?", [$amount, $user['id'], $amount]);
                    if ($stmt->rowCount() === 0) {
                        throw new \Exception('Insufficient coins');
                    }
                    $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$amount, $recipient['id']]);
                    $db->query("INSERT INTO shop_coin_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, 'transfer', ?, NOW())", [$user['id'], -$amount, "Sent to {$gameUser['username']}"]);
                    $db->query("INSERT INTO shop_coin_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, 'transfer', ?, NOW())", [$recipient['id'], $amount, "Received from {$user['username']}"]);
                    $pdo->commit();
                    $success = 'Sent ' . number_format($amount) . ' coins to ' . htmlspecialchars($gameUser['username']) . ' successfully!';
                    $user = \Core\Auth::user();
                } catch (\Exception $e) {
                    $pdo->rollBack();
                    $error = 'Transaction failed: ' . $e->getMessage();
                }
            }
        }
    }
}
$user = \Core\Auth::user();
require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width: 600px;">
        <h2 class="section-title"><i class="fas fa-paper-plane title-accent"></i> Send Coins</h2>
        <p class="section-subtitle">Transfer coins to another player</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>

        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
            <div style="text-align: center; margin-bottom: 1rem;">
                <span style="font-size: 1.2rem;">Your balance: <strong style="color: var(--accent);"><i class="fas fa-coins"></i> <?= number_format($user['coins']) ?></strong></span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Recipient Username</label>
                    <input type="text" name="recipient" class="form-control" required placeholder="In-game username">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-coins"></i> Amount</label>
                    <input type="number" name="amount" class="form-control" required min="1" max="<?= $user['coins'] ?>" placeholder="Enter amount">
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> Send Coins
                </button>
            </form>
        </div>

        <?php
        $transfers = $db->fetchAll(
            "SELECT amount, description, created_at FROM shop_coin_transactions WHERE user_id = ? AND type = 'transfer' ORDER BY created_at DESC LIMIT 20",
            [$user['id']]
        );
        if ($transfers): ?>
        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 0.9rem; color: var(--text-secondary);"><i class="fas fa-history"></i> Recent Transfers</div>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead><tr style="border-bottom: 1px solid var(--border);"><th style="padding: 0.5rem 1rem; text-align: left;">Amount</th><th style="padding: 0.5rem 1rem; text-align: left;">Description</th><th style="padding: 0.5rem 1rem; text-align: left;">Date</th></tr></thead>
                <tbody>
                <?php foreach ($transfers as $tx): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 0.5rem 1rem; color: <?= $tx['amount'] > 0 ? 'var(--success)' : 'var(--danger)' ?>; font-weight: 600;"><?= $tx['amount'] > 0 ? '+' : '' ?><?= number_format($tx['amount']) ?></td>
                        <td style="padding: 0.5rem 1rem; color: var(--text-secondary);"><?= htmlspecialchars($tx['description']) ?></td>
                        <td style="padding: 0.5rem 1rem; color: var(--text-muted); font-size: 0.8rem;"><?= date('M j, g:i A', strtotime($tx['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
