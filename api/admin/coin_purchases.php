<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $purchaseId = (int)$_POST['purchase_id'];

    if ($_POST['action'] === 'confirm') {
        $purchase = $db->fetch("SELECT * FROM shop_coin_purchases WHERE id = ?", [$purchaseId]);
        if ($purchase && $purchase['status'] === 'pending') {
            $db->update('coin_purchases', ['status' => 'confirmed'], 'id = :id', ['id' => $purchaseId]);
            $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$purchase['coins'], $purchase['user_id']]);
            $db->insert('coin_transactions', [
                'user_id' => $purchase['user_id'],
                'amount' => $purchase['coins'],
                'type' => 'purchase',
                'description' => 'Bought ' . number_format($purchase['coins']) . ' coins - ' . number_format($purchase['amount_mad'], 0) . ' MAD via ' . $purchase['payment_method'],
            ]);
            \Core\Logger::info('Coin purchase confirmed', ['purchase_id' => $purchaseId, 'coins' => $purchase['coins']]);
            $msg = 'Purchase confirmed! ' . number_format($purchase['coins']) . ' coins added.';
        } else {
            $msg = 'Purchase not found or already processed.';
        }
    }

    if ($_POST['action'] === 'reject') {
        $note = trim($_POST['admin_note'] ?? '');
        $db->update('coin_purchases', ['status' => 'rejected', 'admin_note' => $note ?: null], 'id = :id', ['id' => $purchaseId]);
        \Core\Logger::info('Coin purchase rejected', ['purchase_id' => $purchaseId]);
        $msg = 'Purchase rejected.';
    }
}

$filter = $_GET['status'] ?? 'pending';
$sql = "SELECT cp.*, u.username FROM shop_coin_purchases cp JOIN shop_users u ON u.id = cp.user_id";
$params = [];

if ($filter === 'pending') {
    $sql .= " WHERE cp.status = 'pending'";
} elseif ($filter === 'confirmed') {
    $sql .= " WHERE cp.status = 'confirmed'";
} elseif ($filter === 'rejected') {
    $sql .= " WHERE cp.status = 'rejected'";
}

$sql .= " ORDER BY cp.created_at DESC";
$purchases = $db->fetchAll($sql, $params);
?>
<div class="admin-header">
    <h2><i class="fas fa-coins" style="color: var(--accent);"></i> Coin Purchases</h2>
    <div style="display:flex;gap:0.4rem;">
        <a href="coin_purchases.php?status=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-clock"></i> Pending</a>
        <a href="coin_purchases.php?status=confirmed" class="btn btn-sm <?= $filter === 'confirmed' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-check"></i> Confirmed</a>
        <a href="coin_purchases.php?status=rejected" class="btn btn-sm <?= $filter === 'rejected' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-times"></i> Rejected</a>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (empty($purchases)): ?>
    <div class="empty-state">
        <div class="icon"><i class="fas fa-coins"></i></div>
        <h3>No purchases</h3>
        <p>No <?= $filter ?> purchases found</p>
    </div>
<?php else: ?>
    <?php foreach ($purchases as $p): ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:1rem;">
                <div>
                    <h3 style="font-family:var(--header-font);"><?= htmlspecialchars($p['username']) ?></h3>
                    <div style="font-size:0.85rem;color:var(--text-secondary);margin-top:0.3rem;">
                        <strong style="color:var(--accent);font-size:1.1rem;"><?= number_format($p['coins']) ?> Coins</strong>
                        &mdash; <?= number_format($p['amount_mad'], 0) ?> MAD via <?= htmlspecialchars($p['payment_method']) ?>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.2rem;">
                        <?= date('Y-m-d H:i', strtotime($p['created_at'])) ?>
                    </div>
                </div>
                <div>
                    <span class="status status-<?= $p['status'] === 'confirmed' ? 'confirmed' : ($p['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </div>
            </div>

            <?php if ($p['proof_file']): ?>
                <div style="margin-top:1rem;">
                    <strong>Payment Proof:</strong><br>
                    <a href="../../uploads/proofs/<?= htmlspecialchars($p['proof_file']) ?>" target="_blank" style="display:inline-block;margin-top:0.3rem;">
                        <img src="../../uploads/proofs/<?= htmlspecialchars($p['proof_file']) ?>" alt="Proof" style="max-height:200px;border-radius:8px;border:1px solid var(--border);">
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($p['admin_note']): ?>
                <div style="margin-top:0.5rem;font-size:0.85rem;color:var(--text-secondary);">
                    <i class="fas fa-comment"></i> <?= htmlspecialchars($p['admin_note']) ?>
                </div>
            <?php endif; ?>

            <?php if ($p['status'] === 'pending'): ?>
                <div style="display:flex;gap:0.5rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Confirm this purchase?')">
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Confirm & Add Coins</button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this purchase?')">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                        <input type="text" name="admin_note" placeholder="Reason (optional)" class="form-control" style="width:200px;display:inline;padding:4px 8px;font-size:0.8rem;">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Reject</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
