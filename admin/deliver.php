<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$message = '';
$messageType = 'success';

$products = $db->fetchAll("SELECT id, name, category_id, price, coin_price FROM shop_products WHERE is_active = 1 ORDER BY category_id, name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deliver'])) {
    $ingameName = trim($_POST['ingame_name']);
    $productId = (int)$_POST['product_id'];
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $adminNote = trim($_POST['admin_note'] ?? '');

    if (empty($ingameName) || !$productId) {
        $message = 'Please fill all fields';
        $messageType = 'danger';
    } else {
        try {
            $cfg = require __DIR__ . '/../config/game_database.php';
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
            $gameDb = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            $stmt = $gameDb->prepare("SELECT uid, username FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$ingameName]);
            $player = $stmt->fetch();

            if (!$player) {
                $message = "Player '$ingameName' not found in game DB";
                $messageType = 'danger';
            } else {
                $product = $db->fetch("SELECT * FROM shop_products WHERE id = ?", [$productId]);
                if (!$product) {
                    $message = 'Product not found';
                    $messageType = 'danger';
                } else {
                    require_once __DIR__ . '/../core/GameDelivery.php';
                    $delivery = new \Core\GameDelivery();

                    // Insert delivery record (order_id=0 means admin direct)
                    $deliveryId = $db->insert('deliveries', [
                        'order_id' => 0,
                        'ingame_name' => $ingameName,
                        'product_name' => $product['name'],
                        'quantity' => $quantity,
                        'status' => 'pending',
                    ]);

                    // Process via GameDelivery
                    $result = $delivery->processDeliveryByIdDirect($deliveryId);

                    $adminUser = \Core\Auth::user();
                    \Core\Logger::info('Admin direct delivery', [
                        'admin' => $adminUser['username'],
                        'player' => $ingameName,
                        'product' => $product['name'],
                        'quantity' => $quantity,
                        'result' => $result,
                    ]);

                    if ($result['success']) {
                        $message = "Delivered {$quantity}x {$product['name']} to $ingameName!";
                    } else {
                        $message = 'Delivery failed: ' . ($result['error'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                }
            }
        } catch (\Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>
<div class="admin-header">
    <h2><i class="fas fa-truck"></i> Direct Product Delivery</h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;max-width:600px;">
    <form method="POST">
        <div class="form-group">
            <label>In-Game Name</label>
            <input type="text" name="ingame_name" class="form-control" required placeholder="Enter player's in-game name">
        </div>
        <div class="form-group">
            <label>Product</label>
            <select name="product_id" class="form-control" required>
                <option value="">-- Select Product --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['price'] > 0 ? $p['price'] . ' DH' : $p['coin_price'] . ' coins' ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" class="form-control" value="1" min="1" max="99">
        </div>
        <div class="form-group">
            <label>Admin Note (optional)</label>
            <input type="text" name="admin_note" class="form-control" placeholder="Reason for delivery">
        </div>
        <button type="submit" name="deliver" class="btn btn-success"><i class="fas fa-paper-plane"></i> Deliver Now</button>
    </form>
</div>

<div style="margin-top:2rem;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;">
    <h3 style="font-size:0.9rem;margin-bottom:0.5rem;"><i class="fas fa-clock"></i> Recent Admin Deliveries</h3>
    <?php
    $recent = $db->fetchAll("SELECT * FROM shop_deliveries WHERE order_id = 0 ORDER BY created_at DESC LIMIT 20");
    ?>
    <div class="table-container">
        <table>
            <thead><tr><th>Date</th><th>Player</th><th>Product</th><th>Qty</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $d): ?>
                    <tr>
                        <td><?= date('Y-m-d H:i', strtotime($d['created_at'])) ?></td>
                        <td><strong><?= htmlspecialchars($d['ingame_name']) ?></strong></td>
                        <td><?= htmlspecialchars($d['product_name']) ?></td>
                        <td><?= $d['quantity'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No direct deliveries yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
