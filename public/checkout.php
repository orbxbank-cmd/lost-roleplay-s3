<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/GameDelivery.php';
\Core\Session::start();

$db = \Core\Database::getInstance();
$config = require __DIR__ . '/../config/app.php';
$paymentMethods = $db->fetchAll("SELECT * FROM shop_payment_methods WHERE is_active = 1 ORDER BY sort_order");
$loggedIn = \Core\Auth::isLoggedIn();
$user = $loggedIn ? \Core\Auth::user() : null;
$userCoins = $user['coins'] ?? 0;
$error = '';
$success = false;

function calculateBundleTotal($db, $items) {
    $bundles = $db->fetchAll("SELECT id, name, total_price FROM shop_bundles WHERE is_active = 1");
    foreach ($bundles as $b) {
        $bps = $db->fetchAll("SELECT product_id, quantity FROM shop_bundle_products WHERE bundle_id = ?", [$b['id']]);
        $match = true;
        foreach ($bps as $bp) {
            $found = false;
            foreach ($items as $item) {
                if ((int)$item['id'] === (int)$bp['product_id'] && (int)$item['quantity'] >= (int)$bp['quantity']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) { $match = false; break; }
        }
        if ($match) return (float)$b['total_price'] * 10;
    }
    return null;
}

function calculateCoinTotal($cartData) {
    return array_reduce($cartData, fn($sum, $item) => $sum + (($item['coinPrice'] ?? $item['price']) * $item['quantity']), 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $ingameName = trim($_POST['ingame_name'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $cartData = json_decode($_POST['cart_data'] ?? '[]', true);

    if (empty($customerName) || empty($customerPhone) || empty($paymentMethod) || empty($cartData)) {
        $error = 'Please fill all required fields';
    } else {
        try {
            $total = array_reduce($cartData, fn($sum, $item) => $sum + ($item['price'] * $item['quantity']), 0);
            $coinTotal = calculateCoinTotal($cartData);
            $bundleTotal = calculateBundleTotal($db, $cartData);
            if ($bundleTotal !== null) {
                $total = min($total, $bundleTotal);
            }
            $userId = $loggedIn ? \Core\Auth::userId() : null;

            if ($paymentMethod === 'coins') {
                if (!$loggedIn) {
                    $error = 'Please login to pay with coins';
                } elseif ($userCoins < $coinTotal) {
                    $error = 'Insufficient coins. You have ' . number_format($userCoins) . ' coins, need ' . number_format($coinTotal);
                } else {
                    $db->query("UPDATE shop_users SET coins = coins - ? WHERE id = ?", [$coinTotal, $userId]);
                    $db->insert('coin_transactions', [
                        'user_id' => $userId,
                        'amount' => -$coinTotal,
                        'type' => 'payment',
                        'description' => 'Order payment',
                    ]);

                    $orderId = $db->insert('orders', [
                        'user_id' => $userId,
                        'customer_name' => $customerName,
                        'customer_phone' => $customerPhone,
                        'ingame_name' => $ingameName,
                        'total' => $total,
                        'payment_method' => 'coins',
                        'payment_status' => 'confirmed',
                        'order_status' => 'processing',
                        'notes' => $notes,
                    ]);

                    foreach ($cartData as $item) {
                        $db->insert('order_items', [
                            'order_id' => $orderId,
                            'product_id' => $item['id'],
                            'product_name' => $item['name'],
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                        ]);
                        $metadata = !empty($item['models']) ? json_encode(['models' => $item['models']]) : null;
                        $db->insert('deliveries', [
                            'order_id' => $orderId,
                            'user_id' => $userId,
                            'ingame_name' => $ingameName ?: $customerName,
                            'product_name' => $item['name'],
                            'quantity' => $item['quantity'],
                            'status' => 'pending',
                            'metadata' => $metadata,
                        ]);
                    }

                    try {
                        $gameDelivery = new \Core\GameDelivery();
                        if ($gameDelivery->isConnected()) {
                            $deliveryResults = $gameDelivery->processByOrderId($orderId);
                            $successCount = count(array_filter($deliveryResults, fn($r) => $r['success']));
                            $failCount = count($deliveryResults) - $successCount;
                            if ($failCount > 0) {
                                \Core\Logger::info('Some coin order deliveries failed', [
                                    'order_id' => $orderId,
                                    'success' => $successCount,
                                    'failed' => $failCount,
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        \Core\Logger::error('GameDelivery error on checkout', ['message' => $e->getMessage()]);
                    }

                    // Referral commission (10% to referrer)
                    if ($userId) {
                        $buyer = $db->fetch("SELECT referred_by FROM shop_users WHERE id = ?", [$userId]);
                        if ($buyer && $buyer['referred_by']) {
                            $commission = max(1, (int)($coinTotal * 0.1));
                            $db->query("UPDATE shop_users SET coins = coins + ?, total_referral_earnings = total_referral_earnings + ? WHERE id = ?", [$commission, $commission, $buyer['referred_by']]);
                            $db->insert('coin_transactions', [
                                'user_id' => $buyer['referred_by'],
                                'amount' => $commission,
                                'type' => 'bonus',
                                'description' => "Referral commission - Order #$orderId ($coinTotal coins)",
                            ]);
                            $buyerUsername = $db->fetch("SELECT username FROM shop_users WHERE id = ?", [$userId])['username'] ?? 'Unknown';
                            $db->insert('referral_transactions', [
                                'referrer_id' => $buyer['referred_by'],
                                'referred_user_id' => $userId,
                                'referred_username' => $buyerUsername,
                                'type' => 'purchase_commission',
                                'coins' => $commission,
                                'order_id' => $orderId,
                            ]);
                            \Core\Logger::info('Referral commission awarded', ['referrer_id' => $buyer['referred_by'], 'commission' => $commission]);
                        }
                    }

                    \Core\Logger::info('Coin order placed', [
                        'order_id' => $orderId,
                        'customer' => $customerName,
                        'coins' => $coinTotal,
                    ]);

                    echo '<script>localStorage.removeItem("cart");</script>';
                    $success = true;
                    $successOrderId = $orderId;
                    $payWithCoins = true;
                }
            } else {
                $orderId = $db->insert('orders', [
                    'user_id' => $userId,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'ingame_name' => $ingameName,
                    'total' => $total,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'pending',
                    'order_status' => 'pending',
                    'notes' => $notes,
                ]);

                foreach ($cartData as $item) {
                    $db->insert('order_items', [
                        'order_id' => $orderId,
                        'product_id' => $item['id'],
                        'product_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                }

                \Core\Logger::info('New order', [
                    'order_id' => $orderId,
                    'customer' => $customerName,
                    'total' => $total,
                    'payment' => $paymentMethod,
                ]);

                $success = true;
                $successOrderId = $orderId;
            }
        } catch (\Exception $e) {
            $error = 'An error occurred. Please try again.';
            \Core\Logger::error('Order error', ['message' => $e->getMessage()]);
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <?php if ($success): ?>
            <div style="max-width: 600px; margin: 0 auto;">
                <div style="text-align: center; padding: 3rem 0;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem; color: var(--success);"><i class="fas fa-check-circle"></i></div>
                    <h2 style="margin-bottom: 0.5rem; font-family: var(--header-font);">Order Placed Successfully!</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                        Order #: <strong style="color: var(--info); font-size: 1.3rem;"><?= $successOrderId ?></strong>
                    </p>
                    <div class="alert alert-info" style="text-align: left; direction: ltr;">
                        <?php if (!empty($payWithCoins)): ?>
                            <p><strong><i class="fas fa-coins"></i> Payment:</strong> <?= number_format($coinTotal) ?> Coins</p>
                            <p><strong><i class="fas fa-check-circle" style="color:var(--success);"></i> Status:</strong> Confirmed instantly</p>
                        <?php else: ?>
                            <p><strong><i class="fas fa-credit-card"></i> Payment:</strong> <?= htmlspecialchars($_POST['payment_method'] ?? '') ?></p>
                            <p><strong><i class="fas fa-coins"></i> Amount:</strong> <?= number_format($coinTotal, 0) ?> Coins</p>
                            <p><strong><i class="fas fa-paper-plane"></i> Send payment proof to:</strong> <?= $config['contact_phone'] ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="alert alert-warning" style="text-align:left;margin-top:1rem;">
                        <i class="fab fa-whatsapp"></i> <strong>Required:</strong> Join our WhatsApp group to receive your delivery: <a href="<?= $config['whatsapp_group'] ?>" target="_blank" style="color:var(--info);font-weight:700;">Join Now</a>
                    </div>
                    <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="shop.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Shop Again</a>
                        <a href="orders.php" class="btn btn-secondary"><i class="fas fa-clipboard-list"></i> My Orders</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <h2 class="section-title"><i class="fas fa-credit-card title-accent"></i> Checkout</h2>
            <p class="section-subtitle">Enter your details to complete the purchase</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; max-width: 1000px; margin: 0 auto;">
                <div>
                    <form method="POST" id="checkout-form" onsubmit="return validateCheckout()">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="customer_name" class="form-control" required placeholder="Enter your name">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number *</label>
                            <input type="tel" name="customer_phone" class="form-control" required placeholder="Enter your phone">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-gamepad"></i> In-game Name</label>
                            <input type="text" name="ingame_name" class="form-control" placeholder="Your character name">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-comment"></i> Notes</label>
                            <textarea name="notes" class="form-control" placeholder="Any additional notes..."></textarea>
                        </div>
                        <input type="hidden" name="cart_data" id="cart-data">
                        <input type="hidden" name="payment_method" id="selected-payment">

                        <h3 style="margin: 1.5rem 0 1rem;"><i class="fas fa-credit-card"></i> Payment Method</h3>
                        <div class="payment-methods">
                            <?php if ($loggedIn): ?>
                                <div class="payment-method coins-method selected" data-code="coins" onclick="selectPayment(this)">
                                    <div class="pm-icon" style="color:var(--accent);"><i class="fas fa-coins"></i></div>
                                    <div class="pm-name">
                                        Pay with Coins
                                        <span style="font-size:0.75rem;color:var(--text-muted);display:block;"><?= number_format($userCoins) ?> available</span>
                                    </div>
                                </div>
                                <div id="payment-error" class="alert alert-danger" style="display:none;"><i class="fas fa-exclamation-triangle"></i> You need coins to purchase</div>
                                <input type="hidden" name="payment_method" id="selected-payment" value="coins">
                            <?php else: ?>
                                <div class="alert alert-info" style="margin-bottom: 0;">
                                    <i class="fas fa-info-circle"></i>
                                    You need to <a href="login.php" style="color: var(--info); font-weight: 700;">Login</a> to purchase with coins
                                </div>
                                <input type="hidden" name="payment_method" id="selected-payment" value="">
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 1.5rem;">
                            <i class="fas fa-check"></i> Confirm Order
                        </button>
                    </form>
                </div>

                <div>
                    <h3 style="margin-bottom: 1rem; font-family: var(--header-font);"><i class="fas fa-shopping-cart"></i> Your Products</h3>
                    <div id="checkout-items"></div>
                    <div id="checkout-summary"></div>

                    <div id="coins-payment-info" class="payment-instructions" style="margin-top: 2rem;">
                        <h4><i class="fas fa-coins" style="color:var(--accent);"></i> Pay with Coins</h4>
                        <p>Your coins balance: <strong style="color:var(--accent);font-size:1.2rem;" id="coins-balance-display"><?= number_format($userCoins) ?></strong></p>
                        <p>After confirming, the coins will be deducted instantly and your order will be processed.</p>
                    </div>

                    <div id="mad-payment-info" class="payment-instructions" style="margin-top: 2rem; display:none;">
                        <h4><i class="fas fa-info-circle"></i> Payment Instructions</h4>
                        <p>After confirming, send the payment receipt via WhatsApp to:</p>
                        <p style="font-size: 1.3rem; font-weight: 800; font-family: var(--header-font); color: var(--info);"><?= $config['contact_phone'] ?></p>
                        <p style="font-size: 0.8rem; color: var(--text-muted);">Your order will be confirmed after payment verification</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
let cachedBundles = null;
let userCoins = <?= $userCoins ?>;
async function getBundles() {
    if (cachedBundles) return cachedBundles;
    try {
        const res = await fetch('bundles_json.php');
        cachedBundles = await res.json();
    } catch (e) { cachedBundles = []; }
    return cachedBundles;
}

function calculateBundleTotal(items) {
    if (!cachedBundles || !cachedBundles.length) return null;
    for (const b of cachedBundles) {
        const match = b.products.every(bp => {
            const item = items.find(i => String(i.id) === String(bp.product_id));
            return item && item.quantity >= bp.quantity;
        });
        if (match) return b.total_price;
    }
    return null;
}

function selectPayment(el) {
    document.querySelectorAll('.payment-method').forEach(pm => pm.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selected-payment').value = el.dataset.code;
    document.getElementById('payment-error').style.display = 'none';

    const isCoins = el.dataset.code === 'coins';
    document.getElementById('coins-payment-info').style.display = isCoins ? 'block' : 'none';
    document.getElementById('mad-payment-info').style.display = isCoins ? 'none' : 'block';

    if (isCoins) {
        const summary = document.getElementById('checkout-summary');
        if (summary) {
            const coinTotalEl = document.getElementById('coin-total-value');
            if (coinTotalEl) {
                const coinTotal = parseInt(coinTotalEl.dataset.coinTotal);
                const coinsBalance = document.getElementById('coins-balance-display');
                if (coinTotal > userCoins) {
                    document.getElementById('coins-payment-info').innerHTML = `
                        <h4><i class="fas fa-coins" style="color:var(--accent);"></i> Pay with Coins</h4>
                        <p>Your balance: <strong style="color:var(--accent);">${userCoins.toLocaleString()}</strong></p>
                        <div class="alert alert-danger" style="margin-top:0.5rem;">
                            <i class="fas fa-exclamation-circle"></i> Insufficient coins! Need ${coinTotal.toLocaleString()}.
                            <a href="profile.php" style="display:block;margin-top:0.3rem;"><i class="fas fa-coins"></i> Add Funds</a>
                        </div>`;
                }
            }
        }
    }
}

function setUserCoins(c) { userCoins = c; }

function validateCheckout() {
    const cartData = JSON.parse(localStorage.getItem('cart') || '[]');
    if (cartData.length === 0) {
        alert('Your cart is empty!');
        return false;
    }
    <?php if ($loggedIn): ?>
    const coinTotal = cartData.reduce((s, i) => s + ((i.coinPrice || i.price) * i.quantity), 0);
    if (coinTotal > userCoins) {
        alert('Insufficient coins! You need ' + coinTotal.toLocaleString() + ' coins but have ' + userCoins.toLocaleString());
        return false;
    }
    <?php else: ?>
    document.getElementById('payment-error').style.display = 'block';
    return false;
    <?php endif; ?>
    document.getElementById('cart-data').value = JSON.stringify(cartData);
    return true;
}

function autoSelectCoins() {
    const coinsMethod = document.querySelector('.coins-method');
    if (coinsMethod) {
        coinsMethod.classList.add('selected');
        document.getElementById('selected-payment').value = 'coins';
        document.getElementById('coins-payment-info').style.display = 'block';
        document.getElementById('mad-payment-info').style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', async () => {
    await getBundles();
    autoSelectCoins();
    const cartData = JSON.parse(localStorage.getItem('cart') || '[]');
    const container = document.getElementById('checkout-items');
    const summary = document.getElementById('checkout-summary');

    if (cartData.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="padding: 2rem;">
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <h3>Cart is empty</h3>
                <a href="shop.php" class="btn btn-primary"><i class="fas fa-store"></i> Browse Shop</a>
            </div>`;
        summary.innerHTML = '';
        return;
    }

    container.innerHTML = cartData.map(item => `
        <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.8rem 1rem; margin-bottom: 0.5rem;">
            <div>
                <div style="font-weight: 600;">${item.name}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">Qty: ${item.quantity}</div>
                ${item.models ? `<div style="font-size:0.75rem;color:var(--info);margin-top:3px;"><i class="fas fa-car"></i> ${item.models.map(m => 'ID: ' + m).join(', ')}</div>` : ''}
            </div>
            <div style="font-weight: 700; color: var(--accent);"><i class="fas fa-coins"></i> ${(item.price * item.quantity).toFixed(0)}</div>
        </div>
    `).join('');

    const baseTotal = cartData.reduce((s, i) => s + (i.price * i.quantity), 0);
    const coinTotal = cartData.reduce((s, i) => s + ((i.coinPrice || i.price) * i.quantity), 0);
    const bundleTotal = cartData.length > 1 ? calculateBundleTotal(cartData) : null;
    const finalTotal = bundleTotal !== null ? Math.min(baseTotal, bundleTotal) : baseTotal;
    const hasDiscount = finalTotal < baseTotal;

    let discountHtml = '';
    if (hasDiscount) {
        discountHtml = `
            <div style="display: flex; justify-content: space-between; padding: 0.3rem 0; font-size: 0.85rem; color: var(--success);">
                <span><i class="fas fa-tag"></i> Bundle Discount</span>
                <span><i class="fas fa-coins"></i> -${(baseTotal - finalTotal).toFixed(0)}</span>
            </div>`;
    }

    summary.innerHTML = `
        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-top: 1rem;">
            ${discountHtml}
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                <span style="color: var(--text-secondary);"><i class="fas fa-coins"></i> Total</span>
                <span style="font-size: 1.5rem; font-weight: 800; color: ${hasDiscount ? 'var(--success)' : 'var(--accent)'}; font-family: var(--header-font);" id="coin-total-value" data-coin-total="${coinTotal}">${finalTotal.toLocaleString()} <small style="font-size:0.7rem;">coins</small></span>
            </div>
            ${hasDiscount ? '<div style="font-size:0.8rem;color:var(--success);text-align:center;"><i class="fas fa-check-circle"></i> Bundle discount applied!</div>' : ''}
            ${coinTotal > userCoins ? '<div style="font-size:0.8rem;color:var(--danger);text-align:center;margin-top:0.3rem;">You don\'t have enough coins. <a href="profile.php">Add funds</a></div>' : ''}
        </div>
    `;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
