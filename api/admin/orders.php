<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $orderId = (int)$_POST['order_id'];

    if ($_POST['action'] === 'update_order_status') {
        $status = $_POST['order_status'];
        $db->update('orders', ['order_status' => $status], 'id = :id', ['id' => $orderId]);

        if ($status === 'processing') {
            $items = $db->fetchAll("SELECT * FROM shop_order_items WHERE order_id = ?", [$orderId]);
            $order = $db->fetch("SELECT * FROM shop_orders WHERE id = ?", [$orderId]);
            foreach ($items as $item) {
                $existing = $db->fetch("SELECT id FROM shop_deliveries WHERE order_id = ? AND product_name = ?", [$orderId, $item['product_name']]);
                if (!$existing) {
                    $db->insert('deliveries', [
                        'order_id' => $orderId,
                        'user_id' => $order['user_id'],
                        'ingame_name' => $order['ingame_name'] ?: $order['customer_name'],
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'status' => 'pending',
                    ]);
                }
            }

            require_once __DIR__ . '/../../core/GameDelivery.php';
            try {
                $gameDelivery = new \Core\GameDelivery();
                if ($gameDelivery->isConnected()) {
                    $deliveryResults = $gameDelivery->processByOrderId($orderId);
                    $successCount = count(array_filter($deliveryResults, fn($r) => $r['success']));
                    $failCount = count($deliveryResults) - $successCount;
                    if ($failCount > 0) {
                        \Core\Logger::info('Some admin order deliveries failed', [
                            'order_id' => $orderId,
                            'success' => $successCount,
                            'failed' => $failCount,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Core\Logger::error('GameDelivery error on admin orders', ['message' => $e->getMessage()]);
            }
        }

        \Core\Logger::info('Order status updated', ['order_id' => $orderId, 'status' => $status]);
        header("Location: orders.php?msg=updated");
        exit;
    }

    if ($_POST['action'] === 'update_payment_status') {
        $status = $_POST['payment_status'];
        $db->update('orders', ['payment_status' => $status], 'id = :id', ['id' => $orderId]);
        \Core\Logger::info('Payment status updated', ['order_id' => $orderId, 'status' => $status]);
        header("Location: orders.php?msg=updated");
        exit;
    }

    if ($_POST['action'] === 'deliver_now') {
        require_once __DIR__ . '/../../core/GameDelivery.php';
        $successCount = 0;
        $failCount = 0;
        try {
            $gameDelivery = new \Core\GameDelivery();
            if ($gameDelivery->isConnected()) {
                $deliveryResults = $gameDelivery->processByOrderId($orderId);
                foreach ($deliveryResults as $result) {
                    if ($result['success']) $successCount++;
                    else $failCount++;
                }
            } else {
                $msg = 'Game DB not connected';
            }
        } catch (\Exception $e) {
            \Core\Logger::error('Deliver now error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            $msg = 'Delivery error: ' . $e->getMessage();
        }
        if (!isset($msg)) {
            $msg = "Delivery processed: $successCount succeeded, $failCount failed";
        }
        header("Location: orders.php?msg=" . urlencode($msg));
        exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$message = $_GET['msg'] ?? '';

$sql = "SELECT o.*,
    (SELECT COUNT(*) FROM shop_deliveries WHERE order_id = o.id AND status = 'pending') as pending_deliveries,
    (SELECT COUNT(*) FROM shop_deliveries WHERE order_id = o.id AND status = 'completed') as completed_deliveries
    FROM shop_orders o WHERE 1=1";
$params = [];

if ($statusFilter) {
    $sql .= " AND o.order_status = ?";
    $params[] = $statusFilter;
}
if ($paymentFilter) {
    $sql .= " AND o.payment_status = ?";
    $params[] = $paymentFilter;
}

$sql .= " ORDER BY o.created_at DESC";
$orders = $db->fetchAll($sql, $params);
?>
<div class="admin-header">
    <h2><i class="fas fa-clipboard-list" style="color: var(--info);"></i> Manage Orders</h2>
    <a href="orders.php" class="btn btn-secondary btn-sm"><i class="fas fa-list"></i> View All</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars(urldecode($message)) ?></div>
<?php endif; ?>

<div class="action-bar">
    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
        <a href="orders.php" class="btn btn-sm <?= (!$statusFilter) ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-list"></i> All</a>
        <a href="orders.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-clock"></i> Pending</a>
        <a href="orders.php?status=processing" class="btn btn-sm <?= $statusFilter === 'processing' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-cog"></i> Processing</a>
        <a href="orders.php?status=delivered" class="btn btn-sm <?= $statusFilter === 'delivered' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-check"></i> Delivered</a>
        <a href="orders.php?status=cancelled" class="btn btn-sm <?= $statusFilter === 'cancelled' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-times"></i> Cancelled</a>
    </div>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>In-game</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Payment Status</th>
                <th>Order Status</th>
                <th>Delivery</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= $order['id'] ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                    <td><?= htmlspecialchars($order['ingame_name'] ?: '-') ?></td>
                    <td><strong><i class="fas fa-coins" style="color:var(--accent);"></i> <?= number_format($order['total'], 0) ?></strong></td>
                    <td><?= htmlspecialchars($order['payment_method']) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="update_payment_status">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="payment_status" onchange="this.form.submit()" class="form-control" style="width:auto;padding:3px 8px;font-size:0.75rem;">
                                <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $order['payment_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="completed" <?= $order['payment_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="rejected" <?= $order['payment_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="update_order_status">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="order_status" onchange="this.form.submit()" class="form-control" style="width:auto;padding:3px 8px;font-size:0.75rem;">
                                <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="delivered" <?= $order['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($order['completed_deliveries'] > 0): ?>
                            <span style="color:var(--success);"><i class="fas fa-check-circle"></i> <?= $order['completed_deliveries'] ?>/<?= $order['completed_deliveries'] + $order['pending_deliveries'] ?></span>
                        <?php elseif ($order['pending_deliveries'] > 0): ?>
                            <span style="color:var(--warning);"><i class="fas fa-clock"></i> <?= $order['pending_deliveries'] ?> pending</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">-</span>
                        <?php endif; ?>
                        <?php if ($order['pending_deliveries'] > 0): ?>
                            <form method="POST" style="display:inline;margin-left:0.3rem;">
                                <input type="hidden" name="action" value="deliver_now">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success" title="Deliver now" style="padding:2px 6px;font-size:0.65rem;"><i class="fas fa-rocket"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete order #<?= $order['id'] ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php if ($order['notes']): ?>
                    <tr>
                        <td colspan="10" style="background: rgba(13,110,253,0.03); font-size: 0.8rem; color: var(--text-secondary);">
                            <i class="fas fa-comment"></i> <?= htmlspecialchars($order['notes']) ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="10" style="text-align: center;">No orders</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $orderId = (int)$_POST['order_id'];
    $db->delete('orders', 'id = ?', [$orderId]);
    \Core\Logger::info('Order deleted', ['order_id' => $orderId]);
    header("Location: orders.php?msg=deleted");
    exit;
}
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
