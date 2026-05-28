<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
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

        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('prod_') . '.' . $ext;
            $dest = __DIR__ . '/../../public/assets/images/products/' . $filename;
            move_uploaded_file($_FILES['image']['tmp_name'], $dest);
            $image = $filename;
        }

        $data = [
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'coin_price' => $price * 10,
            'badge' => $badge ?: null,
            'is_popular' => $isPopular,
            'is_active' => $isActive,
        ];
        if ($image) {
            $data['image'] = $image;
        }

        if ($action === 'add') {
            $db->insert('products', $data);
            \Core\Logger::info('Product added', ['name' => $name]);
            $msg = 'Product added successfully';
        } else {
            $productId = (int)$_POST['product_id'];
            $db->update('products', $data, 'id = :id', ['id' => $productId]);
            \Core\Logger::info('Product updated', ['id' => $productId]);
            $msg = 'Product updated successfully';
        }
    }

    if ($action === 'delete') {
        $productId = (int)$_POST['product_id'];
        $db->delete('products', 'id = ?', [$productId]);
        \Core\Logger::info('Product deleted', ['id' => $productId]);
        $msg = 'Product deleted successfully';
    }

    header("Location: products.php?msg=" . urlencode($msg));
    exit;
}

$categories = $db->fetchAll("SELECT * FROM shop_categories ORDER BY sort_order");
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name
    FROM shop_products p
    JOIN shop_categories c ON p.category_id = c.id
    ORDER BY c.sort_order, p.id
");
$message = $_GET['msg'] ?? '';
$editProduct = null;
if (isset($_GET['edit'])) {
    $editProduct = $db->fetch("SELECT * FROM shop_products WHERE id = ?", [(int)$_GET['edit']]);
}
?>
<div class="admin-header">
    <h2><i class="fas fa-box" style="color: var(--info);"></i> Manage Products</h2>
    <a href="products.php" class="btn btn-secondary btn-sm"><i class="fas fa-list"></i> View All</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 2rem;">
    <div>
        <h3 style="margin-bottom: 1rem; font-family: var(--header-font);"><?= $editProduct ? '<i class="fas fa-edit"></i> Edit Product' : '<i class="fas fa-plus"></i> Add Product' ?></h3>
        <form method="POST" enctype="multipart/form-data" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
            <input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
            <?php if ($editProduct): ?>
                <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label><i class="fas fa-folder"></i> Category</label>
                <select name="category_id" class="form-control" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($editProduct && $editProduct['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Product Name</label>
                <input type="text" name="name" class="form-control" required value="<?= $editProduct ? htmlspecialchars($editProduct['name']) : '' ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Description</label>
                <textarea name="description" class="form-control"><?= $editProduct ? htmlspecialchars($editProduct['description']) : '' ?></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-image"></i> Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <?php if ($editProduct && $editProduct['image']): ?>
                    <div style="margin-top: 0.5rem;">
                        <img src="../../public/assets/images/products/<?= htmlspecialchars($editProduct['image']) ?>" alt="" style="height: 80px; border-radius: 6px; border: 1px solid var(--border);">
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label><i class="fas fa-coins"></i> Price (MAD)</label>
                <input type="number" step="0.01" name="price" class="form-control" required value="<?= $editProduct ? $editProduct['price'] : '' ?>" placeholder="e.g. 30" id="price-input">
                <small style="color:var(--text-muted);">
                    <i class="fas fa-coins" style="color:var(--accent);"></i> Coin price: <strong id="coin-preview"><?= $editProduct ? $editProduct['price'] * 10 : '0' ?></strong> coins
                    (100 coins = 10 MAD)
                </small>
            </div>
            <div class="form-group">
                <label><i class="fas fa-certificate"></i> Badge</label>
                <input type="text" name="badge" class="form-control" value="<?= $editProduct ? htmlspecialchars($editProduct['badge'] ?? '') : '' ?>" placeholder="e.g. Best Seller, Deal">
            </div>
            <div class="form-group" style="display: flex; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_popular" value="1" <?= ($editProduct && $editProduct['is_popular']) ? 'checked' : '' ?>>
                    <i class="fas fa-fire" style="color: var(--danger);"></i> Popular
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= (!$editProduct || $editProduct['is_active']) ? 'checked' : '' ?>>
                    <i class="fas fa-check-circle" style="color: var(--success);"></i> Active
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-<?= $editProduct ? 'save' : 'plus' ?>"></i> <?= $editProduct ? 'Save Changes' : 'Add Product' ?>
            </button>
            <?php if ($editProduct): ?>
                <a href="products.php" class="btn btn-secondary btn-sm" style="width: 100%; margin-top: 0.5rem;"><i class="fas fa-times"></i> Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>Category</th>
                        <th>Name</th>
                        <th>Coins</th>
                        <th>MAD</th>
                        <th>Badge</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= $product['id'] ?></td>
                            <td>
                                <?php if ($product['image']): ?>
                                    <img src="../../public/assets/images/products/<?= htmlspecialchars($product['image']) ?>" alt="" style="height: 40px; width: 40px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['category_name']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><strong style="color:var(--accent);"><i class="fas fa-coins"></i> <?= number_format($product['price'] * 10) ?></strong></td>
                            <td><span style="color:var(--text-muted);font-size:0.85rem;"><?= number_format($product['price'], 0) ?> MAD</span></td>
                            <td><?= $product['badge'] ? '<span class="badge" style="position:static;">' . htmlspecialchars($product['badge']) . '</span>' : '-' ?></td>
                            <td><?= $product['is_active'] ? '<i class="fas fa-check-circle" style="color: var(--success);"></i>' : '<i class="fas fa-times-circle" style="color: var(--danger);"></i>' ?></td>
                            <td>
                                <a href="products.php?edit=<?= $product['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars($product['name']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const priceInput = document.getElementById('price-input');
    const coinPreview = document.getElementById('coin-preview');
    if (priceInput && coinPreview) {
        priceInput.addEventListener('input', function() {
            coinPreview.textContent = (parseFloat(this.value) * 10) || 0;
        });
    }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
