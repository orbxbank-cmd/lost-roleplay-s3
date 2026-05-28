<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Delivery.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $orderId = (int)$_POST['order_id'];

    if ($_POST['action'] === 'update_order_status') {
        $status = $_POST['order_status'];
        $db->update('orders', ['order_status' => $status], 'id = :id', ['id' => $orderId]);
        \Core\Logger::info('تحديث حالة الطلب', ['order_id' => $orderId, 'status' => $status]);

        if ($status === 'delivered') {
            $result = \Core\Delivery::deliverOrderItems($orderId);
            if ($result['success']) {
                \Core\Logger::info('تم التوصيل التلقائي', ['order_id' => $orderId]);
            } else {
                \Core\Logger::warning('فشل التوصيل', ['order_id' => $orderId, 'reason' => $result['message']]);
            }
        }

        header("Location: orders.php?msg=updated");
        exit;
    }

    if ($_POST['action'] === 'update_payment_status') {
        $status = $_POST['payment_status'];
        $db->update('orders', ['payment_status' => $status], 'id = :id', ['id' => $orderId]);
        \Core\Logger::info('تحديث حالة الدفع', ['order_id' => $orderId, 'status' => $status]);

        if ($status === 'confirmed') {
            $result = \Core\Delivery::deliverOrderItems($orderId);
            if ($result['success']) {
                \Core\Logger::info('تم التوصيل بعد تأكيد الدفع', ['order_id' => $orderId]);
            } else {
                \Core\Logger::warning('فشل التوصيل بعد تأكيد الدفع', ['order_id' => $orderId, 'reason' => $result['message']]);
            }
        }

        header("Location: orders.php?msg=updated");
        exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';

$sql = "SELECT * FROM orders WHERE 1=1";
$params = [];

if ($statusFilter) {
    $sql .= " AND order_status = ?";
    $params[] = $statusFilter;
}
if ($paymentFilter) {
    $sql .= " AND payment_status = ?";
    $params[] = $paymentFilter;
}

$sql .= " ORDER BY created_at DESC";
$orders = $db->fetchAll($sql, $params);
$message = $_GET['msg'] ?? '';
?>
<div class="admin-header">
    <h2>📋 إدارة الطلبات</h2>
    <a href="orders.php" class="btn btn-secondary btn-sm">عرض الكل</a>
</div>

<?php if ($message === 'updated'): ?>
    <div class="alert alert-success">تم تحديث الطلب بنجاح</div>
<?php endif; ?>

<div class="action-bar">
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="orders.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">معلق</a>
        <a href="orders.php?status=processing" class="btn btn-sm <?= $statusFilter === 'processing' ? 'btn-primary' : 'btn-secondary' ?>">قيد التنفيذ</a>
        <a href="orders.php?status=delivered" class="btn btn-sm <?= $statusFilter === 'delivered' ? 'btn-primary' : 'btn-secondary' ?>">مكتمل</a>
        <a href="orders.php?status=cancelled" class="btn btn-sm <?= $statusFilter === 'cancelled' ? 'btn-primary' : 'btn-secondary' ?>">ملغي</a>
    </div>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>العميل</th>
                <th>الهاتف</th>
                <th>In-game</th>
                <th>الإيداع</th>
                <th>المجموع</th>
                <th>الدفع</th>
                <th>حالة الدفع</th>
                <th>الحالة</th>
                <th>التاريخ</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= $order['id'] ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                    <td><?= htmlspecialchars($order['ingame_name'] ?: '-') ?></td>
                    <td>
                        <?php if ($order['proof_file']): ?>
                            <a href="../uploads/proofs/<?= htmlspecialchars($order['proof_file']) ?>" target="_blank" class="btn btn-sm btn-secondary">📎 عرض</a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= number_format($order['total'], 0) ?> dh</strong></td>
                    <td><?= htmlspecialchars($order['payment_method']) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="update_payment_status">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="payment_status" onchange="this.form.submit()" class="form-control" style="width:auto;padding:3px 8px;font-size:0.8rem;">
                                <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>🕐 قيد الانتظار</option>
                                <option value="confirmed" <?= $order['payment_status'] === 'confirmed' ? 'selected' : '' ?>>✅ مؤكد</option>
                                <option value="completed" <?= $order['payment_status'] === 'completed' ? 'selected' : '' ?>>✔️ مكتمل</option>
                                <option value="rejected" <?= $order['payment_status'] === 'rejected' ? 'selected' : '' ?>>❌ مرفوض</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="update_order_status">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="order_status" onchange="this.form.submit()" class="form-control" style="width:auto;padding:3px 8px;font-size:0.8rem;">
                                <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>🕐 معلق</option>
                                <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>⚙️ قيد التنفيذ</option>
                                <option value="delivered" <?= $order['order_status'] === 'delivered' ? 'selected' : '' ?>>✅ تم التوصيل</option>
                                <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>❌ ملغي</option>
                            </select>
                        </form>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف الطلب رقم <?= $order['id'] ?>؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                        </form>
                    </td>
                </tr>
                <?php if ($order['notes']): ?>
                    <tr>
                        <td colspan="10" style="background: rgba(233,30,99,0.05); font-size: 0.85rem; color: var(--text-secondary);">
                            📝 ملاحظات: <?= htmlspecialchars($order['notes']) ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="11" style="text-align: center;">لا توجد طلبات</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $orderId = (int)$_POST['order_id'];
    $db->delete('orders', 'id = ?', [$orderId]);
    \Core\Logger::info('حذف طلب', ['order_id' => $orderId]);
    header("Location: orders.php?msg=deleted");
    exit;
}
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
