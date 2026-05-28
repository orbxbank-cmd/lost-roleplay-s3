<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/includes/header.php';

\Core\Auth::requireAuth();

$db = \Core\Database::getInstance();
$userId = \Core\Auth::userId();

$orders = $db->fetchAll("
    SELECT * FROM shop_orders
    WHERE user_id = ?
    ORDER BY created_at DESC
", [$userId]);
?>
<section class="section">
    <div class="container">
        <h2 class="section-title"><i class="fas fa-clipboard-list title-accent"></i> My Orders</h2>
        <p class="section-subtitle">View your order history and status</p>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                <h3>No orders yet</h3>
                <p>You haven't placed any orders yet</p>
                <a href="shop.php" class="btn btn-primary"><i class="fas fa-store"></i> Shop Now</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Payment Status</th>
                            <th>Order Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['id'] ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                                <td><strong><i class="fas fa-coins" style="color:var(--accent);"></i> <?= number_format($order['total'], 0) ?></strong></td>
                                <td><?= htmlspecialchars($order['payment_method']) ?></td>
                                <td>
                                    <span class="status status-<?= $order['payment_status'] ?>">
                                        <?php
                                        $statuses = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'completed' => 'Completed'];
                                        echo $statuses[$order['payment_status']] ?? $order['payment_status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status status-<?= $order['order_status'] ?>">
                                        <?php
                                        $statuses = ['pending' => 'Pending', 'processing' => 'Processing', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
                                        echo $statuses[$order['order_status']] ?? $order['order_status'];
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
