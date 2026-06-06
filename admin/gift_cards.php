<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$message = '';
$messageType = 'success';
$cards = [];
$tableExists = true;

// Check if table exists
try {
    $db->fetch("SELECT 1 FROM shop_gift_cards LIMIT 1");
} catch (\Exception $e) {
    $tableExists = false;
    $message = 'The gift_cards table does not exist. Run the migration SQL to create it.';
    $messageType = 'danger';
}

if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $count = max(1, (int)($_POST['count'] ?? 1));
        $coins = max(10, (int)($_POST['coins'] ?? 100));
        $prefix = strtoupper(trim($_POST['prefix'] ?? 'LOST'));

        for ($i = 0; $i < $count; $i++) {
            $code = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $db->insert('gift_cards', [
                'code' => $code,
                'coins' => $coins,
                'created_by' => \Core\Auth::userId(),
            ]);
        }
        \Core\Logger::info("Generated $count gift cards ($coins coins each)");
        $message = "Generated $count gift card(s) worth $coins coins each.";
    }

    if ($action === 'clear_all') {
        $db->query("DELETE FROM shop_gift_cards");
        \Core\Logger::info("All gift cards deleted by admin");
        $message = "All gift cards have been deleted.";
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $card = $db->fetch("SELECT * FROM shop_gift_cards WHERE id = ?", [$id]);
        if ($card && !$card['used_by']) {
            $newStatus = $card['is_active'] ? 0 : 1;
            $db->update('gift_cards', ['is_active' => $newStatus], 'id = :id', ['id' => $id]);
            $message = "Gift card #$id " . ($newStatus ? 'activated' : 'deactivated');
        }
    }
}

if ($tableExists) {
    try {
        $cards = $db->fetchAll("SELECT gc.*, creator.username as creator_name, redeemer.username as redeemer_name FROM shop_gift_cards gc LEFT JOIN shop_users creator ON gc.created_by = creator.id LEFT JOIN shop_users redeemer ON gc.used_by = redeemer.id ORDER BY gc.created_at DESC LIMIT 100");
    } catch (\Exception $e) {
        $message = 'Error loading gift cards: ' . $e->getMessage();
        $messageType = 'danger';
    }
}
?>
<div class="admin-header">
    <h2><i class="fas fa-gift"></i> Gift Cards</h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 2rem;">
    <div>
        <h3 style="margin-bottom: 1rem;">Generate Gift Cards</h3>
        <form method="POST" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
            <input type="hidden" name="action" value="generate">
            <div class="form-group">
                <label>Coins per Card</label>
                <input type="number" name="coins" class="form-control" value="100" min="10" required>
            </div>
            <div class="form-group">
                <label>Code Prefix</label>
                <input type="text" name="prefix" class="form-control" value="LOST" placeholder="LOST">
            </div>
            <div class="form-group">
                <label>Number of Cards</label>
                <input type="number" name="count" class="form-control" value="1" min="1" max="50">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-plus"></i> Generate</button>
        </form>

        <?php if ($tableExists && !empty($cards)): ?>
            <form method="POST" style="margin-top: 1rem;" onsubmit="return confirm('Delete ALL gift cards? This cannot be undone.');">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-danger" style="width: 100%;"><i class="fas fa-trash"></i> Clear All Cards</button>
            </form>
        <?php endif; ?>
    </div>

    <div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Coins</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th>Used By</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cards)): ?>
                        <tr><td colspan="8" style="text-align: center;">No gift cards yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($cards as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td><code><?= htmlspecialchars($c['code']) ?></code></td>
                                <td style="font-weight: 700; color: var(--accent);"><?= $c['coins'] ?></td>
                                <td><?= htmlspecialchars($c['creator_name'] ?? 'Admin') ?></td>
                                <td>
                                    <?php if ($c['used_by']): ?>
                                        <span class="status status-completed">Used</span>
                                    <?php elseif ($c['is_active']): ?>
                                        <span class="status status-processing">Active</span>
                                    <?php else: ?>
                                        <span class="status status-rejected">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($c['redeemer_name'] ?? '-') ?></td>
                                <td><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                                <td>
                                    <?php if (!$c['used_by']): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary">
                                                <?= $c['is_active'] ? 'Disable' : 'Enable' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
