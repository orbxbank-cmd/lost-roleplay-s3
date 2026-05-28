<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        $totalPrice = (float)$_POST['total_price'] / 10;
        $productIds = $_POST['product_ids'] ?? [];
        $bundleId = $action === 'edit' ? (int)$_POST['bundle_id'] : null;

        if ($name && $totalPrice > 0 && !empty($productIds)) {
            if ($action === 'add') {
                $bundleId = $db->insert('bundles', ['name' => $name, 'total_price' => $totalPrice]);
                foreach ($productIds as $pid) {
                    $db->insert('bundle_products', ['bundle_id' => $bundleId, 'product_id' => (int)$pid, 'quantity' => 1]);
                }
                \Core\Logger::info('Bundle added', ['name' => $name]);
                $msg = 'Bundle added successfully';
            } else {
                $db->update('bundles', ['name' => $name, 'total_price' => $totalPrice], 'id = :id', ['id' => $bundleId]);
                $db->delete('bundle_products', 'bundle_id = ?', [$bundleId]);
                foreach ($productIds as $pid) {
                    $db->insert('bundle_products', ['bundle_id' => $bundleId, 'product_id' => (int)$pid, 'quantity' => 1]);
                }
                \Core\Logger::info('Bundle updated', ['id' => $bundleId]);
                $msg = 'Bundle updated successfully';
            }
        } else {
            $msg = 'Please fill all fields';
        }
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['bundle_id'];
        $bundle = $db->fetch("SELECT is_active FROM shop_bundles WHERE id = ?", [$id]);
        if ($bundle) {
            $db->update('bundles', ['is_active' => $bundle['is_active'] ? 0 : 1], 'id = :id', ['id' => $id]);
            $msg = 'Bundle toggled';
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['bundle_id'];
        $db->delete('bundles', 'id = ?', [$id]);
        \Core\Logger::info('Bundle deleted', ['id' => $id]);
        $msg = 'Bundle deleted';
    }
}

$bundles = $db->fetchAll("SELECT * FROM shop_bundles ORDER BY created_at DESC");
$products = $db->fetchAll("SELECT id, name, price, category_id FROM shop_products ORDER BY category_id, name");
$categories = $db->fetchAll("SELECT id, name FROM shop_categories ORDER BY id");
$editBundle = null;
if (isset($_GET['edit'])) {
    $editBundle = $db->fetch("SELECT * FROM shop_bundles WHERE id = ?", [(int)$_GET['edit']]);
    if ($editBundle) {
        $editBundle['products'] = $db->fetchAll("SELECT product_id, quantity FROM shop_bundle_products WHERE bundle_id = ?", [$editBundle['id']]);
    }
}

function getCats($cats) {
    $map = [];
    foreach ($cats as $c) $map[$c['id']] = $c['name'];
    return $map;
}
$catMap = getCats($categories);
?>
<div class="admin-header">
    <h2><i class="fas fa-gift" style="color: var(--accent);"></i> Bundles</h2>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('add-form').style.display='block';this.style.display='none'"><i class="fas fa-plus"></i> Add Bundle</button>
</div>

<?php if ($msg): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Add / Edit Form -->
<div id="add-form" style="display:<?= $editBundle ? 'block' : 'none' ?>; background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:2rem;">
    <h3 style="margin-bottom:1rem;font-family:var(--header-font);"><?= $editBundle ? 'Edit Bundle' : 'New Bundle' ?></h3>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editBundle ? 'edit' : 'add' ?>">
        <?php if ($editBundle): ?>
            <input type="hidden" name="bundle_id" value="<?= $editBundle['id'] ?>">
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div class="form-group">
                <label>Bundle Name</label>
                <input type="text" name="name" class="form-control" required value="<?= $editBundle ? htmlspecialchars($editBundle['name']) : '' ?>" placeholder="e.g. Admin + Cars Pack">
            </div>
            <div class="form-group">
                <label>Total Price (Coins)</label>
                <input type="number" name="total_price" class="form-control" required step="1" min="0" value="<?= $editBundle ? $editBundle['total_price'] * 10 : '' ?>" placeholder="e.g. 350">
            </div>
        </div>

        <div class="form-group">
            <label>Select Products in Bundle</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.5rem;margin-top:0.5rem;">
                <?php foreach ($products as $p):
                    $checked = $editBundle && in_array($p['id'], array_column($editBundle['products'], 'product_id'));
                ?>
                    <label style="display:flex;align-items:center;gap:0.5rem;background:var(--bg-dark);padding:0.5rem 0.8rem;border-radius:6px;cursor:pointer;font-size:0.85rem;">
                        <input type="checkbox" name="product_ids[]" value="<?= $p['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                        <span>[<?= htmlspecialchars($catMap[$p['category_id']] ?? '?') ?>] <?= htmlspecialchars($p['name']) ?></span>
                        <small style="color:var(--text-muted);margin-left:auto;"><i class="fas fa-coins"></i> <?= $p['price'] * 10 ?></small>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:flex;gap:0.5rem;margin-top:1rem;">
            <button type="submit" class="btn btn-primary"><?= $editBundle ? '<i class="fas fa-save"></i> Update' : '<i class="fas fa-plus"></i> Create' ?></button>
            <?php if ($editBundle): ?>
                <a href="bundles.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
            <?php else: ?>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('add-form').style.display='none';document.querySelector('.admin-header button').style.display='inline'"><i class="fas fa-times"></i> Cancel</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bundles List -->
<?php if (empty($bundles)): ?>
    <div class="empty-state">
        <div class="icon"><i class="fas fa-gift"></i></div>
        <h3>No bundles yet</h3>
        <p>Create your first bundle to offer discounts when players buy multiple products</p>
    </div>
<?php else: ?>
    <?php foreach ($bundles as $b):
        $bps = $db->fetchAll(
            "SELECT bp.product_id, bp.quantity, p.name, p.price, c.name as cat
             FROM shop_bundle_products bp
             JOIN shop_products p ON p.id = bp.product_id
             JOIN shop_categories c ON c.id = p.category_id
             WHERE bp.bundle_id = ?", [$b['id']]
        );
        $normalTotal = array_sum(array_map(fn($bp) => ($bp['price'] * 10) * $bp['quantity'], $bps));
    ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem 1.5rem;margin-bottom:1rem;opacity:<?= $b['is_active'] ? '1' : '0.5' ?>;">
            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:0.5rem;">
                <div>
                    <h3 style="font-family:var(--header-font);"><?= htmlspecialchars($b['name']) ?></h3>
                    <div style="font-size:0.85rem;color:var(--text-secondary);margin-top:0.3rem;">
                        <?php foreach ($bps as $bp): ?>
                            <span style="background:var(--bg-dark);padding:2px 8px;border-radius:4px;margin-right:4px;font-size:0.8rem;">
                                <?= htmlspecialchars($bp['cat']) ?>: <?= htmlspecialchars($bp['name']) ?> (x<?= $bp['quantity'] ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.2rem;font-weight:800;color:var(--success);font-family:var(--header-font);"><i class="fas fa-coins"></i> <?= number_format($b['total_price'] * 10, 0) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">Normal: <i class="fas fa-coins"></i> <?= number_format($normalTotal, 0) ?></div>
                    <?php if ($normalTotal > ($b['total_price'] * 10)): ?>
                        <div style="font-size:0.75rem;color:var(--success);">Save <i class="fas fa-coins"></i> <?= number_format($normalTotal - ($b['total_price'] * 10), 0) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:0.5rem;margin-top:0.8rem;padding-top:0.8rem;border-top:1px solid var(--border);">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="bundle_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn btn-sm <?= $b['is_active'] ? 'btn-warning' : 'btn-primary' ?>">
                        <i class="fas <?= $b['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i> <?= $b['is_active'] ? 'Disable' : 'Enable' ?>
                    </button>
                </form>
                <a href="bundles.php?edit=<?= $b['id'] ?>" class="btn btn-info btn-sm"><i class="fas fa-edit"></i> Edit</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete bundle?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="bundle_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
