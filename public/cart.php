<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <h2 class="section-title"><i class="fas fa-shopping-cart title-accent"></i> Shopping Cart</h2>
        <p class="section-subtitle">Review your order before checkout</p>

        <div id="cart-items">
            <div class="empty-state">
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <h3>Loading cart...</h3>
            </div>
        </div>

        <div id="cart-summary"></div>

        <div style="text-align: center; margin-top: 1rem;">
            <a href="shop.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
