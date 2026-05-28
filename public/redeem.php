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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));

    if (empty($code)) {
        $error = 'Please enter a gift card code.';
    } else {
        $card = $db->fetch("SELECT * FROM shop_gift_cards WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())", [$code]);

        if (!$card) {
            $error = 'Invalid or expired gift card code.';
        } elseif ($card['used_by']) {
            $error = 'This gift card has already been used.';
        } else {
            $db->query("UPDATE shop_gift_cards SET used_by = ?, used_at = NOW(), is_active = 0 WHERE id = ?", [$user['id'], $card['id']]);
            $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$card['coins'], $user['id']]);
            $db->insert('coin_transactions', [
                'user_id' => $user['id'],
                'amount' => $card['coins'],
                'type' => 'gift_card',
                'description' => "Gift card redeemed: $code",
            ]);
            \Core\Logger::info("Gift card $code redeemed", ['username' => $user['username'], 'coins' => $card['coins']]);
            $success = "Gift card redeemed successfully! You received {$card['coins']} coins.";
        }
    }
}

$history = $db->fetchAll("SELECT * FROM shop_coin_transactions WHERE user_id = ? AND type = 'gift_card' ORDER BY created_at DESC", [$user['id']]);

require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
    <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-gift"></i> Redeem Gift Card</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Enter a gift card code to receive free coins</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div style="max-width: 500px;">
        <form method="POST" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem;">
            <div class="form-group">
                <label><i class="fas fa-key"></i> Gift Card Code</label>
                <input type="text" name="code" class="form-control" required placeholder="Enter code (e.g. LOST-XXXX-XXXX)" style="text-transform: uppercase; text-align: center; font-size: 1.2rem; letter-spacing: 2px;" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;"><i class="fas fa-gift"></i> Redeem</button>
        </form>
    </div>

    <?php if ($history): ?>
        <h3 style="margin-top: 2rem;">Redemption History</h3>
        <div class="table-container" style="margin-top: 1rem;">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Coins</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><span style="font-family: monospace;"><?= htmlspecialchars($h['description']) ?></span></td>
                            <td style="color: var(--accent); font-weight: 700;">+<?= $h['amount'] ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($h['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
