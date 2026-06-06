<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $categoryId = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $badge = trim($_POST['badge'] ?? '');
        $isPopular = isset($_POST['is_popular']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'add') {
            $db->insert('products', [
                'category_id' => $categoryId,
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'badge' => $badge ?: null,
                'is_popular' => $isPopular,
                'is_active' => $isActive,
            ]);
            \Core\Logger::info('إضافة منتج', ['name' => $name]);
            $msg = 'تمت إضافة المنتج بنجاح';
        } else {
            $productId = (int)$_POST['product_id'];
            $db->update('products', [
                'category_id' => $categoryId,
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'badge' => $badge ?: null,
                'is_popular' => $isPopular,
                'is_active' => $isActive,
            ], 'id = :id', ['id' => $productId]);
            \Core\Logger::info('تعديل منتج', ['id' => $productId]);
            $msg = 'تم تعديل المنتج بنجاح';
        }
    }

    if ($action === 'delete') {
        $productId = (int)$_POST['product_id'];
        $db->delete('products', 'id = ?', [$productId]);
        \Core\Logger::info('حذف منتج', ['id' => $productId]);
        $msg = 'تم حذف المنتج بنجاح';
    }

    header("Location: products.php?msg=" . urlencode($msg));
    exit;
}

$categories = $db->fetchAll("SELECT * FROM shop_categories ORDER BY sort_order");
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name
    FROM shop_products p
    JOIN categories c ON p.category_id = c.id
    ORDER BY c.sort_order, p.id
");
$message = $_GET['msg'] ?? '';
$editProduct = null;
if (isset($_GET['edit'])) {
    $editProduct = $db->fetch("SELECT * FROM shop_products WHERE id = ?", [(int)$_GET['edit']]);
}
?>
<div class="admin-header">
    <h2>📦 إدارة المنتجات</h2>
    <a href="products.php" class="btn btn-secondary btn-sm">عرض الكل</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 2rem;">
    <div>
        <h3 style="margin-bottom: 1rem;"><?= $editProduct ? 'تعديل المنتج' : 'إضافة منتج جديد' ?></h3>
        <form method="POST" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
            <input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
            <?php if ($editProduct): ?>
                <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>القسم</label>
                <select name="category_id" class="form-control" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($editProduct && $editProduct['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>اسم المنتج</label>
                <input type="text" name="name" class="form-control" required value="<?= $editProduct ? htmlspecialchars($editProduct['name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>الوصف</label>
                <textarea name="description" class="form-control"><?= $editProduct ? htmlspecialchars($editProduct['description']) : '' ?></textarea>
            </div>
            <div class="form-group">
                <label>السعر (dh)</label>
                <input type="number" step="0.01" name="price" class="form-control" required value="<?= $editProduct ? $editProduct['price'] : '' ?>">
            </div>
            <div class="form-group">
                <label>الشارة (Badge)</label>
                <input type="text" name="badge" class="form-control" value="<?= $editProduct ? htmlspecialchars($editProduct['badge'] ?? '') : '' ?>" placeholder="مثال: الأفضل، صفقة، ماكس">
            </div>
            <div class="form-group" style="display: flex; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_popular" value="1" <?= ($editProduct && $editProduct['is_popular']) ? 'checked' : '' ?>>
                    منتج رائج
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" value="1" <?= (!$editProduct || $editProduct['is_active']) ? 'checked' : '' ?>>
                    نشط
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= $editProduct ? '💾 حفظ التعديلات' : '➕ إضافة المنتج' ?>
            </button>
            <?php if ($editProduct): ?>
                <a href="products.php" class="btn btn-secondary btn-sm" style="width: 100%; margin-top: 0.5rem;">إلغاء</a>
            <?php endif; ?>
        </form>
    </div>

    <div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>القسم</th>
                        <th>الاسم</th>
                        <th>السعر</th>
                        <th>الشارة</th>
                        <th>حالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= $product['id'] ?></td>
                            <td><?= htmlspecialchars($product['category_name']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><strong><?= number_format($product['price'], 0) ?> dh</strong></td>
                            <td><?= $product['badge'] ? '<span class="badge" style="position:static;">' . htmlspecialchars($product['badge']) . '</span>' : '-' ?></td>
                            <td><?= $product['is_active'] ? '✅ نشط' : '❌ غير نشط' ?></td>
                            <td>
                                <a href="products.php?edit=<?= $product['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('حذف <?= htmlspecialchars($product['name']) ?>؟')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
