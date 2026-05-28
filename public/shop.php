<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$loggedIn = \Core\Auth::isLoggedIn();
$selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : null;
$categories = $db->fetchAll("SELECT * FROM shop_categories WHERE is_active = 1 ORDER BY sort_order");

if ($selectedCategory) {
    $products = $db->fetchAll("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM shop_products p
        JOIN shop_categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND p.category_id = ?
        ORDER BY p.id
    ", [$selectedCategory]);
} else {
    $products = $db->fetchAll("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM shop_products p
        JOIN shop_categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY c.sort_order, p.id
    ");
}
?>
<section class="section">
    <div class="container">
        <h2 class="section-title"><span class="title-accent">Los Santos</span> Shop</h2>
        <p class="section-subtitle">Browse all products and categories</p>

        <div class="categories-grid">
            <div class="category-card <?= !$selectedCategory ? 'active' : '' ?>" onclick="window.location.href='shop.php'">
                <div class="icon"><i class="fas fa-list"></i></div>
                <div class="name">All</div>
            </div>
            <?php foreach ($categories as $cat): ?>
                <div class="category-card <?= $selectedCategory === (int)$cat['id'] ? 'active' : '' ?>" onclick="window.location.href='shop.php?category=<?= $cat['id'] ?>'">
                    <div class="icon"><i class="fas fa-<?php
                        $icons = ['shield' => 'shield-halved', 'car' => 'car', 'home' => 'house', 'gamepad' => 'gamepad', 'wallet' => 'wallet', 'trending-up' => 'arrow-trend-up', 'gift' => 'gift', 'star' => 'star'];
                        echo $icons[$cat['icon']] ?? 'box';
                    ?>"></i></div>
                    <div class="name"><?= htmlspecialchars($cat['name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <?php if ($product['badge']): ?>
                        <span class="badge <?= $product['is_popular'] ? 'popular-badge' : '' ?>"><?= htmlspecialchars($product['badge']) ?></span>
                    <?php endif; ?>
                    <?php if ($product['image']): ?>
                        <div class="product-image">
                            <img src="assets/images/products/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        </div>
                    <?php endif; ?>
                    <div class="category-tag">
                        <img src="assets/images/game.png" alt="" style="height: 18px; width: auto; vertical-align: middle;">
                        <?= htmlspecialchars($product['category_name']) ?>
                    </div>
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <?php if ($product['description']): ?>
                        <p><?= htmlspecialchars($product['description']) ?></p>
                    <?php endif; ?>
                    <div class="price coin-main">
                        <i class="fas fa-coins" style="color: var(--accent);"></i> <?= number_format($product['price'] * 10) ?> <span class="currency">Coins</span>
                    </div>
                    <?php if ($loggedIn): ?>
                    <button class="btn btn-primary add-to-cart"
                            data-id="<?= $product['id'] ?>"
                            data-name="<?= htmlspecialchars($product['name']) ?>"
                            data-price="<?= $product['price'] * 10 ?>"
                            data-coin-price="<?= $product['price'] * 10 ?>"
                            data-category="<?= htmlspecialchars($product['category_name']) ?>">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                    <?php else: ?>
                    <a href="login.php" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:0.4rem;justify-content:center;">
                        <i class="fas fa-sign-in-alt"></i> Login to Buy
                    </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-box-open"></i></div>
                <h3>No products</h3>
                <p>No products in this category</p>
                <a href="shop.php" class="btn btn-primary"><i class="fas fa-list"></i> View All</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Car Selection Modal -->
<div id="car-modal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:9999;justify-content:center;align-items:center;">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;max-width:700px;width:90%;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h3 style="font-family:var(--header-font);"><i class="fas fa-car"></i> Choose <span id="car-select-count">2</span> Cars</h3>
            <button onclick="closeCarModal()" style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>
        <p style="color:var(--text-secondary);margin-bottom:1rem;font-size:0.85rem;" id="car-select-status">Click on cars to select them</p>
        <div id="car-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-bottom:1.5rem;"></div>
        <div style="display:flex;justify-content:flex-end;gap:0.5rem;border-top:1px solid var(--border);padding-top:1rem;">
            <button class="btn btn-secondary" onclick="closeCarModal()">Cancel</button>
            <button class="btn btn-primary" id="car-confirm-btn" onclick="confirmCarSelection()"><i class="fas fa-cart-plus"></i> Add to Cart</button>
        </div>
    </div>
</div>

<style>
.car-option { background:var(--bg-input);border:2px solid var(--border);border-radius:8px;padding:10px;text-align:center;cursor:pointer;transition:all 0.2s; }
.car-option:hover { border-color:var(--info);background:rgba(13,110,253,0.08); }
.car-option.selected { border-color:var(--accent);background:rgba(255,193,7,0.1);box-shadow:0 0 10px rgba(255,193,7,0.2); }
.car-option .model-id { font-size:0.7rem;color:var(--text-muted); }
.car-option .model-name { font-weight:600;font-size:0.85rem;margin-top:2px; }
.car-option .check { display:none;color:var(--accent);font-size:1.1rem; }
.car-option.selected .check { display:block; }
</style>

<script>
let carModels = [];
let selectedCarModels = [];
let pendingCarProduct = null;
let requiredCarCount = 1;

async function loadCarModels() {
    try {
        const res = await fetch('cars_json.php');
        carModels = await res.json();
    } catch(e) { carModels = []; }
}

function openCarModal(product) {
    pendingCarProduct = product;
    var match = product.name.match(/^(\d+)/);
    requiredCarCount = match ? parseInt(match[1]) : 1;
    product.quantity = 1;
    selectedCarModels = [];
    document.getElementById('car-select-count').textContent = requiredCarCount;
    document.getElementById('car-modal').style.display = 'flex';
    renderCarGrid();
}

function closeCarModal() {
    document.getElementById('car-modal').style.display = 'none';
    pendingCarProduct = null;
}

function renderCarGrid() {
    const grid = document.getElementById('car-grid');
    grid.innerHTML = carModels.map(car => `
        <div class="car-option" data-id="${car.id}" onclick="toggleCar(${car.id})">
            <div class="check"><i class="fas fa-check-circle"></i></div>
            <div style="font-size:1.5rem;margin:4px 0;"><i class="fas fa-car"></i></div>
            <div class="model-name">${car.name}</div>
            <div class="model-id">ID: ${car.id}</div>
        </div>
    `).join('');
    updateCarStatus();
}

function toggleCar(id) {
    const idx = selectedCarModels.indexOf(id);
    if (idx > -1) {
        selectedCarModels.splice(idx, 1);
    } else {
        if (selectedCarModels.length >= requiredCarCount) {
            selectedCarModels.shift();
        }
        selectedCarModels.push(id);
    }
    document.querySelectorAll('.car-option').forEach(el => {
        el.classList.toggle('selected', selectedCarModels.includes(parseInt(el.dataset.id)));
    });
    updateCarStatus();
}

function updateCarStatus() {
    const status = document.getElementById('car-select-status');
    const btn = document.getElementById('car-confirm-btn');
    if (selectedCarModels.length === requiredCarCount) {
        status.innerHTML = '<i class="fas fa-check" style="color:var(--success);"></i> Selected ' + requiredCarCount + ' cars';
        btn.disabled = false;
        btn.style.opacity = '1';
    } else {
        status.textContent = 'Select ' + (requiredCarCount - selectedCarModels.length) + ' more car(s) (' + selectedCarModels.length + '/' + requiredCarCount + ')';
        btn.disabled = true;
        btn.style.opacity = '0.5';
    }
}

function confirmCarSelection() {
    if (!pendingCarProduct || selectedCarModels.length !== requiredCarCount) return;
    pendingCarProduct.models = [...selectedCarModels];
    cart.add(pendingCarProduct);
    closeCarModal();
}

loadCarModels();

document.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.addEventListener('click', function() {
        const category = this.dataset.category;
        const product = {
            id: parseInt(this.dataset.id),
            name: this.dataset.name,
            price: parseFloat(this.dataset.price),
            coinPrice: parseFloat(this.dataset.price),
            category: category,
            quantity: 1
        };
        if (category === 'Cars') {
            openCarModal(product);
        } else {
            cart.add(product);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
