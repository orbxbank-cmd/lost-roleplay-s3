<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$message = '';
$messageType = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_orders'])) {
    try {
        $pdo = $db->getPdo();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE shop_deliveries");
        $pdo->exec("TRUNCATE TABLE shop_order_items");
        $pdo->exec("TRUNCATE TABLE shop_orders");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $message = 'All orders reset successfully!';
        $messageType = 'success';
    } catch (\Exception $e) {
        $message = 'Error resetting orders: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_coins'])) {
    try {
        $pdo = $db->getPdo();
        $pdo->exec("UPDATE shop_users SET coins = 0 WHERE is_admin = 0");
        $pdo->exec("TRUNCATE TABLE shop_coin_transactions");
        $pdo->exec("TRUNCATE TABLE shop_coin_purchases");
        $message = 'All user coins reset to 0!';
        $messageType = 'success';
    } catch (\Exception $e) {
        $message = 'Error resetting coins: ' . $e->getMessage();
    }
}
?>
<div class="admin-header">
    <h2><i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i> Reset Tools</h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="padding:1rem;border-radius:var(--radius);margin-bottom:1.5rem;background:<?= $messageType === 'success' ? 'rgba(40,167,69,0.15)' : 'rgba(220,53,69,0.15)' ?>;border:1px solid <?= $messageType === 'success' ? 'var(--success)' : 'var(--danger)' ?>;color:var(--text-primary);">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;">
        <h3 style="color:var(--danger);margin-bottom:1rem;"><i class="fas fa-shopping-cart"></i> Reset All Orders</h3>
        <p style="color:var(--text-secondary);margin-bottom:1rem;">This will permanently delete all orders, order items, and deliveries. This action cannot be undone.</p>
        <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL orders? This cannot be undone!')">
            <button type="submit" name="reset_orders" class="btn" style="background:var(--danger);color:#fff;border:none;padding:0.7rem 1.5rem;border-radius:var(--radius);cursor:pointer;">
                <i class="fas fa-trash"></i> Delete All Orders
            </button>
        </form>
    </div>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;">
        <h3 style="color:var(--danger);margin-bottom:1rem;"><i class="fas fa-coins"></i> Reset All Coins</h3>
        <p style="color:var(--text-secondary);margin-bottom:1rem;">This will set all users' coins to 0 (except admins) and delete all coin purchases & transactions. This cannot be undone.</p>
        <form method="POST" onsubmit="return confirm('Are you sure you want to reset ALL coins to 0? This cannot be undone!')">
            <button type="submit" name="reset_coins" class="btn" style="background:var(--danger);color:#fff;border:none;padding:0.7rem 1.5rem;border-radius:var(--radius);cursor:pointer;">
                <i class="fas fa-undo"></i> Reset All Coins to 0
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
