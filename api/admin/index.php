<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();

$stats = [
    'products' => $db->fetch("SELECT COUNT(*) as count FROM shop_products WHERE is_active = 1")['count'],
    'orders' => $db->fetch("SELECT COUNT(*) as count FROM shop_orders")['count'],
    'pending_orders' => $db->fetch("SELECT COUNT(*) as count FROM shop_orders WHERE order_status = 'pending'")['count'],
    'users' => $db->fetch("SELECT COUNT(*) as count FROM shop_users WHERE is_admin = 0")['count'],
    'revenue' => $db->fetch("SELECT COALESCE(SUM(total), 0) as total FROM shop_orders WHERE payment_status IN ('confirmed', 'completed')")['total'],
    'pending_payments' => $db->fetch("SELECT COUNT(*) as count FROM shop_orders WHERE payment_status = 'pending'")['count'],
];

$recentOrders = $db->fetchAll("SELECT * FROM shop_orders ORDER BY created_at DESC LIMIT 10");
?>
<div class="admin-header">
    <h2><i class="fas fa-chart-bar" style="color: var(--info);"></i> Dashboard</h2>
    <a href="../../public/logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $stats['products'] ?></div>
        <div class="stat-label"><i class="fas fa-box"></i> Active Products</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['orders'] ?></div>
        <div class="stat-label"><i class="fas fa-clipboard-list"></i> Total Orders</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning);"><?= $stats['pending_orders'] ?></div>
        <div class="stat-label"><i class="fas fa-clock"></i> Pending Orders</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['revenue'], 0) ?> <small style="font-size: 0.8rem; color: var(--text-muted);"><i class="fas fa-coins"></i></small></div>
        <div class="stat-label"><i class="fas fa-coins"></i> Revenue</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning);"><?= $stats['pending_payments'] ?></div>
        <div class="stat-label"><i class="fas fa-hourglass"></i> Pending Payments</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['users'] ?></div>
        <div class="stat-label"><i class="fas fa-users"></i> Users</div>
    </div>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h3 style="font-family: var(--header-font);"><i class="fas fa-list"></i> Recent Orders</h3>
    <a href="orders.php" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View All</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><?= $order['id'] ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                    <td><strong><i class="fas fa-coins" style="color:var(--accent);"></i> <?= number_format($order['total'], 0) ?></strong></td>
                    <td><?= htmlspecialchars($order['payment_method']) ?></td>
                    <td><span class="status status-<?= $order['order_status'] ?>"><?= ucfirst($order['order_status']) ?></span></td>
                    <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentOrders)): ?>
                <tr><td colspan="7" style="text-align: center;">No orders yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
