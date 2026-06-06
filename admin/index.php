<?php
require_once __DIR__ . '/../core/Database.php';
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
    <h2>📊 الإحصائيات</h2>
    <div>
        <a href="../public/logout.php" class="btn btn-danger btn-sm">تسجيل خروج</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $stats['products'] ?></div>
        <div class="stat-label">المنتجات النشطة</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['orders'] ?></div>
        <div class="stat-label">إجمالي الطلبات</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning);"><?= $stats['pending_orders'] ?></div>
        <div class="stat-label">طلبات معلقة 🕐</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['revenue'], 0) ?> <small style="font-size: 0.9rem; color: var(--text-muted);">dh</small></div>
        <div class="stat-label">الإيرادات</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning);"><?= $stats['pending_payments'] ?></div>
        <div class="stat-label">مدفوعات معلقة</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['users'] ?></div>
        <div class="stat-label">المستخدمين</div>
    </div>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h3>آخر الطلبات</h3>
    <a href="orders.php" class="btn btn-secondary btn-sm">عرض الكل</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>العميل</th>
                <th>الهاتف</th>
                <th>المجموع</th>
                <th>الدفع</th>
                <th>الحالة</th>
                <th>التاريخ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><?= $order['id'] ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                    <td><strong><?= number_format($order['total'], 0) ?> dh</strong></td>
                    <td><?= htmlspecialchars($order['payment_method']) ?></td>
                    <td>
                        <span class="status status-<?= $order['order_status'] ?>"><?= $order['order_status'] ?></span>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentOrders)): ?>
                <tr><td colspan="7" style="text-align: center;">لا توجد طلبات بعد</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
