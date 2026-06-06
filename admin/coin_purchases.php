<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();

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
                'description' => "Coin purchase confirmed ({$purchase['coins']} coins)"
            ]);
            \Core\Logger::info('Coin purchase confirmed', ['purchase_id' => $purchaseId, 'coins' => $purchase['coins']]);
        }
    }

    if ($_POST['action'] === 'reject') {
        $adminNote = trim($_POST['admin_note'] ?? '');
        $db->update('coin_purchases', ['status' => 'rejected', 'admin_note' => $adminNote], 'id = :id', ['id' => $purchaseId]);
        \Core\Logger::info('Coin purchase rejected', ['purchase_id' => $purchaseId]);
    }

    header("Location: coin_purchases.php");
    exit;
}

$purchases = $db->fetchAll("SELECT cp.*, u.username FROM coin_purchases cp LEFT JOIN shop_users u ON cp.user_id = u.id ORDER BY cp.created_at DESC");
?>
<div class="admin-header">
    <h2>🪙 Coin Purchase Requests</h2>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>User</th>
                <th>Coins</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Proof</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($purchases)): ?>
                <tr><td colspan="9" style="text-align: center;">No requests yet</td></tr>
            <?php else: ?>
                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['username'] ?? $p['user_id']) ?></strong></td>
                        <td style="font-weight: 700; color: var(--accent);">+<?= number_format($p['coins']) ?></td>
                        <td><?= number_format($p['amount_mad'], 0) ?> dh</td>
                        <td><?= htmlspecialchars($p['payment_method']) ?></td>
                        <td>
                            <?php if ($p['proof_file']): ?>
                                <a href="../uploads/proofs/<?= htmlspecialchars($p['proof_file']) ?>" target="_blank" class="btn btn-sm btn-secondary">View</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status status-<?= $p['status'] ?>">
                                <?= $p['status'] === 'pending' ? 'Pending' : ($p['status'] === 'confirmed' ? 'Confirmed' : 'Rejected') ?>
                            </span>
                        </td>
                        <td><?= date('Y-m-d H:i', strtotime($p['created_at'])) ?></td>
                        <td>
                            <?php if ($p['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="confirm">
                                    <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Confirm +<?= $p['coins'] ?> coins for <?= htmlspecialchars($p['username'] ?? '') ?>?')">Confirm</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this request?')">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                                    <input type="text" name="admin_note" placeholder="Reason" class="form-control" style="width:100px;display:inline;padding:3px 5px;font-size:0.8rem;">
                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            <?php elseif ($p['status'] === 'rejected' && $p['admin_note']): ?>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($p['admin_note']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
